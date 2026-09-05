param(
    [Parameter(Mandatory=$true)][string]$ToEmail,
    [Parameter(Mandatory=$false)][string]$ToName = "User",
    [Parameter(Mandatory=$true)][string]$Otp,
    [Parameter(Mandatory=$true)][string]$SmtpUser,
    [Parameter(Mandatory=$true)][string]$SmtpPass,
    [Parameter(Mandatory=$false)][string]$FromName = "CivicPulse"
)

try {
    $html = @"
<div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 30px; border: 1px solid #e5e7eb; border-radius: 12px; background-color: #ffffff;'>
    <h2 style='color: #2563eb; margin-top: 0;'>CivicPulse</h2>
    <p style='color: #374151; font-size: 16px;'>Hi <strong>$ToName</strong>,</p>
    <p style='color: #4b5563; font-size: 15px;'>Your OTP verification code is:</p>
    <div style='font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 8px; padding: 15px; background: #f1f5f9; border-radius: 8px; text-align: center; margin: 20px 0;'>$Otp</div>
    <p style='color: #6b7280; font-size: 14px;'>This code is valid for 15 minutes. Enter this code to verify your CivicPulse account.</p>
    <hr style='border: none; border-top: 1px solid #f3f4f6; margin: 20px 0;' />
    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>If you did not request this OTP, you can safely ignore this email.</p>
</div>
"@

    $smtp = New-Object System.Net.Mail.SmtpClient("smtp.gmail.com", 587)
    $smtp.EnableSsl = $true
    $smtp.Timeout = 15000
    $cleanPass = $SmtpPass -replace '\s',''
    $smtp.Credentials = New-Object System.Net.NetworkCredential($SmtpUser.Trim(), $cleanPass)

    $mail = New-Object System.Net.Mail.MailMessage
    $mail.From = New-Object System.Net.Mail.MailAddress($SmtpUser.Trim(), $FromName)
    $mail.To.Add($ToEmail.Trim())
    $mail.Subject = "Your CivicPulse OTP Verification Code: $Otp"
    $mail.Body = $html
    $mail.IsBodyHtml = $true

    $smtp.Send($mail)
    Write-Output "SUCCESS"
    exit 0
} catch {
    Write-Output "ERROR: $($_.Exception.Message)"
    exit 1
}
