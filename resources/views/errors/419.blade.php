<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Session expired — {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--primary:#1F3A52;--primary-dark:#132836;}
    *{box-sizing:border-box;}
    body{margin:0;font-family:'Inter',sans-serif;background:#F6F8F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px 28px;max-width:380px;width:100%;text-align:center;}
    .icon{width:56px;height:56px;border-radius:50%;background:#eef2f4;color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;}
    h1{font-family:'Fraunces',serif;font-size:22px;margin:0 0 8px;color:#1B2429;}
    p{color:#5A6C78;font-size:14px;line-height:1.5;margin:0 0 24px;}
    .btn{display:inline-block;width:100%;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;text-decoration:none;}
    .btn:hover{background:var(--primary-dark);}
</style>
</head>
<body>
    <div class="card">
        <div class="icon">&#8635;</div>
        <h1>Your session expired</h1>
        <p>This page sat open for a while, so for security you'll need to sign in again. Nothing you were working on was saved — after signing in, just pick up where you left off.</p>
        <a href="{{ route('login') }}" class="btn">Sign in again</a>
    </div>
</body>
</html>
