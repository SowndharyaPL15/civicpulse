import mysql.connector
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
import os
import math

# -------------------------------------------------
# LOAD ENVIRONMENT
# -------------------------------------------------

def load_env(path=None):
    """Load .env file from project root."""
    if path is None:
        path = os.path.join(os.path.dirname(os.path.abspath(__file__)), '.env')
    if os.path.exists(path):
        with open(path) as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if '=' in line:
                    key, value = line.split('=', 1)
                    os.environ.setdefault(key.strip(), value.strip())

load_env()

# -------------------------------------------------
import urllib.parse

def get_db_config():
    db_url = os.environ.get("DATABASE_URL") or os.environ.get("MYSQL_URL") or os.environ.get("CLEARDB_DATABASE_URL")
    if db_url:
        parsed = urllib.parse.urlparse(db_url)
        return {
            "host": parsed.hostname or "localhost",
            "port": parsed.port or 3306,
            "user": parsed.username or "root",
            "password": parsed.password or "",
            "database": parsed.path.lstrip('/') if parsed.path else "otp_verification"
        }
    
    return {
        "host": os.environ.get("MYSQLHOST") or os.environ.get("DB_HOST", "localhost"),
        "port": int(os.environ.get("MYSQLPORT") or os.environ.get("DB_PORT", 3306)),
        "user": os.environ.get("MYSQLUSER") or os.environ.get("DB_USER", "root"),
        "password": os.environ.get("MYSQLPASSWORD") or os.environ.get("DB_PASS") or os.environ.get("DB_PASSWORD", ""),
        "database": os.environ.get("MYSQLDATABASE") or os.environ.get("DB_NAME", "otp_verification")
    }

SIMILARITY_THRESHOLD = 0.35
DISTANCE_THRESHOLD_KM = 1.0  # Group complaints within 1.0 km

DB_CONFIG = get_db_config()

# -------------------------------------------------
# DISTANCE HELPER
# -------------------------------------------------

def haversine_distance(lat1, lon1, lat2, lon2):
    if lat1 is None or lon1 is None or lat2 is None or lon2 is None:
        return float('inf')
    R = 6371.0  # Radius of earth in km
    dlat = math.radians(lat2 - lat1)
    dlon = math.radians(lon2 - lon1)
    a = math.sin(dlat / 2)**2 + math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(dlon / 2)**2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    return R * c

# -------------------------------------------------
# PRIORITY CALCULATION
# -------------------------------------------------

def calculate_priority(count):
    if count >= 6:
        return "HIGH"
    elif count >= 3:
        return "MEDIUM"
    else:
        return "LOW"


# -------------------------------------------------
# CONNECT DATABASE
# -------------------------------------------------

print("Connecting to database...")

# Enable SSL if connecting to remote database (e.g. TiDB Cloud, Aiven, PlanetScale)
if DB_CONFIG.get("host") not in ("localhost", "127.0.0.1", "db"):
    ca_path = os.environ.get("MYSQL_SSL_CA", "/etc/ssl/certs/ca-certificates.crt")
    if os.path.exists(ca_path):
        DB_CONFIG["ssl_ca"] = ca_path
    DB_CONFIG["ssl_disabled"] = False

try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    print("Database connected")
except mysql.connector.Error as e:
    print(f"Database connection failed: {e}")
    exit(1)


# -------------------------------------------------
# LOAD MODEL
# -------------------------------------------------

print("Loading AI model...")

model = SentenceTransformer('all-MiniLM-L6-v2')

print("Model loaded successfully")


# -------------------------------------------------
# FETCH OPEN ISSUES
# -------------------------------------------------

cursor.execute("""
SELECT id, issue_title, department_id, issue_type_id,
complaint_count, latitude, longitude
FROM issues
WHERE status='Open'
""")

issues = cursor.fetchall()

print("Open issues:", len(issues))


# -------------------------------------------------
# GROUP ISSUES BY (DEPARTMENT + TYPE)
# -------------------------------------------------

issue_groups = {}

for issue in issues:

    key = (issue["department_id"], issue["issue_type_id"])

    if key not in issue_groups:
        issue_groups[key] = {
            "ids": [],
            "titles": [],
            "counts": [],
            "embeddings": [],
            "latitudes": [],
            "longitudes": []
        }

    issue_groups[key]["ids"].append(issue["id"])
    issue_groups[key]["titles"].append(issue["issue_title"])
    issue_groups[key]["counts"].append(issue["complaint_count"])
    issue_groups[key]["latitudes"].append(issue["latitude"])
    issue_groups[key]["longitudes"].append(issue["longitude"])


# -------------------------------------------------
# PRECOMPUTE ISSUE EMBEDDINGS
# -------------------------------------------------

