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

        <?php if(isset($_SESSION['dev_otp'])): ?>
        <div class="success" style="background-color: #fff3cd; color: #856404; border-left: 5px solid #ffc107; margin-bottom:15px; padding:12px; border-radius:6px; font-size:14px;">
            ⚠️ <strong>Dev Mode Fallback:</strong> <?= htmlspecialchars($_SESSION['mail_error'] ?? 'Email delivery failed.') ?><br>
            👉 Enter this OTP to continue: <strong style="font-size:18px; color:#1e3a8a; letter-spacing:2px;"><?= htmlspecialchars($_SESSION['dev_otp']) ?></strong>
        </div>
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
