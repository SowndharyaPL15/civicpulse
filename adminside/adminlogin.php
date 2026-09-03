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
$expiry = date('Y-m-d H:i:s',strtotime('+30 minutes'));

$stmt2=$conn->prepare("UPDATE admin SET otp=?, otp_expiry=? WHERE admin_id=?");
$stmt2->bind_param("ssi",$otp,$expiry,$admin['admin_id']);
$stmt2->execute();

$smtp_user = trim(getenv('SMTP_USER') ?: '');
$smtp_pass = str_replace(' ', '', getenv('SMTP_PASS') ?: '');
$sent = false;

if(!empty($smtp_user) && !empty($smtp_pass)){
    $mail = new PHPMailer(true);
    try{
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
        $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $smtp_from_name = getenv('SMTP_FROM_NAME') ?: 'CivicPulse Admin';
        $mail->setFrom($smtp_user, $smtp_from_name);
        $mail->addAddress($admin['email']);

        $mail->isHTML(true);
        $mail->Subject='Admin OTP Verification — CivicPulse';
        $mail->Body="Your OTP is: <b>$otp</b>. It expires in 30 minutes.";

        $mail->send();
        $sent = true;
    }catch(Exception $e){
        error_log("Admin PHPMailer error: " . $e->getMessage());
    }
}

$_SESSION['otp_admin_id']=$admin['admin_id'];
if(!$sent){
    $_SESSION['admin_dev_otp'] = $otp;
} else {
    unset($_SESSION['admin_dev_otp']);
}
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
height:100vh;
background:linear-gradient(135deg,#0f172a,#1e3a5f);
display:flex;
align-items:center;
justify-content:center;
font-family:'Inter',sans-serif;
margin:0;
}

.login-container{
width:900px;
max-width:95vw;
background:white;
border-radius:16px;
overflow:hidden;
box-shadow:0 25px 50px rgba(0,0,0,0.25);
}

.left-panel{
background:linear-gradient(135deg,#0f172a,#1e3a5f);
color:white;
padding:60px 40px;
display:flex;
flex-direction:column;
justify-content:center;
}

.left-panel h2{
font-weight:700;
}

.right-panel{
padding:60px 40px;
}

.form-control{
padding:12px;
border-radius:10px;
border:1px solid #e5e7eb;
transition:0.2s;
}

.form-control:focus{
border-color:#2563eb;
box-shadow:0 0 0 3px rgba(37,99,235,0.1);
}

.login-btn{
padding:12px;
font-weight:600;
border-radius:10px;
background:#2563eb;
border:none;
transition:0.2s;
}

.login-btn:hover{
background:#1e40af;
transform:translateY(-1px);
}

.brand{
font-size:28px;
font-weight:bold;
margin-bottom:10px;
background:linear-gradient(90deg,#60a5fa,#34d399);
-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.subtitle{
opacity:0.85;
line-height:1.6;
}

@media(max-width:768px){
.left-panel{display:none;}
.right-panel{padding:40px 25px;}
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
<input type="email" name="email" class="form-control" placeholder="admin@example.com" required>
</div>

<div class="mb-3">
<label class="form-label">Password</label>
<input type="password" name="password" class="form-control" placeholder="Enter password" required>
</div>

<button type="submit" name="login" class="btn btn-primary w-100 login-btn">
Login
</button>

</form>

<?php
if(isset($error)){
echo "<div class='alert alert-danger mt-3'>" . htmlspecialchars($error) . "</div>";
}
?>

</div>

</div>

</body>
</html>