<?php
/**
 * CivicPulse — Bulletproof Mail Helper
 * Supports:
 *  1. Resend API (HTTPS Port 443 — Guaranteed on Render/Cloud with RESEND_API_KEY)
 *  2. Brevo API (HTTPS Port 443 — with BREVO_API_KEY)
 *  3. Gmail / Custom SMTP (PHPMailer over TLS 587 & SSL 465)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    // -------------------------------------------------------------
    // OPTION 1: Resend HTTP API (HTTPS Port 443 — Never blocked by Cloud)
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
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($code >= 200 && $code < 300) {
                return true;
            } else {
                $error_detail = "Resend API error (HTTP $code): " . ($res ?: $curl_err);
                error_log($error_detail);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Authorization: Bearer {$resend_key}\r\nContent-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 15,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $res = @file_get_contents('https://api.resend.com/emails', false, $context);
            if ($res !== false) {
                $json = json_decode($res, true);
                if (isset($json['id'])) {
                    return true;
                } else {
                    $error_detail = "Resend API response: " . $res;
                }
            } else {
                $error_detail = "Resend HTTP request failed.";
            }
        }
    }

    // -------------------------------------------------------------
    // OPTION 2: Brevo HTTP API (HTTPS Port 443)
    // -------------------------------------------------------------
    $brevo_key = trim(getenv('BREVO_API_KEY') ?: '');
    if (!empty($brevo_key)) {
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $brevo_key,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'sender' => ['name' => $from_name, 'email' => getenv('SMTP_USER') ?: 'admin@civicpulse.org'],
                'to' => [['email' => $to_email, 'name' => $to_name]],
                'subject' => 'Your CivicPulse OTP Verification Code: ' . $otp,
                'htmlContent' => $html_body
            ]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return true;
        } else {
            $error_detail = "Brevo API error (HTTP $code): " . $res;
            error_log($error_detail);
        }
    }

    // -------------------------------------------------------------
    // OPTION 3: Gmail SMTP / Custom SMTP via PHPMailer
    // -------------------------------------------------------------
    $smtp_user = trim(getenv('SMTP_USER') ?: '');
    $smtp_pass = str_replace(' ', '', getenv('SMTP_PASS') ?: '');

    if (empty($smtp_user) || empty($smtp_pass)) {
        $error_detail = "No SMTP credentials or API key configured.";
        return false;
    }

    $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';

    $configs = [
        ['port' => 465, 'secure' => 'ssl'],
        ['port' => 587, 'secure' => 'tls'],
        ['port' => 2525, 'secure' => 'tls'],
    ];

    foreach ($configs as $cfg) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = $cfg['secure'];
            $mail->Port       = $cfg['port'];
            $mail->Timeout    = 8;
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
            error_log("SMTP attempt failed on port {$cfg['port']} ({$cfg['secure']}): " . $error_detail);
        }
    }

    return false;
}
