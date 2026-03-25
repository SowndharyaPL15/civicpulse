<?php
session_start();
include "config.php";

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kolkata');

if(isset($_POST['login'])){

$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT * FROM admin WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if($admin && password_verify($password,$admin['password'])){

if($admin['active']==0){

$otp = rand(100000,999999);
$expiry = date('Y-m-d H:i:s',strtotime('+15 minutes'));

$stmt2=$conn->prepare("UPDATE admin SET otp=?, otp_expiry=? WHERE admin_id=?");
$stmt2->bind_param("ssi",$otp,$expiry,$admin['admin_id']);
$stmt2->execute();

$mail = new PHPMailer(true);

try{

$mail->isSMTP();
$mail->Host='smtp.gmail.com';
$mail->SMTPAuth=true;
$mail->Username='justforfunleomeenu@gmail.com';
$mail->Password='jwkvyxtjqyaiaovm';
$mail->SMTPSecure='tls';
$mail->Port=587;

$mail->setFrom('justforfunleomeenu@gmail.com','CivicPulse Admin');
$mail->addAddress($admin['email']);

$mail->isHTML(true);
$mail->Subject='Admin OTP Verification';
$mail->Body="Your OTP is: <b>$otp</b>. It expires in 15 minutes.";

$mail->send();

}catch(Exception $e){
die("OTP could not be sent. Mailer Error: {$mail->ErrorInfo}");
}

$_SESSION['otp_admin_id']=$admin['admin_id'];

header("Location: admin_otp.php");
exit;

}

else if($admin['temp_pass']==1){

$_SESSION['change_pass_admin']=$admin['admin_id'];

header("Location: change_pass.php");
exit;

}

else{

$_SESSION['admin_id']=$admin['admin_id'];
$_SESSION['department_id']=$admin['dept_id'];
$_SESSION['admin_email']=$admin['email'];

header("Location: dashboard.php");
exit;

}

}else{
$error="Invalid email or password!";
}

}
?>

<!DOCTYPE html>
<html>
<head>

<title>CivicPulse Admin Login</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
height:100vh;
background:linear-gradient(135deg,#2c3e50,#4ca1af);
display:flex;
align-items:center;
justify-content:center;
font-family:Segoe UI;
}

.login-container{
width:900px;
background:white;
border-radius:12px;
overflow:hidden;
box-shadow:0 20px 40px rgba(0,0,0,0.2);
}

.left-panel{
background:#2c3e50;
color:white;
padding:60px;
display:flex;
flex-direction:column;
justify-content:center;
}

.left-panel h2{
font-weight:700;
}

.right-panel{
padding:60px;
}

.form-control{
padding:12px;
}

.login-btn{
padding:12px;
font-weight:600;
}

.brand{
font-size:28px;
font-weight:bold;
margin-bottom:10px;
}

.subtitle{
opacity:0.8;
}

</style>

</head>

<body>

<div class="login-container row g-0">

<div class="col-md-6 left-panel">

<div>

<div class="brand">CivicPulse</div>

<h2>Admin Portal</h2>

<p class="subtitle">
Manage civic complaints, assign workers, and monitor infrastructure issues efficiently.
</p>

</div>

</div>

<div class="col-md-6 right-panel">

<h3 class="mb-4">Admin Login</h3>

<form method="post">

<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<button type="submit" name="login" class="btn btn-primary w-100 login-btn">
Login
</button>

</form>

<?php
if(isset($error)){
echo "<div class='alert alert-danger mt-3'>$error</div>";
}
?>

</div>

</div>

</body>
</html>