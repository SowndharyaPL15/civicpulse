<?php
session_start();
include "config.php";
if(!isset($_SESSION['otp_admin_id'])) header("Location: adminlogin.php");

$admin_id = $_SESSION['otp_admin_id'];
if(isset($_POST['verify'])){
    $input_otp = $_POST['otp'];
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id=? AND otp=? AND otp_expiry > NOW()");
    $stmt->bind_param("is",$admin_id,$input_otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $stmt2 = $conn->prepare("UPDATE admin SET active=1, otp=NULL, otp_expiry=NULL WHERE admin_id=?");
        $stmt2->bind_param("i",$admin_id);
        $stmt2->execute();

        unset($_SESSION['otp_admin_id']);
        $_SESSION['change_pass_admin'] = $admin_id;
        header("Location: change_pass.php");
        exit;
    } else $error="Invalid or expired OTP!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="sidebar">
    <h2>Admin Portal</h2>
    <p>OTP Verification</p>
</div>

<div class="main-content">
    <div class="form-container">
        <h2>Enter OTP</h2>
        <form method="post">
            <input type="text" name="otp" placeholder="6-digit OTP" required>
            <button type="submit" name="verify">Verify OTP</button>
        </form>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
    </div>
</div>
</body>
</html>
