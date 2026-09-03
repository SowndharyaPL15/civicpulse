<?php
session_start();
include "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

if(isset($_POST['register'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $raw_password = $_POST['password'];

    /* Validation */
    if(empty($name) || empty($email) || empty($raw_password)){
        $error = "All fields are required.";
    } elseif(strlen($raw_password) < 6){
        $error = "Password must be at least 6 characters.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Invalid email address.";
    } else {

        $password = password_hash($raw_password, PASSWORD_DEFAULT);

        // Generate 6-digit OTP & activation code
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $activation_code = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 12);

        // Check if email exists using prepared statement
        $stmt = $conn->prepare("SELECT uid, status FROM user WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $check = $stmt->get_result();

        if($check->num_rows > 0){
            $row = $check->fetch_assoc();
            if($row['status'] == 'active'){
                $error = "Email already registered. Please login.";
            } else {
                $stmt = $conn->prepare("UPDATE user SET name=?, password=?, otp=?, activation_code=?, status='inactive' WHERE email=?");
                $stmt->bind_param("sssss", $name, $password, $otp, $activation_code, $email);
                $stmt->execute();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO user (name,email,password,otp,activation_code,status) VALUES (?,?,?,?,?,'inactive')");
            $stmt->bind_param("sssss", $name, $email, $password, $otp, $activation_code);
            $stmt->execute();
        }

        if(!isset($error)){
            $smtp_user = trim(getenv('SMTP_USER') ?: '');
            $smtp_pass = str_replace(' ', '', getenv('SMTP_PASS') ?: '');
            $sent = false;

            if(!empty($smtp_user) && !empty($smtp_pass)){
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
                    $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];

                    $smtp_from_name = getenv('SMTP_FROM_NAME') ?: 'CivicPulse';
                    $mail->setFrom($smtp_user, $smtp_from_name);
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your CivicPulse OTP Verification Code';
                    $mail->Body    = "
                    <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;padding:30px;border:1px solid #e5e7eb;border-radius:12px;'>
                    <h2 style='color:#2563eb;'>CivicPulse</h2>
                    <p>Hi <strong>" . htmlspecialchars($name) . "</strong>,</p>
                    <p>Your OTP verification code is:</p>
                    <div style='font-size:32px;font-weight:bold;color:#2563eb;letter-spacing:8px;padding:15px;background:#f1f5f9;border-radius:8px;text-align:center;'>$otp</div>
                    <p style='margin-top:15px;color:#6b7280;'>This code will be used to verify your account.</p>
                    <p style='color:#9ca3af;font-size:12px;'>If you didn't request this, please ignore this email.</p>
                    </div>";

                    $mail->send();
                    $sent = true;
                } catch (Exception $e) {
                    error_log("PHPMailer error: " . $e->getMessage());
                }
            }

            $_SESSION['email'] = $email;
            if (!$sent) {
                $_SESSION['dev_otp'] = $otp;
            } else {
                unset($_SESSION['dev_otp']);
            }
            header("Location: verify_otp.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — CivicPulse</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>Sign Up</h2>

        <?php if(isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="signupForm">
            <input type="text" name="name" placeholder="Full Name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            <input type="email" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="password" placeholder="Password (min 6 chars)" required minlength="6">
            <button type="submit" name="register">Create Account</button>
        </form>
        <p>
            Already registered? <a href="login.php">Login here</a>
        </p>
    </div>
</div>
</body>
</html>
