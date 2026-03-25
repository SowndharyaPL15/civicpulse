<?php
session_start();
include "config.php"; // your DB connection

// Redirect if no email in session
if(!isset($_SESSION['email'])){
    header("Location: signup.php");
    exit;
}

$email = $_SESSION['email'];

if(isset($_POST['verify'])){
    $entered_otp = trim(mysqli_real_escape_string($conn, $_POST['otp']));

    // Fetch OTP and status from DB
    $res = mysqli_query($conn, "SELECT otp, status FROM user WHERE email='$email'");
    if(mysqli_num_rows($res) > 0){
        $row = mysqli_fetch_assoc($res);

        if($row['status'] == 'active'){
            echo "<script>alert('Your account is already verified. Please login.');window.location='login.php';</script>";
            session_destroy();
            exit;
        }

        // Compare OTP
        if($row['otp'] == $entered_otp){
            mysqli_query($conn, "UPDATE user SET status='active', otp=NULL WHERE email='$email'");
            echo "<script>alert('Registration successful! You can now login.');window.location='login.php';</script>";
            session_destroy();
            exit;
        } else {
            echo "<script>alert('Invalid OTP! Please try again.');</script>";
        }
    } else {
        echo "<script>alert('User not found. Please register again.');window.location='signup.php';</script>";
        session_destroy();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - CivicPulse</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>OTP Verification</h2>
        <p>Enter the OTP sent to your email: <b><?php echo $email; ?></b></p>
        <form method="POST">
            <input type="text" name="otp" placeholder="Enter OTP" required maxlength="6"><br><br>
            <button type="submit" name="verify">Verify</button>
        </form>
        <p>Didn't receive OTP? <a href="resend_otp.php">Resend OTP</a></p>
    </div>
</div>
</body>
</html>
