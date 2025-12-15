<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f7f9fc; padding: 20px; color: #333;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px;">
        <h2 style="margin-top: 0; color: #111827;">Email Verification Code</h2>
        <p style="font-size: 15px; line-height: 1.5;">Your email verification code is:</p>
        <p style="font-size: 24px; font-weight: bold; letter-spacing: 2px; color: #111827; margin: 8px 0 16px;">{{ $code }}</p>
        <p style="font-size: 14px; line-height: 1.5; color: #4b5563;">This code will expire in 15 minutes.</p>

        @if(!empty($verifyUrl))
            <p style="margin: 20px 0 12px; text-align: center;">
                <a href="{{ $verifyUrl }}" style="display: inline-block; background: #2563eb; color: #ffffff; padding: 12px 18px; border-radius: 6px; text-decoration: none; font-weight: 600;">Verify Email</a>
            </p>
            <p style="font-size: 13px; color: #6b7280; word-break: break-all; text-align: center; margin-bottom: 0;">
                Or copy and paste this link: <br>
                <a href="{{ $verifyUrl }}" style="color: #2563eb; text-decoration: underline;">{{ $verifyUrl }}</a>
            </p>
        @endif

        <p style="font-size: 13px; line-height: 1.5; color: #6b7280; margin-top: 18px;">If you did not request this code, please ignore this email.</p>
    </div>
</body>
</html>

