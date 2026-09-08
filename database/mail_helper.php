<?php
/**
 * CivicPulse — Multi-Transport Mail Helper
 * Supports:
 *  1. Brevo HTTP API (HTTPS Port 443 — Guaranteed on Render/Cloud to any recipient)
 *  2. Gmail SMTP via PHPMailer (Port 587 TLS & Port 465 SSL)
 *  3. Resend HTTP API (HTTPS Port 443)
 *  4. Windows Native PowerShell Bridge (Windows fallback)
 *  5. Dev Mode Fallback (Zero delay on screen)
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
    // PRIORITY 1: Google Apps Script Webhook Relay (HTTPS Port 443 — Guaranteed Primary Inbox delivery from real Gmail)
    // -------------------------------------------------------------
    $relay_url = trim(getenv('GMAIL_RELAY_URL') ?: '');
    if (!empty($relay_url)) {
        $payload = json_encode([
            'to' => $to_email,
            'subject' => 'Your CivicPulse OTP Verification Code: ' . $otp,
            'html' => $html_body
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($relay_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 400) {
                $json = json_decode($res, true);
                if (isset($json['status']) && $json['status'] === 'success') {
                    $error_detail = null;
                    return true;
                }
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 2: SMTP2GO HTTP API (HTTPS Port 443 — Requires verified sender in SMTP2GO)
    // -------------------------------------------------------------
    $smtp2go_key = trim(getenv('SMTP2GO_API_KEY') ?: '');
    if (!empty($smtp2go_key)) {
        $smtp2go_sender = trim(getenv('SMTP2GO_SENDER') ?: (getenv('SMTP_USER') ?: '23cs108@drngpit.ac.in'));
        $payload = json_encode([
            'api_key' => $smtp2go_key,
            'to' => [$to_name ? "$to_name <$to_email>" : $to_email],
            'sender' => "$from_name <$smtp2go_sender>",
            'subject' => 'Your CivicPulse OTP Verification Code: ' . $otp,
            'html_body' => $html_body
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.smtp2go.com/v3/email/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                $json = json_decode($res, true);
                if (isset($json['data']['succeeded']) && $json['data']['succeeded'] > 0) {
                    $error_detail = null;
                    return true;
                }
            } else {
                $err_json = json_decode($res, true);
                $error_detail = "SMTP2GO API error: " . ($err_json['data']['error'] ?? $res);
                error_log($error_detail);
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 2: Brevo HTTP API (HTTPS Port 443 — Active once account is enabled)
    // -------------------------------------------------------------
    $brevo_key = trim(getenv('BREVO_API_KEY') ?: '');
    if (!empty($brevo_key)) {
        $sender_email = trim(getenv('BREVO_SENDER_EMAIL') ?: (getenv('SMTP_USER') ?: '146aqc@gmail.com'));
        $payload = json_encode([
            'sender' => ['name' => $from_name, 'email' => $sender_email],
            'to' => [['email' => $to_email, 'name' => $to_name]],
            'subject' => 'Your CivicPulse OTP Verification Code: ' . $otp,
            'htmlContent' => $html_body
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'api-key: ' . $brevo_key,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                $error_detail = null;
                return true;
            } else {
                $err_json = json_decode($res, true);
                $error_detail = "Brevo API error (HTTP $code): " . ($err_json['message'] ?? $res);
                error_log($error_detail);
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 3: Resend HTTP API (HTTPS Port 443)
    // -------------------------------------------------------------
    $resend_key = trim(getenv('RESEND_API_KEY') ?: '');
    if (!empty($resend_key)) {
        $payload = json_encode([
            'from' => getenv('MAIL_FROM') ?: 'CivicPulse <onboarding@resend.dev>',
            'to' => [$to_email],
            'subject' => 'Your CivicPulse OTP Verification Code: ' . $otp,
            'html' => $html_body
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $resend_key,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                return true;
            } else {
                $err_json = json_decode($res, true);
                $msg = $err_json['message'] ?? ($res ?: $curl_err);
                $error_detail = "Resend API error (HTTP $code): " . $msg;
                error_log($error_detail);
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 3: Direct Gmail SMTP via PHPMailer (Port 587 TLS & Port 465 SSL)
    // -------------------------------------------------------------
    if (!empty($smtp_user) && !empty($smtp_pass)) {
        // Pre-check port connectivity with 1.5s probe to avoid long hangs on Render
        $probe = @fsockopen($smtp_host, 587, $errno, $errstr, 1.5);
        $port_587_open = false;
        if ($probe) {
            $port_587_open = true;
            fclose($probe);
        }

        if ($port_587_open) {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $phpmailer_dir = dirname(__DIR__) . '/userside/PHPMailer/src/';
                if (file_exists($phpmailer_dir . 'PHPMailer.php')) {
                    require_once $phpmailer_dir . 'Exception.php';
                    require_once $phpmailer_dir . 'PHPMailer.php';
                    require_once $phpmailer_dir . 'SMTP.php';
                }
            }

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $smtp_host;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;
                    $mail->Timeout    = 5;
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
                    error_log("PHPMailer attempt failed: " . $error_detail);
                }
            }
        }
    }

    // -------------------------------------------------------------
    // PRIORITY 4: Windows Native .NET PowerShell Bridge (Fallback on Windows)
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
