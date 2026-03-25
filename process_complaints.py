import mysql.connector
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np

# -------------------------------------------------
# CONFIG
# -------------------------------------------------

SIMILARITY_THRESHOLD = 0.35

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "otp_verification"
}

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

conn = mysql.connector.connect(**DB_CONFIG)
cursor = conn.cursor(dictionary=True)

print("Database connected")


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

        best_index = np.argmax(scores)
        best_score = scores[best_index]

        print(f"Complaint {cid} similarity score: {best_score:.2f}")

        if best_score >= SIMILARITY_THRESHOLD:

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