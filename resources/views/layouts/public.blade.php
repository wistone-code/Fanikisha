@php
    $c = config('company');
    $sw = ($lang ?? 'en') === 'sw';
    $other = $sw ? 'en' : 'sw';
@endphp
<!DOCTYPE html>
<html lang="{{ $sw ? 'sw' : 'en' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>@yield('title', $c['brand'].' — Event invitations, RSVP & contributions')</title>
<meta name="description" content="@yield('description', 'Fanikisha helps event organisers in Tanzania send digital invitation cards, collect RSVPs, check guests in at the door and manage contributions.')">
@include('partials.pwa-head', ['themeColor' => '#1F3A52'])
<meta property="og:title" content="@yield('title', $c['brand'])">
<meta property="og:description" content="@yield('description', 'Digital invitations, RSVP, check-in and contributions for weddings, funerals and events.')">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@vite(['resources/css/app.css'])
<style>
    body{font-family:'Inter',sans-serif;color:#1B2429;background:#fff;}
    h1,h2,h3.serif{font-family:'Fraunces',serif;}
    .pbtn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;padding:12px 20px;font-size:14px;font-weight:600;}
    .pbtn-primary{background:#1F3A52;color:#fff;}
    .pbtn-light{background:#fff;color:#1F3A52;border:1px solid #d9e0e5;}
    .prose-doc h2{font-size:1.15rem;margin:1.8rem 0 .5rem;font-weight:700;}
    .prose-doc p,.prose-doc li{font-size:.95rem;line-height:1.65;color:#374151;}
    .prose-doc p{margin:.75rem 0;}
    .prose-doc table{width:100%;font-size:.85rem;border-collapse:collapse;margin:1rem 0;display:block;overflow-x:auto;}
    .prose-doc th,.prose-doc td{border:1px solid #e5e7eb;padding:.45rem .6rem;text-align:left;vertical-align:top;}
    .prose-doc th{background:#f4f5f7;}
    .prose-doc blockquote{border-left:3px solid #1F3A52;padding-left:1rem;color:#374151;margin:1rem 0;}
    .prose-doc ul{list-style:disc;padding-left:1.25rem;margin:.5rem 0;}
    .prose-doc li{margin:.25rem 0;}
    .prose-doc a{color:#1F3A52;text-decoration:underline;}
</style>
</head>
<body class="min-h-screen flex flex-col">
<header class="border-b border-gray-100 bg-white sticky top-0 z-10">
    <div class="max-w-5xl mx-auto px-5 h-14 flex items-center justify-between">
        <a href="{{ route('home', ['lang' => $sw ? 'sw' : null]) }}" class="flex items-center gap-2 font-bold">
            <span class="w-8 h-8 rounded-lg bg-[#1F3A52] text-white flex items-center justify-center text-base">F</span>
            <span>{{ $c['brand'] }}</span>
        </a>
        <nav class="flex items-center gap-3 text-sm">
            <a href="{{ url()->current() }}?lang={{ $other }}" class="text-gray-500 hover:text-gray-800" hreflang="{{ $other }}">{{ $sw ? 'English' : 'Kiswahili' }}</a>
            <a href="{{ route('login') }}" class="pbtn pbtn-primary !py-2 !px-4 !text-[13px]">{{ $sw ? 'Ingia' : 'Sign in' }}</a>
        </nav>
    </div>
</header>

<main class="flex-1">@yield('content')</main>

<footer class="bg-[#0F1D26] text-gray-300 mt-12">
    <div class="max-w-5xl mx-auto px-5 py-10 grid gap-8 sm:grid-cols-3 text-sm">
        <div>
            <div class="font-bold text-white mb-2">{{ $c['legal_name'] }}</div>
            <p class="text-gray-400 leading-relaxed">{{ $sw ? 'Mwaliko wa kidijitali, RSVP, kuingia mlangoni na michango kwa matukio yako.' : 'Digital invitations, RSVP, door check-in and contributions for your events.' }}</p>
            @if ($c['registration'])<p class="text-gray-500 text-xs mt-2">{{ $c['registration'] }}</p>@endif
        </div>
        <div>
            <div class="font-semibold text-white mb-2">{{ $sw ? 'Mawasiliano' : 'Contact' }}</div>
            <ul class="space-y-1.5 text-gray-400">
                <li><i class="fa-solid fa-envelope w-5"></i> <a href="mailto:{{ $c['email'] }}" class="hover:text-white">{{ $c['email'] }}</a></li>
                @if ($c['phone'])<li><i class="fa-solid fa-phone w-5"></i> <a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}" class="hover:text-white">{{ $c['phone'] }}</a></li>@endif
                <li><i class="fa-solid fa-location-dot w-5"></i> {{ $c['address'] }}</li>
            </ul>
        </div>
        <div>
            <div class="font-semibold text-white mb-2">{{ $sw ? 'Taarifa' : 'Legal' }}</div>
            <ul class="space-y-1.5 text-gray-400">
                <li><a href="{{ route('privacy', ['lang' => $sw ? 'sw' : null]) }}" class="hover:text-white">{{ $sw ? 'Sera ya Faragha' : 'Privacy Policy' }}</a></li>
                <li><a href="{{ route('terms', ['lang' => $sw ? 'sw' : null]) }}" class="hover:text-white">{{ $sw ? 'Vigezo na Masharti' : 'Terms of Service' }}</a></li>
                <li><a href="{{ route('acceptable-use', ['lang' => $sw ? 'sw' : null]) }}" class="hover:text-white">{{ $sw ? 'Sera ya Matumizi na Ujumbe' : 'Acceptable Use & Messaging' }}</a></li>
                <li><a href="{{ route('data-request', ['lang' => $sw ? 'sw' : null]) }}" class="hover:text-white">{{ $sw ? 'Taarifa zako na ujumbe' : 'Your data & messages' }}</a></li>
                <li><a href="{{ route('login') }}" class="hover:text-white">{{ $sw ? 'Ingia' : 'Sign in' }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10 text-center text-xs text-gray-500 py-4">© {{ date('Y') }} {{ $c['legal_name'] }}. {{ $sw ? 'Haki zote zimehifadhiwa.' : 'All rights reserved.' }}</div>
</footer>
</body>
</html>
