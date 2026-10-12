{{-- Shared look for the app's error pages: a small card with a message and a way out (no dead ends). --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} — {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root{--primary:#1F3A52;--primary-dark:#132836;}
    *{box-sizing:border-box;}
    body{margin:0;font-family:'Inter',sans-serif;background:#F6F8F9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
    .card{background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px 28px;max-width:380px;width:100%;text-align:center;}
    .icon{width:56px;height:56px;border-radius:50%;background:#eef2f4;color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;}
    h1{font-family:'Fraunces',serif;font-size:22px;margin:0 0 10px;color:#1B2429;}
    p{color:#5b6770;font-size:14px;line-height:1.5;margin:0 0 22px;}
    .btn{display:block;width:100%;background:var(--primary);color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;text-decoration:none;margin-top:8px;}
    .btn:hover{background:var(--primary-dark);}
    .btn-ghost{background:#fff;color:#1B2429;border:1px solid #e2e6e9;}
    .btn-ghost:hover{background:#f3f5f6;}
    .code{color:#9aa5ad;font-size:12px;margin-top:18px;}
</style>
</head>
<body>
    <div class="card">
        <div class="icon">{!! $icon !!}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="javascript:history.back()" class="btn btn-ghost" onclick="if (history.length < 2) { location.href = '{{ url('/dashboard') }}'; return false; }">Go back</a>
        <a href="{{ url('/dashboard') }}" class="btn">Go to dashboard</a>
        <div class="code">Error {{ $code }}</div>
    </div>
</body>
</html>
