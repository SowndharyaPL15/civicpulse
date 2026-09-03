<?php
session_start();
include "config.php";

/* Protect this utility page from unauthorized access */
if(!isset($_SESSION['admin_id'])){
    die("Unauthorized access. Admin login required.");
}

// Step 1: Decide a temporary password
$tempPassword = "TempPass123"; // or use random bytes for security

// Step 2: Hash the password
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

// Step 3: Display the hashed password
echo "Temporary Password: " . htmlspecialchars($tempPassword) . "<br>";
echo "Hashed Password for DB: " . htmlspecialchars($hashedPassword);
?>
