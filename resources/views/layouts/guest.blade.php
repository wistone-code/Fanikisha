<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>@yield('title', config('app.name'))</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<style>
    body{font-family:'Inter',sans-serif;padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);}
    input.text-sm, select.text-sm, textarea.text-sm { font-size: 16px; }
    h1{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:9px;padding:11px 14px;font-size:13.5px;font-weight:600;cursor:pointer;width:100%;}
    .btn-primary{background:#1F3A52;color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-5" style="background:radial-gradient(120% 140% at 20% 0%, #17303F 0%, #0A1319 60%);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="text-center pt-8 px-8">
            <div class="w-13 h-13 mx-auto mb-3 rounded-xl bg-[#1F3A52] text-white flex items-center justify-center text-2xl font-bold" style="width:52px;height:52px;">F</div>
            <h1 class="text-xl mb-1">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500 mb-2">Your Event Partner</p>
        </div>
        <div class="px-8 pb-8 pt-2">
            @yield('content')
        </div>
    </div>
</body>
</html>
