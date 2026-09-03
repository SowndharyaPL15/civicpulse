<?php
/**
 * CivicPulse — Bulletproof Mail Helper
 * Automatically handles Gmail SMTP over TLS (587) with SSL (465) fallback and error logging.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function civicpulse_send_otp_email($to_email, $to_name, $otp, &$error_detail = null) {
    $smtp_user = trim(getenv('SMTP_USER') ?: '');
    $smtp_pass = str_replace(' ', '', getenv('SMTP_PASS') ?: '');

    if (empty($smtp_user) || empty($smtp_pass)) {
        $error_detail = "SMTP_USER or SMTP_PASS environment variable is not configured in Render.";
        return false;
    }

    $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $from_name = getenv('SMTP_FROM_NAME') ?: 'CivicPulse';

    $ports_to_try = [
        ['port' => (int)(getenv('SMTP_PORT') ?: 587), 'secure' => getenv('SMTP_SECURE') ?: 'tls'],
        ['port' => 465, 'secure' => 'ssl'],
        ['port' => 587, 'secure' => 'tls'],
    ];

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

    foreach ($ports_to_try as $cfg) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = $cfg['secure'];
            $mail->Port       = $cfg['port'];
            $mail->Timeout    = 10;
            $mail->CharSet    = 'UTF-8';

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $mail->setFrom($smtp_user, $from_name);
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML(true);
            $mail->Subject = 'Your CivicPulse OTP Verification Code';
            $mail->Body    = $html_body;
            $mail->AltBody = "Your CivicPulse OTP verification code is: $otp";

            $mail->send();
            return true;
        } catch (Exception $e) {
            $error_detail = $mail->ErrorInfo ?: $e->getMessage();
            error_log("PHPMailer attempt failed on port {$cfg['port']} ({$cfg['secure']}): " . $error_detail);
        }
    }

    return false;
}
