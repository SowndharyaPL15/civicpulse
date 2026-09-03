<?php
session_start();
include "config.php";

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once __DIR__ . '/../database/mail_helper.php';

// Redirect if no email in session
if(!isset($_SESSION['email'])){
    header("Location: signup.php");
    exit;
}

$email = trim($_SESSION['email']);
$error = '';
$success = '';

if(isset($_POST['resend'])){

    // Generate new 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Update OTP in database
    $stmt = $conn->prepare("UPDATE user SET otp=? WHERE email=? AND status='inactive'");
    $stmt->bind_param("ss", $otp, $email);
    $stmt->execute();

    if($stmt->affected_rows >= 0){

        // Fetch user name
        $stmt2 = $conn->prepare("SELECT name FROM user WHERE email=?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $user = $stmt2->get_result()->fetch_assoc();
        $name = $user['name'] ?? 'User';

        $mail_error = null;
        $sent = civicpulse_send_otp_email($email, $name, $otp, $mail_error);

        if (!$sent) {
            $_SESSION['dev_otp'] = $otp;
            if (!empty($mail_error)) {
                $_SESSION['mail_error'] = $mail_error;
            }
            $success = "Dev Mode Fallback: New OTP generated.";
        } else {
            unset($_SESSION['dev_otp']);
            unset($_SESSION['mail_error']);
            $success = "A new OTP has been sent to your email.";
        }

        header("Location: verify_otp.php");
        exit;

    } else {
        $error = "Account not found or already verified.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resend OTP — CivicPulse</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>Resend OTP</h2>
        <p>OTP will be sent to: <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="resend">Send New OTP</button>
        </form>

        <p style="margin-top:15px;">
            <a href="verify_otp.php">← Back to OTP verification</a>
        </p>
    </div>
</div>
</body>
</html>
