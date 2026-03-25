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
    <title>About | CivicPulse</title>
    <style>
        * {
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:'Segoe UI',Arial,sans-serif;
        }
        body {
            background:#f4f7f9;
            color:#333;
        }
        /* Navbar */
        .navbar {
            background:#1f4e79;
            color:#fff;
            padding:16px 40px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            box-shadow:0 2px 10px rgba(0,0,0,0.2);
        }
        .brand {
            font-size:1.3rem;
            font-weight:800;
        }
        .nav-links {
            display:flex;
            gap:12px;
        }
        .nav-links a {
            color:#fff;
            text-decoration:none;
            padding:8px 16px;
            border-radius:6px;
            font-weight:600;
            border:1px solid rgba(255,255,255,0.3);
        }
        .nav-links a:hover {
            background:rgba(255,255,255,0.15);
        }
        /* Hero */
        .hero {
            background:linear-gradient(135deg,#1f4e79,#2e6ca6);
            color:#fff;
            text-align:center;
            padding:70px 20px;
        }
        .hero h1 {
            font-size:2.5rem;
            margin-bottom:10px;
        }
        .hero p {
            max-width:700px;
            margin:auto;
            font-size:1rem;
        }
        /* Sections */
        .section {
            padding:60px 40px;
            max-width:1100px;
            margin:auto;
        }
        .section h2 {
            color:#1f4e79;
            margin-bottom:20px;
        }
        .section p {
            line-height:1.8;
            color:#555;
        }
        /* Cards */
        .feature-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:20px;
            margin-top:30px;
        }
        .feature-card {
            background:#fff;
            padding:24px;
            border-radius:12px;
            text-align:center;
            box-shadow:0 4px 15px rgba(0,0,0,0.08);
        }
        .feature-card h4 {
            color:#1f4e79;
            margin:10px 0;
        }
        /* CTA */
        .cta {
            background:linear-gradient(135deg,#1f4e79,#2e6ca6);
            color:#fff;
            text-align:center;
            padding:60px 20px;
        }
        .cta a {
            display:inline-block;
            margin:10px;
            padding:14px 30px;
            background:#f59e0b;
            color:#fff;
            text-decoration:none;
            border-radius:8px;
            font-weight:700;
        }
        /* Footer */
        footer {
            background:#0f2744;
            color:#fff;
            text-align:center;
            padding:20px;
        }
        /* Modal */
        .modal {
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            justify-content:center;
            align-items:center;
            z-index:999;
        }
        .modal-content {
            background:#fff;
            padding:30px;
            border-radius:12px;
            text-align:center;
            width:320px;
        }
        .modal-content h3 {
            margin-bottom:20px;
            color:#1f4e79;
        }
        .modal-btn {
            display:block;
            margin:10px 0;
            padding:12px;
            background:#1f4e79;
            color:#fff;
            text-decoration:none;
            border-radius:8px;
        }
        .register {
            background:#f59e0b;
        }
        .close-btn {
            margin-top:15px;
            padding:10px 20px;
            border:none;
            background:#ccc;
            border-radius:8px;
            cursor:pointer;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="brand">🏙️ CivicPulse</div>
    <div class="nav-links">
        <?php if ($is_user): ?>
            <a href="userside/home.php">Dashboard</a>
            <a href="userside/logout.php">Logout</a>
        <?php elseif ($is_admin): ?>
            <a href="adminside/dashboard.php">Admin Dashboard</a>
            <a href="adminside/logout.php">Logout</a>
        <?php else: ?>
            <a href="#" onclick="openModal()">Sign In / Register</a>
        <?php endif; ?>
    </div>
</nav>
<!-- Hero -->
<div class="hero">
    <h1>About CivicPulse</h1>
    <p>
        A smart civic complaint management platform connecting citizens and local government departments for faster resolution of public issues.
    </p>
</div>
<!-- Purpose -->
<div class="section">
    <h2>Our Purpose</h2>
    <p>
        CivicPulse helps citizens report civic issues such as road damage, streetlight failure, drainage problems, and garbage collection issues directly to relevant departments.
    </p>
    <div class="feature-grid">
        <div class="feature-card">
            <h4>📍 Location Pinning</h4>
            <p>Attach exact complaint location using map.</p>
        </div>
        <div class="feature-card">
            <h4>📷 Photo Upload</h4>
            <p>Upload complaint evidence.</p>
        </div>
        <div class="feature-card">
            <h4>📊 Track Status</h4>
            <p>Monitor complaint progress live.</p>
        </div>
        <div class="feature-card">
            <h4>🤖 AI Grouping</h4>
            <p>Similar complaints grouped automatically.</p>
        </div>
    </div>
</div>
<!-- CTA -->
<div class="cta">
    <h2>Join CivicPulse Today 🚀</h2>
    <a href="#" onclick="openModal()">Get Started</a>
</div>
<!-- Footer -->
<footer>
    CivicPulse — Empowering Citizens, Enabling Governance
</footer>
<!-- Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <h3>Select Login Type</h3>
        <a href="userside/login.php" class="modal-btn">👤 User Login</a>
        <a href="adminside/adminlogin.php" class="modal-btn">🛡️ Admin Login</a>
        <a href="userside/signup.php" class="modal-btn register">📝 Register as User</a>
        <button onclick="closeModal()" class="close-btn">Close</button>
    </div>
</div>
<!-- Script -->
<script>
function openModal() {
    document.getElementById("roleModal").style.display = "flex";
}
function closeModal() {
    document.getElementById("roleModal").style.display = "none";
}
window.onclick = function(event) {
    let modal = document.getElementById("roleModal");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
</html>
