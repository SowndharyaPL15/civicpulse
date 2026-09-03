<?php
session_start();
include "config.php";

if(!isset($_SESSION['change_pass_admin'])){
    header("Location: adminlogin.php");
    exit;
}

$admin_id = $_SESSION['change_pass_admin'];

if(isset($_POST['change'])){
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if(strlen($new_pass) < 6){
        $error = "Password must be at least 6 characters!";
    } elseif($new_pass !== $confirm_pass){
        $error = "Passwords do not match!";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin SET password=?, temp_pass=0 WHERE admin_id=?");
        $stmt->bind_param("si",$hashed,$admin_id);
        $stmt->execute();

        unset($_SESSION['change_pass_admin']);
        $_SESSION['admin_id'] = $admin_id;
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password — CivicPulse Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
    body{
        height:100vh;
        background:linear-gradient(135deg,#0f172a,#1e3a5f);
        display:flex;
        align-items:center;
        justify-content:center;
        font-family:'Inter',sans-serif;
        margin:0;
    }

    .pass-card{
        width:440px;
        max-width:92vw;
        background:white;
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 25px 50px rgba(0,0,0,0.25);
    }

    .pass-header{
        background:linear-gradient(135deg,#0f172a,#1e3a5f);
        color:white;
        padding:30px;
        text-align:center;
    }

    .pass-header h3{
        margin:0;
        font-weight:700;
    }

    .pass-header p{
        opacity:0.85;
        font-size:14px;
        margin-top:5px;
    }

    .pass-body{
        padding:30px;
    }

    .form-control{
        border-radius:10px;
        padding:12px;
        border:1px solid #e5e7eb;
    }

    .form-control:focus{
        border-color:#2563eb;
        box-shadow:0 0 0 3px rgba(37,99,235,0.1);
    }

    .btn-primary{
        background:#2563eb;
        border:none;
        border-radius:10px;
        padding:12px;
        font-weight:600;
    }

    .btn-primary:hover{
        background:#1e40af;
    }

    .form-label{
        font-weight:500;
        color:#374151;
        font-size:14px;
    }
    </style>
</head>
<body>

<div class="pass-card">

    <div class="pass-header">
        <h3>🔑 Set New Password</h3>
        <p>Create a strong password for your admin account</p>
    </div>

    <div class="pass-body">

        <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required minlength="6">
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required minlength="6">
            </div>

            <button type="submit" name="change" class="btn btn-primary w-100">Change Password</button>
        </form>

    </div>

</div>

</body>
</html>
