<?php
session_start();
include "config.php";
if(!isset($_SESSION['change_pass_admin'])) header("Location: adminlogin.php");

$admin_id = $_SESSION['change_pass_admin'];
if(isset($_POST['change'])){
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if($new_pass !== $confirm_pass) $error="Passwords do not match!";
    else{
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin SET password=?, temp_pass=0 WHERE admin_id=?");
        $stmt->bind_param("si",$hashed,$admin_id);
        $stmt->execute();

        unset($_SESSION['change_pass_admin']);
        $_SESSION['admin_id'] = $admin_id;
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="sidebar">
    <h2>Admin Portal</h2>
    <p>Change Password</p>
</div>

<div class="main-content">
    <div class="form-container">
        <h2>Change Your Password</h2>
        <form method="post">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit" name="change">Change Password</button>
        </form>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
    </div>
</div>
</body>
</html>
