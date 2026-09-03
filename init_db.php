<?php
/**
 * CivicPulse — Database Auto-Initializer & Migrator
 * Automatically creates tables and seeds default data on first container startup or PaaS deploy.
 */

echo "[CivicPulse DB Init] Starting database check...\n";

require_once __DIR__ . '/database/db_connect.php';

$params = civicpulse_get_db_params();
$db_name = $params['name'];

// 1. Check if core tables already exist
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'user'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    echo "[CivicPulse DB Init] Database '$db_name' is already initialized (found 'user' table).\n";
} else {
    echo "[CivicPulse DB Init] Database '$db_name' is empty. Running migrations from database/civicpulse.sql...\n";
    
    $sql_file = __DIR__ . '/database/civicpulse.sql';
    if (!file_exists($sql_file)) {
        die("[CivicPulse DB Init] ERROR: Schema file '$sql_file' not found.\n");
    }

    $sql_content = file_get_contents($sql_file);

    // Clean out fixed database creation/use statements so it adapts to cloud database names (e.g. railway, render)
    $sql_content = preg_replace('/CREATE\s+DATABASE[^\n;]*;/i', '', $sql_content);
    $sql_content = preg_replace('/USE\s+`?[^`\s;]+`?;/i', '', $sql_content);

    // Execute queries using mysqli_multi_query
    if (mysqli_multi_query($conn, $sql_content)) {
        do {
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
        echo "[CivicPulse DB Init] Schema migration completed successfully.\n";
    } else {
        echo "[CivicPulse DB Init] Migration error: " . mysqli_error($conn) . "\n";
    }
}

// 2. Ensure default admin exists
$admin_check = mysqli_query($conn, "SELECT admin_id FROM admin LIMIT 1");
if ($admin_check && mysqli_num_rows($admin_check) === 0) {
    echo "[CivicPulse DB Init] No admin account found. Creating default admin...\n";

    $admin_name = getenv('ADMIN_NAME') ?: 'Super Admin';
    $admin_email = getenv('ADMIN_EMAIL') ?: 'admin@civicpulse.com';
    $admin_pass_raw = getenv('ADMIN_PASSWORD') ?: 'password';
    $admin_pass_hash = password_hash($admin_pass_raw, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO admin (name, email, password, dept_id, active, temp_pass) VALUES (?, ?, ?, 1, 1, 0)");
    if ($stmt) {
        $stmt->bind_param("sss", $admin_name, $admin_email, $admin_pass_hash);
        $stmt->execute();
        echo "[CivicPulse DB Init] Default admin created:\n";
        echo "  - Email:    $admin_email\n";
        echo "  - Password: $admin_pass_raw\n";
        echo "  - Note: Change your password upon first login.\n";
    } else {
        echo "[CivicPulse DB Init] Warning: Could not create admin: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "[CivicPulse DB Init] Admin account(s) already exist.\n";
}

// 3. Ensure upload directory exists
$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "[CivicPulse DB Init] Created uploads directory: $upload_dir\n";
}

echo "[CivicPulse DB Init] Database initialization complete!\n";
