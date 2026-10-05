<!DOCTYPE html>
<html>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1B2429;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
        <h2 style="margin:0 0 12px;">Welcome to {{ $appName }}</h2>
        <p style="font-size:14px;line-height:1.5;">Hello {{ $name }}, an account has been created for you. Use the details below to sign in.</p>
        <table style="width:100%;font-size:14px;background:#f4f5f7;border-radius:8px;padding:12px;margin:16px 0;">
            <tr><td style="color:#6b7280;padding:4px 0;">Username</td><td style="text-align:right;"><strong>{{ $username }}</strong></td></tr>
            <tr><td style="color:#6b7280;padding:4px 0;">Temporary password</td><td style="text-align:right;"><strong style="font-family:monospace;">{{ $password }}</strong></td></tr>
        </table>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ $loginUrl }}" style="background:#1B2429;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:bold;">Sign in</a>
        </p>
        <p style="font-size:12px;color:#6b7280;line-height:1.5;">You will be asked to choose your own password the first time you sign in. If the button doesn't work, open this link: {{ $loginUrl }}</p>
    </div>
</body>
</html>
