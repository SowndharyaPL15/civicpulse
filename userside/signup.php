<?php
session_start();
include "config.php"; // your DB connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

if(isset($_POST['register'])){
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Generate OTP & activation code
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6-digit OTP
    $activation_code = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 12);

    // Check if email exists
    $check = mysqli_query($conn, "SELECT * FROM user WHERE email='$email'");
    if(mysqli_num_rows($check) > 0){
        $row = mysqli_fetch_assoc($check);
        if($row['status'] == 'active'){
            echo "<script>alert('Email already registered');
            window.location.href='login.php';</script>";
            
            exit;

        } else {
            mysqli_query($conn, "UPDATE user SET name='$name', password='$password', otp='$otp', activation_code='$activation_code', status='inactive' WHERE email='$email'");
        }
    } else {
        mysqli_query($conn, "INSERT INTO user (name,email,password,otp,activation_code,status) VALUES ('$name','$email','$password','$otp','$activation_code','inactive')");
        
    }

    // Send OTP email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'justforfunleomeenu@gmail.com'; // your Gmail
        $mail->Password   = 'jwkvyxtjqyaiaovm';    // Gmail app password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('justforfunleomeenu@gmail.com', 'CivicPulse');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Verification Code';
        $mail->Body    = "Hi $name,<br>Your OTP code is <b>$otp</b>.<br>Activation Code: $activation_code";

        $mail->send();

        // Store email in session for OTP verification
        $_SESSION['email'] = $email;

        // Temporary debug: show OTP
        // echo "OTP sent: $otp"; exit;

        header("Location: verify_otp.php");
        exit;
    } catch (Exception $e) {
        echo "<script>alert('OTP could not be sent. Mailer Error: {$mail->ErrorInfo}');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CivicPulse Registration</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>Sign Up</h2>
        <form method="POST" id="signupForm">
            <input type="text" name="name" placeholder="Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Register</button>
        </form>
        <p>
            Already registered? <a href="login.php">Login here</a>
        </p>
    </div>
</div>
</body>
</html>
