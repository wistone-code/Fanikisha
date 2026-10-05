<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1B2429;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
        <h2 style="margin:0 0 12px;">Reset your password</h2>
        <p style="font-size:14px;line-height:1.5;">Hello {{ $name }}, use this code to reset your {{ $appName }} password:</p>
        <p style="text-align:center;font-size:32px;letter-spacing:8px;font-family:monospace;font-weight:bold;margin:20px 0;">{{ $code }}</p>
        <p style="font-size:12px;color:#6b7280;line-height:1.5;">It expires in {{ $minutes }} minutes. If you didn't request this, you can ignore this email — your password won't change.</p>
    </div>
</body>
</html>
