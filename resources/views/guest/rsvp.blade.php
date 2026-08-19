<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>You're invited — {{ $event->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:'Inter',sans-serif;} h1{font-family:'Fraunces',serif;}</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6" style="background:radial-gradient(120% 140% at 20% 0%, #17303F 0%, #0A1319 60%);">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-8 text-center">
        <div class="text-xs uppercase tracking-widest text-gray-400 font-semibold mb-2">You're invited to</div>
        <h1 class="text-2xl font-semibold mb-2">{{ $event->name }}</h1>
        <p class="text-sm text-gray-500 mb-1">{{ $event->event_date->format('l, F j, Y') }}</p>
        @if ($event->place)<p class="text-sm text-gray-500 mb-4">{{ $event->place }}</p>@endif
        <p class="text-sm mt-4">Dear {{ $pledge->name }}, thank you for your contribution — we look forward to celebrating with you!</p>
    </div>
</body>
</html>
