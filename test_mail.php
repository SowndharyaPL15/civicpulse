<?php
/**
 * CivicPulse — Real-Time Mail Diagnostic & Test Tool
 * Open this in your browser: http://localhost/Civicpulse/test_mail.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/database/db_connect.php';
require_once __DIR__ . '/database/mail_helper.php';

$test_email = $_GET['email'] ?? 'dhanasrikumarpl@gmail.com';
$test_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$error = null;
$result = null;

if (isset($_POST['send_test'])) {
    $test_email = trim($_POST['email']);
    $result = civicpulse_send_otp_email($test_email, 'Test User', $test_otp, $error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CivicPulse Mail Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; background: #f8fafc; color: #1e293b; }
        .box { max-width: 650px; margin: auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h2 { color: #2563eb; margin-top: 0; }
        .item { padding: 10px; margin-bottom: 8px; border-radius: 6px; background: #f1f5f9; font-size: 14px; }
        .success { background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; font-weight: bold; margin: 15px 0; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin: 15px 0; }
        input, button { padding: 10px 14px; font-size: 15px; border-radius: 6px; border: 1px solid #cbd5e1; }
        button { background: #2563eb; color: white; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #1d4ed8; }
        pre { background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<div class="box">
    <h2>🧪 CivicPulse Mail Diagnostics</h2>
    
    <h3>1. PHP Environment Status:</h3>
    <div class="item"><strong>OpenSSL Extension:</strong> <?= extension_loaded('openssl') ? '✅ Enabled' : '❌ DISABLED (Please enable in php.ini)' ?></div>
    <div class="item"><strong>cURL Extension:</strong> <?= extension_loaded('curl') ? '✅ Enabled' : '❌ DISABLED (Please enable in php.ini)' ?></div>
    <div class="item"><strong>Sockets Extension:</strong> <?= extension_loaded('sockets') ? '✅ Enabled' : '⚠️ Disabled (optional)' ?></div>
    <div class="item"><strong>RESEND_API_KEY:</strong> <?= !empty(getenv('RESEND_API_KEY')) ? '✅ Configured (' . substr(getenv('RESEND_API_KEY'), 0, 10) . '...)' : '❌ Not Set' ?></div>
    <div class="item"><strong>SMTP_USER:</strong> <?= htmlspecialchars(getenv('SMTP_USER') ?: '❌ Not Set') ?></div>
    <div class="item"><strong>SMTP_PASS:</strong> <?= !empty(getenv('SMTP_PASS')) ? '✅ Set (' . strlen(getenv('SMTP_PASS')) . ' chars)' : '❌ Not Set' ?></div>

    <hr style="margin: 20px 0; border: none; border-top: 1px solid #e2e8f0;">

    <h3>2. Test Live OTP Dispatch:</h3>
    <form method="POST">
        <div style="margin-bottom: 15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">Recipient Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($test_email) ?>" style="width: 100%; box-sizing: border-box;" required>
        </div>
        <button type="submit" name="send_test">🚀 Send Test OTP Now</button>
    </form>

    <?php if ($result === true): ?>
        <div class="success">
            🎉 SUCCESS! OTP email sent successfully to <?= htmlspecialchars($test_email) ?>!
            <br><small>Generated OTP: <strong><?= $test_otp ?></strong></small>
        </div>
    <?php elseif ($result === false): ?>
        <div class="error">
            ❌ FAILED: <?= htmlspecialchars($error ?: 'Unknown error') ?>
        </div>
    <?php endif; ?>

    <p style="margin-top:20px; font-size:13px; color:#64748b;">
        <a href="userside/signup.php">← Back to Signup</a> &bull; <a href="userside/verify_otp.php">Verify OTP</a>
    </p>
</div>
</body>
</html>
