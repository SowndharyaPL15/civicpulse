<?php
session_start();

$is_user  = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['admin_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CivicPulse | About System</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    --bg:#0b1220;
    --card:#111a2e;
    --primary:#4f8cff;
    --success:#22c55e;
    --text:#e5e7eb;
    --muted:#9ca3af;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Inter, sans-serif;
}

body{
    background:linear-gradient(180deg,#0b1220,#0f172a);
    color:var(--text);
}

/* NAV */
nav{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 40px;
    background:rgba(17,26,46,0.75);
    backdrop-filter: blur(10px);
    border-bottom:1px solid rgba(255,255,255,0.05);
    position:sticky;
    top:0;
}

.logo{
    font-weight:700;
    font-size:1.2rem;
}

/* HERO */
.hero{
    text-align:center;
    padding:90px 20px 60px;
}

.hero h1{
    font-size:3rem;
    background:linear-gradient(90deg,#4f8cff,#22c55e);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

.hero p{
    max-width:780px;
    margin:15px auto;
    color:var(--muted);
    line-height:1.6;
}

/* SECTION */
.section{
    max-width:1100px;
    margin:auto;
    padding:50px 20px;
}

.section h2{
    margin-bottom:10px;
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:18px;
    margin-top:25px;
}

.card{
    background:rgba(17,26,46,0.85);
    border:1px solid rgba(255,255,255,0.05);
    padding:22px;
    border-radius:16px;
    transition:0.2s;
}

.card:hover{
    transform:translateY(-5px);
    border-color:rgba(79,140,255,0.4);
}

.card h4{
    margin-bottom:8px;
}

/* ROLE BOX */
.role-container{
    display:flex;
    justify-content:center;
    gap:20px;
    flex-wrap:wrap;
    margin-top:25px;
}

.role-box{
    width:260px;
    padding:25px;
    border-radius:16px;
    background:rgba(17,26,46,0.85);
    border:1px solid rgba(255,255,255,0.05);
    text-align:center;
}

.role-box p{
    font-size:13px;
    color:var(--muted);
    margin-top:8px;
}

/* BUTTONS */
.btn{
    display:inline-block;
    margin-top:15px;
    padding:11px 16px;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
    font-size:14px;
}

.primary{background:var(--primary); color:white;}
.success{background:var(--success); color:white;}
.dark{background:#1f2937; color:white;}

/* FOOTER */
footer{
    text-align:center;
    padding:25px;
    color:var(--muted);
    border-top:1px solid rgba(255,255,255,0.05);
    margin-top:40px;
}
</style>
</head>

<body>

<!-- NAV -->
<nav>
    <div class="logo">🏙 CivicPulse System</div>

    <div>
        <?php if($is_user): ?>
            
            <a class="btn dark" href="logout.php">Logout</a>

        <?php elseif($is_admin): ?>
            <a class="btn success" href="../adminside/dashboard.php">Admin Panel</a>
            <a class="btn dark" href="../adminside/logout.php">Logout</a>

        <?php else: ?>
            <a class="btn primary" href="login.php">User Login</a>
            <a class="btn success" href="../adminside/adminlogin.php">Admin Login</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <h1>CivicPulse</h1>
    <p>
        A smart civic complaint management system that enables citizens to report issues
        with GPS tagging, image evidence, AI grouping, and automatic worker assignment
        for faster resolution by local authorities.
    </p>
</div>

<!-- FEATURES -->
<div class="section">
    <h2>System Features</h2>

    <div class="grid">

        <div class="card">
            <h4>📍 GPS-Based Reporting</h4>
            <p>Users submit complaints with exact location tracking.</p>
        </div>

        <div class="card">
            <h4>🤖 AI Grouping</h4>
            <p>Similar complaints are automatically clustered.</p>
        </div>

        <div class="card">
            <h4>👷 Smart Assignment</h4>
            <p>Workers are assigned based on department and availability.</p>
        </div>

        <div class="card">
            <h4>📊 Admin Dashboard</h4>
            <p>Real-time analytics for decision making and monitoring.</p>
        </div>

    </div>
</div>

<!-- ROLE ACCESS -->
<div class="section" style="text-align:center;">
    <h2>Access the Platform</h2>
    <p style="color:#9ca3af;">Choose your role to continue</p>

    <div class="role-container">

        <div class="role-box">
            <h3>👤 Citizen</h3>
            <p>Report and track civic issues in your area.</p>
            <a href="login.php" class="btn primary">Enter User Portal</a>
        </div>

        <div class="role-box">
            <h3>🛡 Admin</h3>
            <p>Manage complaints, workers, and analytics dashboard.</p>
            <a href="../adminside/adminlogin.php" class="btn success">Enter Admin Panel</a>
        </div>

    </div>
</div>

<footer>
    CivicPulse • Smart Civic Governance System • Built for Real-World Impact
</footer>

</body>
</html>