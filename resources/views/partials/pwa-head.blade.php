{{-- Shared "installable app" tags for every layout (see public/manifest.json).
     $themeColor : colour of the phone's address/status bar
     $withApple  : add the iPhone Home-Screen tags (default true; the guest invitation page leaves them out) --}}
<link rel="manifest" href="/manifest.json">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<meta name="theme-color" content="{{ $themeColor ?? '#1F3A52' }}">
@if ($withApple ?? true)
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Fanikisha">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
@endif
