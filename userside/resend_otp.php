<?php
session_start();
include "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

// Redirect if no email in session
if(!isset($_SESSION['email'])){
    header("Location: signup.php");
    exit;
}

$email = $_SESSION['email'];
$error = '';
$success = '';

if(isset($_POST['resend'])){

    // Generate new OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Update OTP in database
    $stmt = $conn->prepare("UPDATE user SET otp=? WHERE email=? AND status='inactive'");
    $stmt->bind_param("ss", $otp, $email);
    $stmt->execute();

    if($stmt->affected_rows > 0){

        // Fetch user name
        $stmt2 = $conn->prepare("SELECT name FROM user WHERE email=?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $user = $stmt2->get_result()->fetch_assoc();
        $name = $user['name'] ?? 'User';

        // Send new OTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USER') ?: '';
            $mail->Password   = getenv('SMTP_PASS') ?: '';
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
            $mail->Port       = getenv('SMTP_PORT') ?: 587;

            $smtp_from = getenv('SMTP_USER') ?: '';
            $smtp_name = getenv('SMTP_FROM_NAME') ?: 'CivicPulse';
            $mail->setFrom($smtp_from, $smtp_name);
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Your New CivicPulse OTP Code';
            $mail->Body    = "
            <div style='font-family:Arial;max-width:500px;margin:auto;padding:30px;border:1px solid #e5e7eb;border-radius:12px;'>
            <h2 style='color:#2563eb;'>CivicPulse</h2>
            <p>Hi <strong>$name</strong>,</p>
            <p>Your new OTP verification code is:</p>
            <div style='font-size:32px;font-weight:bold;color:#2563eb;letter-spacing:8px;padding:15px;background:#f1f5f9;border-radius:8px;text-align:center;'>$otp</div>
            <p style='margin-top:15px;color:#6b7280;'>Use this code to verify your account.</p>
            </div>";

            $mail->send();
            $success = "A new OTP has been sent to your email.";

        } catch (Exception $e) {
            $_SESSION['dev_otp'] = $otp;
            $success = "Dev Mode Fallback: New OTP generated.";
        }

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
