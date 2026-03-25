<?php
session_start();
include "config.php";

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT uid, name, email, password, status FROM user WHERE email = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = "Account not active. Please verify OTP.";
            } elseif (password_verify($password, $user['password'])) {
                // Correct session variable
                $_SESSION['user_id'] = $user['uid'];
                $_SESSION['user_name'] = $user['name'];

                // Redirect to home.php
                header("Location: home.php");
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Citizen Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <div class="form-box">
        <h2>Citizen Login</h2>

        <?php if (!empty($error)) echo '<p style="color:red;">'.$error.'</p>'; ?>

        <form method="post" action="">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <p>
            New user? <a href="signup.php">Create an account</a>
        </p>
    </div>
</div>
</body>
</html>
