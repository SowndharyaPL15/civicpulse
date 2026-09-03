<?php
session_start();
include "config.php";

if(!isset($_SESSION['otp_admin_id'])){
    header("Location: adminlogin.php");
    exit;
}

$admin_id = $_SESSION['otp_admin_id'];

if(isset($_POST['verify'])){
    $input_otp = $_POST['otp'];
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id=? AND otp=? AND otp_expiry > NOW()");
    $stmt->bind_param("is",$admin_id,$input_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $stmt2 = $conn->prepare("UPDATE admin SET active=1, otp=NULL, otp_expiry=NULL WHERE admin_id=?");
        $stmt2->bind_param("i",$admin_id);
        $stmt2->execute();

        unset($_SESSION['otp_admin_id']);
        $_SESSION['change_pass_admin'] = $admin_id;
        header("Location: change_pass.php");
        exit;
    } else {
        $error = "Invalid or expired OTP!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification — CivicPulse Admin</title>
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

    .otp-card{
        width:420px;
        max-width:92vw;
        background:white;
        border-radius:16px;
        overflow:hidden;
        box-shadow:0 25px 50px rgba(0,0,0,0.25);
    }

    .otp-header{
        background:linear-gradient(135deg,#0f172a,#1e3a5f);
        color:white;
        padding:30px;
        text-align:center;
    }

    .otp-header h3{
        margin:0;
        font-weight:700;
    }

    .otp-header p{
        opacity:0.85;
        font-size:14px;
        margin-top:5px;
    }

    .otp-body{
        padding:30px;
    }

    .form-control{
        border-radius:10px;
        padding:14px;
        font-size:18px;
        text-align:center;
        letter-spacing:8px;
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
    </style>
</head>
<body>

<div class="otp-card">

    <div class="otp-header">
        <h3>🔐 OTP Verification</h3>
        <p>Enter the 6-digit code sent to your email</p>
    </div>

    <div class="otp-body">

        <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <input type="text" name="otp" class="form-control" placeholder="------" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
            </div>
            <button type="submit" name="verify" class="btn btn-primary w-100">Verify OTP</button>
        </form>

        <div class="text-center mt-3">
            <a href="adminlogin.php" class="text-muted text-decoration-none">← Back to Login</a>
        </div>

    </div>

</div>

</body>
</html>
