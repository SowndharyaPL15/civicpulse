<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Fetch current password
    $stmt = $conn->prepare("SELECT password FROM user WHERE uid=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect!";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match!";
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE user SET password=? WHERE uid=?");
        $update->bind_param("si", $hashed, $user_id);

        if ($update->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Error updating password!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Premium Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: 'Inter', sans-serif;
            color: #111827;
        }

        .password-card {
            max-width: 480px;
            margin: 70px auto;
            border-radius: 20px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        }

        /* Header */
        .card-header-custom {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header-custom h3 {
            margin: 0;
            font-weight: 600;
        }

        .card-header-custom p {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Body */
        .card-body-custom {
            padding: 30px;
        }

        .form-label {
            font-size: 14px;
            color: #6b7280;
        }

        .form-control {
            border-radius: 12px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.15rem rgba(37, 99, 235, 0.2);
        }

        /* Password toggle */
        .input-group-text {
            background: #fff;
            border-radius: 0 12px 12px 0;
            cursor: pointer;
        }

        /* Buttons */
        .btn-primary {
            background: #2563eb;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-weight: 500;
            transition: 0.25s;
        }

        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .btn-secondary {
            border-radius: 12px;
            padding: 10px;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="password-card">

    <!-- HEADER -->
    <div class="card-header-custom">
        <h3>Change Password</h3>
        <p>Keep your account secure</p>
    </div>

    <!-- BODY -->
    <div class="card-body-custom">

        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <form method="POST">

            <!-- Current Password -->
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <div class="input-group">
                    <input type="password" id="current" name="current_password" class="form-control" required>
                    <span class="input-group-text" onclick="toggle('current')">👁</span>
                </div>
            </div>

            <!-- New Password -->
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" id="new" name="new_password" class="form-control" required>
                    <span class="input-group-text" onclick="toggle('new')">👁</span>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <input type="password" id="confirm" name="confirm_password" class="form-control" required>
                    <span class="input-group-text" onclick="toggle('confirm')">👁</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Update Password</button>
            <a href="profile.php" class="btn btn-secondary w-100 mt-2">Back</a>

        </form>
    </div>

</div>

<!-- JS for toggle -->
<script>
function toggle(id) {
    let input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}
</script>

</body>
</html>