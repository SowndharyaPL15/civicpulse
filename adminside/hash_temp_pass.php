<?php
// Step 1: Decide a temporary password
$tempPassword = "admin123"; // or use random bytes for security

// Step 2: Hash the password
$hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

// Step 3: Display the hashed password
echo "Temporary Password: " . $tempPassword . "<br>";
echo "Hashed Password for DB: " . $hashedPassword;
?>
