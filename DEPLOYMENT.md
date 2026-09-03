# 🚀 CivicPulse Deployment Guide

This guide covers how to deploy **CivicPulse** (PHP 8.2 + Apache + MySQL + Python AI grouping) to cloud platforms (**Railway**, **Render**, **Fly.io**) or containerized on any server using **Docker Compose**.

---

## 📋 Table of Contents

1. [Option 1: Railway (Recommended — Fastest 1-Click Setup)](#option-1-railway-recommended)
2. [Option 2: Render](#option-2-render)
3. [Option 3: Docker Compose (Local / Cloud VPS / AWS EC2)](#option-3-docker-compose)
4. [Option 4: Fly.io](#option-4-flyio)
5. [🔑 Environment Variables Reference](#-environment-variables-reference)
6. [🔐 First-Time Login & Verification Checklist](#-first-time-login--verification-checklist)

---

## Option 1: Railway (Recommended)

Railway is the fastest PaaS platform for CivicPulse because it natively provisions MySQL and automatically injects connection credentials.

### Steps:
1. **Push your code to GitHub**:
   ```bash
   git init
   git add .
   git commit -m "feat: setup cloud deployment for CivicPulse"
   git remote add origin https://github.com/YOUR_USERNAME/civicpulse.git
   git branch -M main
   git push -u origin main
   ```

2. **Create a Railway Project**:
   - Go to [Railway.app](https://railway.app) and sign in.
   - Click **+ New Project** → **Deploy from GitHub repo**.
   - Select your `civicpulse` repository.

3. **Add MySQL Database**:
   - In the Railway project dashboard, click **+ New** → **Database** → **Add MySQL**.
   - Railway will automatically provision MySQL and expose `MYSQLHOST`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`, `MYSQLPORT`, and `MYSQL_URL`.

4. **Connect Web Service to Database**:
   - In Railway, the app container will automatically detect `MYSQL_URL` / `MYSQL*` variables.
   - Go to your Web Service **Settings** → **Networking** → Click **Generate Domain** (e.g., `civicpulse-production.up.railway.app`).

5. **Configure Environment Variables (Optional)**:
   Under the Web Service **Variables** tab, add your Gmail SMTP credentials for OTP emails:
   - `SMTP_HOST`: `smtp.gmail.com`
   - `SMTP_PORT`: `587`
   - `SMTP_USER`: `your-email@gmail.com`
   - `SMTP_PASS`: `your-gmail-app-password`
   - `SMTP_SECURE`: `tls`
   - `SMTP_FROM_NAME`: `CivicPulse`

6. **Automatic Migration**:
   - On container startup, `entrypoint.sh` automatically runs `init_db.php`, creating all tables and the initial admin user (`admin@civicpulse.com`).

---

## Option 2: Render

Render offers free/low-cost cloud Docker web services and managed databases.

### Steps:
1. **Push your code to GitHub** (as shown above).
2. **Create a New Web Service on Render**:
   - Sign in at [Render.com](https://render.com).
   - Click **New +** → **Web Service**.
   - Connect your GitHub repository.
   - Runtime: **Docker** (Render will automatically detect the `Dockerfile`).
   - Instance Type: **Starter** or **Free**.

3. **Connect MySQL Database**:
   - Since Render provides native PostgreSQL, you can either:
     - Use a free MySQL cloud instance (e.g., [Aiven MySQL](https://aiven.io), [PlanetScale](https://planetscale.com), or [Clever Cloud](https://www.clever-cloud.com/)).
     - Or deploy MySQL as a Docker service.
   - Add the database connection string or individual variables in the Render **Environment** tab:
     - `DB_HOST`: `<your-db-host>`
     - `DB_PORT`: `3306`
     - `DB_USER`: `<your-db-user>`
     - `DB_PASS`: `<your-db-password>`
     - `DB_NAME`: `<your-db-name>`

4. **Add Persistent Disk (Optional for file uploads)**:
   - In Render Web Service settings, go to **Disks** → **Add Disk**.
   - Mount Path: `/var/www/html/uploads`
   - Size: `1 GB` or more.

5. **Deploy**:
   - Click **Create Web Service**.
   - Once deployed, visit your Render URL (e.g., `https://civicpulse.onrender.com`).

---

## Option 3: Docker Compose (Local / Cloud VPS / AWS EC2 / DigitalOcean)

Run the entire stack (PHP Apache web app + MySQL 8.0 + Python AI grouping + phpMyAdmin) with a single command.

### Prerequisites:
- Docker and Docker Compose installed.

### Steps:
1. **Clone or navigate to the directory**:
   ```bash
   cd Civicpulse
   ```

2. **(Optional) Configure environment variables**:
   Create or edit `.env`:
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-email@gmail.com
   SMTP_PASS=your-app-password
   ADMIN_EMAIL=admin@civicpulse.com
   ADMIN_PASSWORD=password
   ```

3. **Start the containers**:
   ```bash
   docker-compose up -d --build
   ```

4. **Access the application**:
   - **Citizen Portal**: [http://localhost:8080/](http://localhost:8080/)
   - **Citizen Login**: [http://localhost:8080/userside/login.php](http://localhost:8080/userside/login.php)
   - **Admin Portal**: [http://localhost:8080/adminside/adminlogin.php](http://localhost:8080/adminside/adminlogin.php)
   - **phpMyAdmin**: [http://localhost:8081/](http://localhost:8081/) (User: `root`, Password: `civicpulse_secret`)

5. **Stop containers**:
   ```bash
   docker-compose down
   ```

---

## Option 4: Fly.io

### Steps:
1. Install Fly CLI: `flyctl` ([https://fly.io/docs/hands-on/install-flyctl/](https://fly.io/docs/hands-on/install-flyctl/))
2. Sign in: `fly auth login`
3. Launch app:
   ```bash
   fly launch
   ```
4. Create volume for uploads:
   ```bash
   fly volumes create civicpulse_uploads --size 1
   ```
5. Set secrets (Database & SMTP):
   ```bash
   fly secrets set DB_HOST="your-db-host" DB_USER="your-db-user" DB_PASS="your-db-pass" DB_NAME="your-db-name" SMTP_USER="your-email" SMTP_PASS="your-pass"
   ```
6. Deploy:
   ```bash
   fly deploy
   ```

---

## 🔑 Environment Variables Reference

| Variable | Description | Default / Example |
|---|---|---|
| `DATABASE_URL` / `MYSQL_URL` | Full MySQL connection URL | `mysql://root:pass@host:3306/db` |
| `DB_HOST` / `MYSQLHOST` | MySQL hostname | `localhost` / `db` |
| `DB_PORT` / `MYSQLPORT` | MySQL port | `3306` |
| `DB_USER` / `MYSQLUSER` | MySQL username | `root` |
| `DB_PASS` / `MYSQLPASSWORD` | MySQL password | `""` |
| `DB_NAME` / `MYSQLDATABASE` | Database name | `otp_verification` |
| `ADMIN_NAME` | Default administrator display name | `Super Admin` |
| `ADMIN_EMAIL` | Default administrator email | `admin@civicpulse.com` |
| `ADMIN_PASSWORD` | Default administrator password | `password` |
| `SMTP_HOST` | SMTP server host | `smtp.gmail.com` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | SMTP username / email address | `""` |
| `SMTP_PASS` | SMTP application password | `""` |
| `SMTP_SECURE` | Encryption protocol | `tls` |
| `SMTP_FROM_NAME` | Sender display name | `CivicPulse` |
| `PORT` | Web server port (dynamically handled) | `80` (or injected by PaaS) |

---

## 🔐 First-Time Login & Verification Checklist

1. **Visit Root URL**:
   Opening `https://<your-domain>/` will automatically route you to the Citizen Portal landing page (`/userside/about.php`).

2. **Admin Login**:
   - URL: `https://<your-domain>/adminside/adminlogin.php`
   - Default Email: `admin@civicpulse.com` (or your configured `ADMIN_EMAIL`)
   - Default Password: `password` (or your configured `ADMIN_PASSWORD`)
   - *Security Note*: Change your admin password immediately after first login under **Change Password**.

3. **Citizen Registration**:
   - Register a new citizen account at `/userside/signup.php`.
   - Enter OTP received via email (or check the database `user` table if testing without SMTP).

4. **AI Complaint Grouping Verification**:
   - Submit a test complaint with GPS coordinates.
   - The Python script (`process_complaints.py`) will automatically execute in the background using `sentence-transformers` and `scikit-learn` to group related issues.
   - In the Admin dashboard, you can also manually trigger AI grouping anytime via the **Run AI Grouping** button.
