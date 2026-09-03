<?php
session_start();
include "config.php";

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';
require_once __DIR__ . '/../database/mail_helper.php';

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
            $mail_error = null;
            $sent = civicpulse_send_otp_email($email, $name, $otp, $mail_error);

            $_SESSION['email'] = $email;
            if (!$sent) {
                $_SESSION['dev_otp'] = $otp;
                if (!empty($mail_error)) {
                    $_SESSION['mail_error'] = $mail_error;
                }
            } else {
                unset($_SESSION['dev_otp']);
                unset($_SESSION['mail_error']);
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
