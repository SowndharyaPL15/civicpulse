# 🏙 CivicPulse

**A Smart Civic Complaint Management System**

CivicPulse enables citizens to report real-world community problems — such as potholes, garbage accumulation, broken streetlights, water issues, and other public infrastructure issues — with GPS tagging, image evidence, and real-time tracking. Authorities can manage, assign, and resolve issues through an admin dashboard with analytics.

---

## 📋 Problem Statement

Urban civic infrastructure issues often go unreported or unresolved due to lack of a centralized, transparent complaint management system. Citizens have no easy way to report problems with evidence, and authorities struggle to prioritize and track resolution.

CivicPulse bridges this gap by providing:
- A citizen-friendly interface for reporting issues with location and evidence
- AI-powered grouping of similar complaints
- Smart worker assignment based on proximity
- Real-time status tracking for transparency
- Analytics dashboard for administrative decision-making

---

## ✨ Features

### Citizen Module
- **Registration & Login** — OTP-based email verification
- **Dashboard** — View complaint stats (total, open, in-progress, resolved)
- **Submit Complaint** — Select department, issue type, describe problem, upload evidence, pin location on map
- **Track Complaints** — Visual progress tracker (Submitted → Assigned → In Progress → Resolved)
- **Issue Confirmation** — "I also face this" button on map for community validation
- **Profile Management** — Edit profile, change password
- **Complaint Map** — Interactive map with marker clusters showing all complaints

### Admin Module
- **Secure Login** — OTP verification, temporary password support
- **Department Dashboard** — View issues filtered by department with stats
- **Search & Filter** — Search by title/ID, filter by status
- **View Issue Details** — Full details, evidence images, assignment history, map
- **Assign Worker** — Manual selection or auto-assign nearest available worker
- **Update Status** — Change issue status with resolution notes, status history
- **Worker Management** — Add/remove workers, track availability, GPS location
- **Reports & Analytics** — Priority distribution (doughnut chart), issue trends (line chart), complaint heatmap, top issue clusters
- **Central Admin** — Cross-department overview with department assignment

### AI & Smart Features
- **AI Complaint Grouping** — Uses sentence-transformers (all-MiniLM-L6-v2) to cluster similar complaints
- **Automatic Priority** — Priority calculated based on complaint count
- **Smart Worker Assignment** — Haversine distance-based nearest worker assignment
- **Location Centroid** — Issue location recalculated as centroid of all related complaints

---

## 👥 User Roles

| Role | Access |
|------|--------|
| **Citizen** | Register, submit complaints, track status, confirm issues |
| **Department Admin** | Manage department issues, assign workers, update status, view reports |
| **Central Admin** | View all issues, assign departments |

---

## 🔄 System Workflow

```
Citizen submits complaint
        ↓
Complaint stored with GPS + image evidence
        ↓
AI groups similar complaints into issues
        ↓
Priority auto-calculated (LOW/MEDIUM/HIGH)
        ↓
Department admin reviews issue
        ↓
Worker assigned (manual or auto-nearest)
        ↓
Status updated (Open → In Progress → Resolved)
        ↓
Citizen tracks progress in real-time
```

---

## 🛠 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | HTML, CSS, JavaScript, Bootstrap 5 |
| **Backend** | PHP 8+ |
| **Database** | MySQL (via XAMPP) |
| **Maps** | Leaflet.js + OpenStreetMap (no API key needed) |
| **Charts** | Chart.js |
| **Email** | PHPMailer (Gmail SMTP) |
| **AI Grouping** | Python, sentence-transformers, scikit-learn |
| **Geocoding** | Nominatim (OpenStreetMap) |
| **Icons** | Bootstrap Icons |
| **Fonts** | Inter (Google Fonts) |

---

## 📁 Project Structure

```
CivicPulse/
│
├── adminside/                  # Admin module
│   ├── PHPMailer/              # PHPMailer library
│   ├── config.php              # Database configuration
│   ├── adminlogin.php          # Admin login page
│   ├── admin_otp.php           # Admin OTP verification
│   ├── change_pass.php         # Admin password change
│   ├── dashboard.php           # Department dashboard
│   ├── view_issue.php          # Issue details + evidence
│   ├── assign_work.php         # Manual worker assignment
│   ├── smart_assign.php        # Auto nearest worker assignment
│   ├── update_status.php       # Status update + history
│   ├── workers.php             # Worker management (CRUD)
│   ├── reports.php             # Analytics & reports
│   ├── central_admin_dashboard.php  # Cross-department view
│   ├── assign_department.php   # Department assignment handler
│   ├── logout.php              # Admin logout
│   └── adminstyle.css          # Admin stylesheet
│
├── userside/                   # Citizen module
│   ├── PHPMailer/              # PHPMailer library
│   ├── images/                 # Static images
│   ├── config.php              # Database configuration
│   ├── login.php               # Citizen login
│   ├── signup.php              # Citizen registration
│   ├── verify_otp.php          # OTP verification
│   ├── resend_otp.php          # Resend OTP
│   ├── home.php                # Citizen dashboard
│   ├── complaint.php           # Submit complaint form
│   ├── track.php               # Track complaints
│   ├── confirm_issue.php       # "I also face this" API
│   ├── profile.php             # User profile
│   ├── edit_profile.php        # Edit profile
│   ├── change_password.php     # Change password
│   ├── about.php               # About/landing page
│   ├── logout.php              # User logout
│   └── styles.css              # Auth pages stylesheet
│
├── database/
│   └── civicpulse.sql          # Complete database schema
│
├── uploads/                    # Uploaded complaint images
│
├── process_complaints.py       # AI complaint grouping script
├── .env                        # Environment variables (not in git)
├── .env.example                # Environment template
├── .gitignore                  # Git ignore rules
└── README.md                   # This file
```

