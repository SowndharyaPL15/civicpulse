<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT name, email, phone, address, status
    FROM `user`
    WHERE uid=?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Citizen Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(to right, #667eea, #764ba2);
        }
        .profile-card {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            max-width: 650px;
            margin: auto;
            margin-top: 60px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
    </style>
</head>

<body>

<div class="profile-card">
    <h2 class="text-center mb-4">👤 Citizen Profile</h2>

    <table class="table table-bordered">
        <tr>
            <th>Full Name</th>
            <td><?= htmlspecialchars($user['name']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= htmlspecialchars($user['email']) ?></td>
        </tr>
        <tr>
            <th>Mobile Number</th>
            <td><?= htmlspecialchars($user['phone'] ?? 'Not Provided') ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td><?= htmlspecialchars($user['address'] ?? 'Not Provided') ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="badge bg-success"><?= htmlspecialchars($user['status']) ?></span></td>
        </tr>
    </table>

    <div class="d-grid gap-2">
        <a href="edit_profile.php" class="btn btn-primary">✏ Edit Profile</a>
        <a href="change_password.php" class="btn btn-warning">🔐 Change Password</a>
        <a href="home.php" class="btn btn-dark">⬅ Back</a>
    </div>
</div>

</body>
</html>