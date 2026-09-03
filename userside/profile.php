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

    <!-- Premium Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: 'Inter', sans-serif;
            color: #111827;
        }

        .profile-card {
            background: #fff;
            border-radius: 20px;
            max-width: 720px;
            margin: 60px auto;
            padding: 0;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Header Section */
        .profile-header {
            padding: 30px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            text-align: center;
        }

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            color: #2563eb;
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }

        .profile-header h3 {
            margin: 5px 0;
            font-weight: 600;
        }

        .profile-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Content */
        .profile-body {
            padding: 30px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .info-label {
            color: #6b7280;
            font-size: 14px;
        }

        .info-value {
            font-weight: 500;
            color: #111;
        }

        .status-badge {
            background: #dcfce7;
            color: #166534;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }

        /* Buttons */
        .profile-actions {
            padding: 20px 30px 30px;
        }

        .btn-modern {
            border-radius: 12px;
            padding: 10px;
            font-weight: 500;
            transition: all 0.25s ease;
        }

        .btn-primary {
            background: #2563eb;
            border: none;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #facc15;
            border: none;
            color: #000;
        }

        .btn-warning:hover {
            background: #eab308;
            transform: translateY(-1px);
        }

        .btn-dark {
            background: #111827;
            border: none;
        }

        .btn-dark:hover {
            background: #000;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>

<div class="profile-card">

    <!-- HEADER -->
    <div class="profile-header">
        <div class="avatar">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <h3><?= htmlspecialchars($user['name']) ?></h3>
        <p><?= htmlspecialchars($user['email']) ?></p>
    </div>

    <!-- BODY -->
    <div class="profile-body">

        <div class="info-row">
            <span class="info-label">Mobile Number</span>
            <span class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not Provided') ?></span>
        </div>

        <div class="info-row">
            <span class="info-label">Address</span>
            <span class="info-value"><?= htmlspecialchars($user['address'] ?? 'Not Provided') ?></span>
        </div>

        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="status-badge"><?= htmlspecialchars($user['status']) ?></span>
        </div>

    </div>

    <!-- ACTIONS -->
    <div class="profile-actions d-grid gap-2">
        <a href="edit_profile.php" class="btn btn-primary btn-modern">Edit Profile</a>
        <a href="change_password.php" class="btn btn-warning btn-modern">Change Password</a>
        <a href="home.php" class="btn btn-dark btn-modern">Back</a>
    </div>

</div>

</body>
</html>