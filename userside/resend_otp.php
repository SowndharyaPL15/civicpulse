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

// Generate new 6-digit OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Update OTP in database
$stmt = $conn->prepare("UPDATE user SET otp=? WHERE email=?");
$stmt->bind_param("ss", $otp, $email);
$stmt->execute();

// Fetch user name
$stmt2 = $conn->prepare("SELECT name FROM user WHERE email=?");
$stmt2->bind_param("s", $email);
$stmt2->execute();
$user_res = $stmt2->get_result();
$user = $user_res ? $user_res->fetch_assoc() : null;
$name = $user['name'] ?? 'User';

$mail_error = null;
$sent = civicpulse_send_otp_email($email, $name, $otp, $mail_error);

if (!$sent) {
    $_SESSION['dev_otp'] = $otp;
    if (!empty($mail_error)) {
        $_SESSION['mail_error'] = $mail_error;
    }
} else {
    unset($_SESSION['dev_otp']);
    unset($_SESSION['mail_error']);
    $_SESSION['resend_success'] = "A new OTP has been sent to " . htmlspecialchars($email) . "! Please check your inbox.";
}

header("Location: verify_otp.php");
exit;
