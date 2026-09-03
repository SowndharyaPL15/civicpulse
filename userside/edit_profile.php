<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch existing data
$stmt = $conn->prepare("SELECT name, email, phone, address FROM user WHERE uid=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Update logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $update = $conn->prepare("UPDATE user SET name=?, email=?, phone=?, address=? WHERE uid=?");
    $update->bind_param("ssssi", $name, $email, $phone, $address, $user_id);

    if ($update->execute()) {
        $success = "Profile updated successfully!";
    } else {
        $error = "Something went wrong!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Elegant Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f8f9fb;
            font-family: 'Poppins', sans-serif;
            color: #111;
        }

        .card {
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        h2 {
            font-weight: 600;
            color: #0d1b2a;
        }

        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            padding: 10px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.15rem rgba(37, 99, 235, 0.2);
        }

        textarea.form-control {
            min-height: 100px;
        }

        .btn-primary {
            background: #2563eb;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-weight: 500;
            transition: 0.3s;
        }

        .btn-primary:hover {
            background: #1e4fd1;
        }

        .btn-secondary {
            background: #f1f3f5;
            color: #333;
            border: none;
            border-radius: 10px;
            padding: 10px;
        }

        .btn-secondary:hover {
            background: #e2e6ea;
        }

        .alert {
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <h2 class="text-center mb-4">✏ Edit Profile</h2>

        <?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Mobile Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control"><?= htmlspecialchars($user['address']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">Update Profile</button>
            <a href="profile.php" class="btn btn-secondary w-100 mt-2">Back</a>
        </form>
    </div>
</div>

</body>
</html>