print("Encoding issue embeddings...")

for key in issue_groups:

    titles = issue_groups[key]["titles"]

    if len(titles) > 0:
        issue_groups[key]["embeddings"] = model.encode(titles)


# -------------------------------------------------
# FETCH UNGROUPED COMPLAINTS
# -------------------------------------------------

cursor.execute("""
SELECT cid, description, department_id, issue_type_id,
latitude, longitude
FROM complaints
WHERE issue_id IS NULL
""")

complaints = cursor.fetchall()

print("Ungrouped complaints:", len(complaints))


# -------------------------------------------------
# PROCESS COMPLAINTS
# -------------------------------------------------

for complaint in complaints:

    cid = complaint["cid"]
    text = complaint["description"]
    lat = complaint["latitude"]
    lng = complaint["longitude"]

    if text is None or text.strip() == "":
        continue

    dept = complaint["department_id"]
    type_id = complaint["issue_type_id"]

    key = (dept, type_id)

    complaint_embedding = model.encode([text])

    matched_issue = None
    best_score = 0


    # -------------------------------------------------
    # CHECK EXISTING ISSUES
    # -------------------------------------------------

    if key in issue_groups and len(issue_groups[key]["ids"]) > 0:

        embeddings = issue_groups[key]["embeddings"]
        scores = cosine_similarity(complaint_embedding, embeddings)[0]

        best_score = 0
        best_index = -1

        # Check distance for all candidate issues first
        for idx in range(len(issue_groups[key]["ids"])):
            issue_lat = issue_groups[key]["latitudes"][idx]
            issue_lng = issue_groups[key]["longitudes"][idx]

            # Calculate distance using Haversine formula
            dist = haversine_distance(lat, lng, issue_lat, issue_lng)

            if dist <= DISTANCE_THRESHOLD_KM:
                if scores[idx] > best_score:
                    best_score = scores[idx]
                    best_index = idx

        if best_index != -1 and best_score >= SIMILARITY_THRESHOLD:

            matched_issue = issue_groups[key]["ids"][best_index]

            # update complaint count
            new_count = issue_groups[key]["counts"][best_index] + 1
            issue_groups[key]["counts"][best_index] = new_count

            new_priority = calculate_priority(new_count)

            cursor.execute("""
            UPDATE issues
            SET complaint_count=%s,
                priority=%s
            WHERE id=%s
            """, (new_count, new_priority, matched_issue))

            print(f"Complaint {cid} matched issue {matched_issue}")


    # -------------------------------------------------
    # CREATE NEW ISSUE
    # -------------------------------------------------

    if matched_issue is None:

        priority = calculate_priority(1)

        issue_title = text[:120]

        cursor.execute("""
        INSERT INTO issues
        (issue_title, department_id, issue_type_id,
        complaint_count, priority, status,
        latitude, longitude, created_at)
        VALUES (%s,%s,%s,1,%s,'Open',%s,%s,NOW())
        """, (issue_title, dept, type_id, priority, lat, lng))

        matched_issue = cursor.lastrowid

        print(f"Created new issue {matched_issue} for complaint {cid}")


        if key not in issue_groups:
            issue_groups[key] = {
                "ids": [],
                "titles": [],
                "counts": [],
                "embeddings": [],
                "latitudes": [],
                "longitudes": []
            }

        issue_groups[key]["ids"].append(matched_issue)
        issue_groups[key]["titles"].append(issue_title)
        issue_groups[key]["counts"].append(1)
        issue_groups[key]["latitudes"].append(lat)
        issue_groups[key]["longitudes"].append(lng)

        issue_groups[key]["embeddings"] = model.encode(issue_groups[key]["titles"])


    # -------------------------------------------------
    # LINK COMPLAINT TO ISSUE
    # -------------------------------------------------

    cursor.execute("""
    UPDATE complaints
    SET issue_id=%s
    WHERE cid=%s
    """, (matched_issue, cid))


    # -------------------------------------------------
    # RECALCULATE ISSUE LOCATION (CENTROID)
    # -------------------------------------------------

    cursor.execute("""
    SELECT AVG(latitude) AS lat, AVG(longitude) AS lng
    FROM complaints
    WHERE issue_id=%s
    """, (matched_issue,))

    loc = cursor.fetchone()

    if loc["lat"] is not None and loc["lng"] is not None:

        cursor.execute("""
        UPDATE issues
        SET latitude=%s, longitude=%s
        WHERE id=%s
        """, (loc["lat"], loc["lng"], matched_issue))


# -------------------------------------------------
# COMMIT CHANGES
# -------------------------------------------------

conn.commit()

cursor.close()
conn.close()

print("AI complaint grouping with location completed successfully")