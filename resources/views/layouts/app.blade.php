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
    :root{
        --primary: {{ $theme['primary'] ?? '#1F3A52' }};
        --primary-dark: {{ $theme['primary_dark'] ?? '#132836' }};
        --accent: {{ $theme['accent'] ?? '#7A93A8' }};
    }
    body{font-family:'Inter',sans-serif;background:#F6F8F9;}
    h1,h2,.display{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:9px 14px;font-size:13.5px;font-weight:600;cursor:pointer;}
    .btn-primary{background:var(--primary);color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
    .btn-danger{background:#fbe9e8;color:#b23a32;}
    .card{background:#fff;border:1px solid #e2e6e9;border-radius:12px;}
    .badge{display:inline-flex;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:600;}
    .badge-admin{background:#e7edf1;color:var(--primary);}
    .badge-viewer{background:#f0e7e5;color:#5c6b73;}
</style>
</head>
<body class="text-[#1B2429]">

@if (session('status'))
<div id="toast" class="fixed top-4 right-4 z-50 bg-[#1B2429] text-white px-4 py-3 rounded-lg shadow-lg text-sm">
    {{ session('status') }}
</div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(), 3000);</script>
@endif

@if (session('reveal_credentials'))
<div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold text-lg mb-2">Account credentials</h3>
        <p class="text-sm text-gray-500 mb-4">Share these with {{ session('reveal_credentials')['name'] }}. They'll set their own password on first login.</p>
        <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-2">
            <div class="flex justify-between"><span class="text-gray-500">Username</span><strong>{{ session('reveal_credentials')['username'] }}</strong></div>
            <div class="flex justify-between"><span class="text-gray-500">Temporary password</span><strong class="font-mono">{{ session('reveal_credentials')['password'] }}</strong></div>
        </div>
        <button onclick="this.closest('.fixed').remove()" class="btn btn-primary w-full justify-center mt-4">Done</button>
    </div>
</div>
@endif

<div class="min-h-screen flex flex-col">
    <header class="text-white" style="background:var(--primary);border-bottom:3px solid var(--accent);">
        <div class="max-w-6xl mx-auto px-5 py-3 flex items-center gap-4">
            @auth
                @if (!request()->routeIs('event.create'))
                <div class="relative">
                    <button onclick="document.getElementById('navMenu').classList.toggle('hidden')" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/10">
                        <span class="font-semibold">{{ config('app.name') }}</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div id="navMenu" class="hidden absolute left-0 mt-1 w-56 bg-white text-[#1B2429] rounded-xl shadow-xl p-1 z-40">
                        @if (auth()->user()->is_super_user)
                            <a href="{{ route('admin.users.index') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">User Management</a>
                            <a href="{{ route('admin.account') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Account Settings</a>
                        @else
                            @php($event = app('currentEvent'))
                            @php($isAdmin = $event && auth()->user()->isAdminOn($event))
                            @if ($event)
                                @php($routeNames = ['home' => 'dashboard', 'financial' => 'financial.index', 'pledges' => 'pledges.index', 'providers' => 'providers.index', 'committees' => 'committees.index', 'schedule' => 'schedule.index', 'team' => 'team.index', 'invitations' => 'guests.index', 'settings' => 'event.settings'])
                                @foreach (app(\App\Services\NavLabelService::class)->itemsFor($event, $isAdmin) as $item)
                                    <a href="{{ route($routeNames[$item['id']]) }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">{{ $item['label'] }}</a>
                                @endforeach
                            @endif
                        @endif
                        <div class="border-t my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">Logout</button>
                        </form>
                    </div>
                </div>
                @else
                <span class="font-semibold">{{ config('app.name') }}</span>
                @endif
            @else
                <span class="font-semibold">{{ config('app.name') }}</span>
            @endauth

            <div class="flex-1"></div>

            @auth
            <div class="relative">
                <button onclick="document.getElementById('userMenu').classList.toggle('hidden')" class="text-right px-2 py-1 rounded-lg hover:bg-white/10">
                    <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                    <div class="text-xs opacity-75">{{ auth()->user()->is_super_user ? 'Admin' : ucfirst(app('currentEvent') ? auth()->user()->roleOn(app('currentEvent')) : '') }}</div>
                </button>
                <div id="userMenu" class="hidden absolute right-0 mt-1 w-48 bg-white text-[#1B2429] rounded-xl shadow-xl p-1 z-40">
                    <button onclick="document.getElementById('changePasswordModal').classList.remove('hidden')" class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Change password</button>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">Logout</button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full px-5 py-6">
        @yield('content')
    </main>
</div>

@auth
<div id="changePasswordModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold text-lg mb-4">Change password</h3>
        <form method="POST" action="{{ route('password.own.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <div><label class="text-xs font-semibold">Current password</label><input type="password" name="current_password" class="w-full border rounded-lg px-3 py-2 text-sm" required></div>
            <div><label class="text-xs font-semibold">New password</label><input type="password" name="password" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="At least 8 characters, upper&lowercase + a number" required></div>
            <div><label class="text-xs font-semibold">Confirm new password</label><input type="password" name="password_confirmation" class="w-full border rounded-lg px-3 py-2 text-sm" required></div>
            @error('current_password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('changePasswordModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Change password</button>
            </div>
        </form>
    </div>
</div>
@endauth

</body>
</html>
