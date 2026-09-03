<?php
session_start();
include "config.php";

// Redirect if no email in session
if(!isset($_SESSION['email'])){
    header("Location: signup.php");
    exit;
}

$email = $_SESSION['email'];

if(isset($_POST['verify'])){
    $entered_otp = trim($_POST['otp']);

    // Fetch OTP and status from DB using prepared statement
    $stmt = $conn->prepare("SELECT otp, status FROM user WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows > 0){
        $row = $res->fetch_assoc();

        if($row['status'] == 'active'){
            $info = "Your account is already verified. Please login.";
        } elseif($row['otp'] == $entered_otp){
            // Activate account
            $stmt2 = $conn->prepare("UPDATE user SET status='active', otp=NULL WHERE email=?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();

            unset($_SESSION['dev_otp']);
            session_destroy();
            $success = true;
        } else {
            $error = "Invalid OTP! Please try again.";
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
        font-size:20px;
        letter-spacing:6px;
        text-align:center;
    }
    </style>
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>OTP Verification</h2>
        <p>Enter the OTP sent to: <strong><?= htmlspecialchars($email) ?></strong></p>

        <?php if(isset($success)): ?>
        <div class="success">
            ✅ Registration successful! You can now login.
        </div>
        <p><a href="login.php">Click here to Login</a></p>

        <?php elseif(isset($info)): ?>
        <div class="success"><?= htmlspecialchars($info) ?></div>
        <p><a href="login.php">Click here to Login</a></p>

        <?php else: ?>

        <?php if(isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(isset($_SESSION['dev_otp'])): ?>
        <div class="success" style="background-color: #fff3cd; color: #856404; border-left: 5px solid #ffc107; margin-bottom:15px; padding:10px; border-radius:6px;">
            ⚠️ <strong>Dev Mode Fallback:</strong> Email delivery failed. Your OTP is: <strong><?= htmlspecialchars($_SESSION['dev_otp']) ?></strong>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="otp" class="otp-input" placeholder="------" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
            <button type="submit" name="verify">Verify OTP</button>
        </form>

        <p style="margin-top:15px;">
            Didn't receive OTP? <a href="resend_otp.php">Resend OTP</a>
        </p>

        <?php endif; ?>
    </div>
</div>
</body>
</html>
