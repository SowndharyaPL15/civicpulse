<?php
session_start();
include "config.php";

// Redirect if no email in session
if(!isset($_SESSION['email'])){
    header("Location: signup.php");
    exit;
}

$email = trim($_SESSION['email']);

if(isset($_POST['verify'])){
    $entered_otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    // Fetch user details from DB
    $stmt = $conn->prepare("SELECT uid, name, email, otp, status FROM user WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows > 0){
        $row = $res->fetch_assoc();

        if($row['status'] === 'active'){
            // Already active - log in and redirect
            $_SESSION['user_id'] = $row['uid'];
            $_SESSION['user_name'] = $row['name'];
            unset($_SESSION['dev_otp']);
            header("Location: home.php");
            exit;
        } elseif(!empty($entered_otp) && trim((string)$row['otp']) === $entered_otp){
            // Activate account
            $stmt2 = $conn->prepare("UPDATE user SET status='active', otp=NULL WHERE uid=?");
            $stmt2->bind_param("i", $row['uid']);
            $stmt2->execute();

            // Set session & redirect straight to dashboard
            unset($_SESSION['dev_otp']);
            $_SESSION['user_id'] = $row['uid'];
            $_SESSION['user_name'] = $row['name'];
            header("Location: home.php");
            exit;
        } else {
            $error = "Invalid OTP! Please check the code or click Resend OTP.";
        }
    } else {
        $error = "User not found. Please register again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify OTP — CivicPulse</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    .otp-input{
        font-size:22px;
        letter-spacing:8px;
        text-align:center;
        font-weight:bold;
    }
    </style>
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>OTP Verification</h2>
        <p>Enter the 6-digit OTP sent to: <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if(isset($error)): ?>
        <div class="error" style="margin-bottom:15px; color:#dc2626; background:#fee2e2; padding:10px; border-radius:8px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(isset($_SESSION['resend_success'])): ?>
        <div class="success" style="background-color: #dcfce7; color: #15803d; border-left: 5px solid #22c55e; margin-bottom:15px; padding:12px; border-radius:6px; font-size:14px;">
            <?= htmlspecialchars($_SESSION['resend_success']) ?>
        </div>
        <?php unset($_SESSION['resend_success']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['dev_otp'])): ?>
        <div class="success" style="background-color: #fff3cd; color: #856404; border-left: 5px solid #ffc107; margin-bottom:18px; padding:14px; border-radius:8px; font-size:14px; text-align:left;">
            <div style="font-weight:600; margin-bottom:6px;">⚠️ Dev Mode Fallback Active</div>
            <div style="font-size:13px; color:#664d03; margin-bottom:10px; line-height:1.4;">
                SMTP host timed out (Port 587/465 is blocked by your ISP/network). Your verification code is:
            </div>
            <div style="display:flex; align-items:center; justify-content:space-between; background:#fff; padding:8px 12px; border-radius:6px; border:1px dashed #e0a800;">
                <span id="devOtpVal" style="font-size:22px; font-weight:700; color:#1e3a8a; letter-spacing:4px;"><?= htmlspecialchars($_SESSION['dev_otp']) ?></span>
                <button type="button" onclick="autoFillOtp()" style="width:auto; padding:6px 12px; font-size:12px; margin:0; background:#2563eb; color:white; border-radius:6px; border:none; cursor:pointer;">⚡ Auto-Fill Code</button>
            </div>
        </div>
        <script>
        function autoFillOtp() {
            var otp = document.getElementById('devOtpVal').innerText.trim();
            var input = document.querySelector('input[name="otp"]');
            if (input) {
                input.value = otp;
                input.focus();
            }
        }
        </script>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" class="otp-input" placeholder="------" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autofocus autocomplete="one-time-code">
            <button type="submit" name="verify">Verify & Continue</button>
        </form>

        <p style="margin-top:15px;">
            Didn't receive OTP? <a href="resend_otp.php">Resend OTP</a> &bull; <a href="signup.php">Change Email</a>
        </p>
    </div>
</div>
</body>
</html>