---

## 🚀 Installation & Setup

### Prerequisites
- **XAMPP** (Apache + MySQL + PHP 8+)
- **Python 3.8+** (for AI grouping feature)
- **pip** (Python package manager)
- **Gmail account** with App Password enabled (for SMTP)

### Step 1: Clone / Place Project
```bash
# Place the CivicPulse folder in your XAMPP htdocs directory
# e.g., C:\xampp\htdocs\Civicpulse
```

### Step 2: Database Setup
```bash
# Start XAMPP (Apache + MySQL)
# Open phpMyAdmin at http://localhost/phpmyadmin
# Import the schema file:
#   database/civicpulse.sql
```

### Step 3: Environment Configuration
```bash
# Copy .env.example to .env (already done if you have .env)
# Update the values in .env:

DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=otp_verification

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_FROM_NAME=CivicPulse
SMTP_SECURE=tls
```

### Step 4: Python Dependencies (for AI grouping)
```bash
pip install sentence-transformers scikit-learn mysql-connector-python numpy python-dotenv
```

### Step 5: Create Admin Account
```sql
-- Run this in phpMyAdmin after importing schema
-- Password: admin123 (will be hashed)
INSERT INTO admin (name, email, password, dept_id, active, temp_pass)
VALUES ('Admin', 'admin@civicpulse.com', 
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
        1, 1, 0);
-- Note: The above hash is for 'password'. Change after first login.
```

### Step 6: Access the Application
```
Citizen Portal:  http://localhost/Civicpulse/userside/about.php
Citizen Login:   http://localhost/Civicpulse/userside/login.php
Admin Login:     http://localhost/Civicpulse/adminside/adminlogin.php
Central Admin:   http://localhost/Civicpulse/adminside/central_admin_dashboard.php
```

---

## 🔑 Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `DB_HOST` | MySQL host | Yes |
| `DB_USER` | MySQL username | Yes |
| `DB_PASS` | MySQL password | Yes |
| `DB_NAME` | Database name | Yes |
| `SMTP_HOST` | SMTP server host | Yes |
| `SMTP_PORT` | SMTP server port | Yes |
| `SMTP_USER` | SMTP email address | Yes |
| `SMTP_PASS` | SMTP app password | Yes |
| `SMTP_FROM_NAME` | Email sender name | No |
| `SMTP_SECURE` | SMTP encryption (tls/ssl) | No |
| `PYTHON_SCRIPT_PATH` | Custom path to AI script | No |

---

## 📡 Key Routes / Pages

### Citizen
| Route | Description |
|-------|-------------|
| `userside/login.php` | Citizen login |
| `userside/signup.php` | Citizen registration |
| `userside/home.php` | Citizen dashboard |
| `userside/complaint.php` | Submit new complaint |
| `userside/track.php` | Track complaint status |
| `userside/profile.php` | View/edit profile |

### Admin
| Route | Description |
|-------|-------------|
| `adminside/adminlogin.php` | Admin login |
| `adminside/dashboard.php` | Department dashboard |
| `adminside/view_issue.php?id=X` | View issue details |
| `adminside/assign_work.php?id=X` | Assign worker to issue |
| `adminside/update_status.php?id=X` | Update issue status |
| `adminside/workers.php` | Manage workers |
| `adminside/reports.php` | Analytics & reports |

---

## 🔮 Future Improvements

- Push/email notifications on status changes
- Password reset via email
- Multi-language support
- Mobile app (React Native / Flutter)
- Advanced duplicate detection with NLP
- Department-wise performance metrics
- Citizen feedback/rating after resolution
- Export reports to PDF/CSV
- Role-based admin hierarchy
- Webhook integrations

---

## 👤 Author

**CivicPulse** — Smart Civic Governance System  
Built for Real-World Impact

---

## 📄 License

This project is for educational and civic-technology purposes.
