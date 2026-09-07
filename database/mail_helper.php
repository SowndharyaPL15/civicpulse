<?php
/**
 * CivicPulse — Direct High-Performance Mail Dispatcher
 * Sends OTPs directly to EVERY registered email address via Gmail SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure .env is loaded
if (function_exists('civicpulse_load_env')) {
    civicpulse_load_env(dirname(__DIR__) . '/.env');
} elseif (file_exists(dirname(__DIR__) . '/.env')) {
    $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

function civicpulse_send_otp_email($to_email, $to_name, $otp, &$error_detail = null) {
    $to_email = trim($to_email);
    $to_name = trim($to_name ?: 'User');
    $from_name = getenv('SMTP_FROM_NAME') ?: 'CivicPulse';

    $html_body = "
    <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 12px; background-color: #ffffff;'>
        <h2 style='color: #2563eb; margin-top: 0;'>CivicPulse</h2>
        <p style='color: #374151; font-size: 16px;'>Hi <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
        <p style='color: #4b5563; font-size: 15px;'>Your OTP verification code is:</p>
        <div style='font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 8px; padding: 15px; background: #f1f5f9; border-radius: 8px; text-align: center; margin: 20px 0;'>$otp</div>
        <p style='color: #6b7280; font-size: 14px;'>This code is valid for 15 minutes. Enter this code to verify your CivicPulse account.</p>
        <hr style='border: none; border-top: 1px solid #f3f4f6; margin: 20px 0;' />
        <p style='color: #9ca3af; font-size: 12px; margin: 0;'>If you did not request this OTP, you can safely ignore this email.</p>
    </div>";

    $smtp_user = trim(getenv('SMTP_USER') ?: '');
    $smtp_pass = str_replace(' ', '', getenv('SMTP_PASS') ?: '');
    $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';

    // -------------------------------------------------------------
    // PRIORITY 1: Direct Gmail SMTP via PHPMailer (Port 587 TLS & Port 465 SSL)
    // -------------------------------------------------------------
    if (!empty($smtp_user) && !empty($smtp_pass)) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $phpmailer_dir = dirname(__DIR__) . '/userside/PHPMailer/src/';
            if (file_exists($phpmailer_dir . 'PHPMailer.php')) {
                require_once $phpmailer_dir . 'Exception.php';
                require_once $phpmailer_dir . 'PHPMailer.php';
                require_once $phpmailer_dir . 'SMTP.php';
            }
        }

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $attempts = [
                ['port' => 587, 'secure' => 'tls'],
                ['port' => 465, 'secure' => 'ssl'],
            ];

            foreach ($attempts as $att) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $smtp_host;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    $mail->SMTPSecure = $att['secure'];
                    $mail->Port       = $att['port'];
                    $mail->Timeout    = 6;
                    $mail->CharSet    = 'UTF-8';

                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                            'peer_name' => $smtp_host
                        ]
                    ];

                    $mail->setFrom($smtp_user, $from_name);
                    $mail->addAddress($to_email, $to_name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your CivicPulse OTP Verification Code: ' . $otp;
                    $mail->Body    = $html_body;
                    $mail->AltBody = "Hi $to_name,\n\nYour CivicPulse OTP verification code is: $otp\n\nValid for 15 minutes.";

                    $mail->send();
                    return true;
                } catch (Exception $e) {
                    $error_detail = $mail->ErrorInfo ?: $e->getMessage();
                    error_log("PHPMailer attempt on port {$att['port']} ({$att['secure']}) failed: " . $error_detail);
                }
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 2: Windows Native .NET PowerShell Bridge (Fallback on Windows)
    // -------------------------------------------------------------
    if (!empty($smtp_user) && !empty($smtp_pass) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ps_script = __DIR__ . DIRECTORY_SEPARATOR . 'send_smtp.ps1';
        if (file_exists($ps_script)) {
            $cmd = 'powershell.exe -ExecutionPolicy Bypass -NoProfile -File ' . escapeshellarg($ps_script) . 
                   ' -ToEmail ' . escapeshellarg($to_email) . 
                   ' -ToName ' . escapeshellarg($to_name) . 
                   ' -Otp ' . escapeshellarg($otp) . 
                   ' -SmtpUser ' . escapeshellarg($smtp_user) . 
                   ' -SmtpPass ' . escapeshellarg($smtp_pass) . 
                   ' -FromName ' . escapeshellarg($from_name) . ' 2>&1';
            $output = @shell_exec($cmd);
            if ($output && strpos($output, 'SUCCESS') !== false) {
                return true;
            }
        }
    }

    return false;
}
