<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>@yield('title', config('app.name'))</title>
@include('partials.pwa-head', ['themeColor' => $theme['primary'] ?? '#1F3A52'])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    :root{
        --primary: {{ $theme['primary'] ?? '#1F3A52' }};
        --primary-dark: {{ $theme['primary_dark'] ?? '#132836' }};
        --accent: {{ $theme['accent'] ?? '#7A93A8' }};
    }
    body{font-family:'Inter',sans-serif;background:#F6F8F9;padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);}
    h1,h2,.display{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;gap:6px;border-radius:9px;padding:9px 14px;font-size:13.5px;font-weight:600;cursor:pointer;}
    .btn.hidden{display:none;}
    .btn-primary{background:var(--primary);color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
    .btn-danger{background:#fbe9e8;color:#b23a32;}
    .card{background:#fff;border:1px solid #e2e6e9;border-radius:12px;}
    .badge{display:inline-flex;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:600;}
    .badge-admin{background:#e7edf1;color:var(--primary);}
    .badge-viewer{background:#f0e7e5;color:#5c6b73;}
    /* Classic dropdown menus (admin navigation and account menu) */
    .cm-trigger{display:flex;align-items:center;gap:8px;padding:7px 12px;border:1px solid rgba(255,255,255,.28);border-radius:8px;}
    .cm-trigger:hover{background:rgba(255,255,255,.12);}
    .cm-menu{position:absolute;top:100%;margin-top:10px;min-width:14.5rem;background:#fff;color:#1B2429;border:1px solid #d9dfe3;border-radius:10px;box-shadow:0 14px 34px rgba(19,40,54,.18);padding:6px 0;z-index:40;}
    .cm-menu.hidden{display:none;}
    .cm-menu::before{content:"";position:absolute;top:-6px;width:10px;height:10px;background:#fff;border-left:1px solid #d9dfe3;border-top:1px solid #d9dfe3;transform:rotate(45deg);}
    .cm-left{left:0;} .cm-left::before{left:22px;}
    .cm-right{right:0;} .cm-right::before{right:22px;}
    .cm-label{font:600 10.5px/1 'Inter',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#8a979e;padding:10px 16px 6px;}
    .cm-item{display:flex;align-items:center;gap:11px;width:100%;text-align:left;padding:9px 16px;font-size:14px;color:#1B2429;border-left:3px solid transparent;background:transparent;cursor:pointer;}
    .cm-item i{width:16px;text-align:center;color:#8a979e;font-size:13px;}
    .cm-item:hover{background:#f6f8f9;border-left-color:var(--primary);}
    .cm-item:hover i{color:var(--primary);}
    .cm-item .badge{margin-left:auto;}
    .cm-sep{height:1px;background:#e9edef;margin:6px 0;}
    .cm-danger,.cm-danger i{color:#b42318;}
    .cm-danger:hover{background:#fdf1f0;border-left-color:#b42318;} .cm-danger:hover i{color:#b42318;}
    /* iOS Safari auto-zooms the whole page when focusing any input under 16px —
       Tailwind's text-sm (14px) triggers this on every form field otherwise. */
    input.text-sm, select.text-sm, textarea.text-sm { font-size: 16px; }
</style>
</head>
<body class="text-[#1B2429]">

@auth
    @unless (auth()->user()->is_super_user)
        @php($bannerEvent = app('currentEvent'))
        @if ($bannerEvent && $bannerEvent->sms_quota !== null)
            @php($bannerRemaining = max(0, $bannerEvent->sms_quota - $bannerEvent->sms_sent_count))
            @if ($bannerRemaining <= 0)
            <div class="bg-red-50 text-red-700 text-sm px-4 py-2 text-center">
                <i class="fa-solid fa-triangle-exclamation"></i> Your SMS quota is finished — sending is paused. Contact your system admin to raise it.
            </div>
            @elseif ($bannerRemaining < 100)
            <div class="bg-amber-50 text-amber-700 text-sm px-4 py-2 text-center">
                <i class="fa-solid fa-triangle-exclamation"></i> Your SMS quota is running low — {{ $bannerRemaining }} message(s) remaining.
            </div>
            @endif
        @endif
    @endunless
@endauth

@if (session('status'))
<div id="toast" class="fixed top-4 right-4 z-50 bg-[#1B2429] text-white px-4 py-3 rounded-lg shadow-lg text-sm">
    {{ session('status') }}
</div>
<script>setTimeout(()=>document.getElementById('toast')?.remove(), 3000);</script>
@endif

@if (session('error'))
<div id="errToast" class="fixed top-4 right-4 z-50 max-w-sm bg-red-600 text-white px-4 py-3 rounded-lg shadow-lg text-sm" role="alert">{{ session('error') }}</div>
<script>setTimeout(()=>document.getElementById('errToast')?.remove(), 6000);</script>
@endif

@if (session('warning'))
<div id="warningToast" class="fixed top-4 right-4 z-[60] max-w-sm bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 rounded-lg shadow-lg text-sm" role="alert">
    <div class="flex items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
        <div class="flex-1">
            <div class="font-semibold mb-1">Account not saved</div>
            @foreach ((array) session('warning') as $w)<div>{{ $w }}</div>@endforeach
        </div>
        <button type="button" onclick="document.getElementById('warningToast').remove()" class="text-amber-600" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
@endif

@if (session('reveal_credentials'))
<div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold text-lg mb-2">Account credentials</h3>
        <p class="text-sm text-gray-500 mb-4">Share these with {{ session('reveal_credentials')['name'] }} if the email doesn't reach them. They'll set their own password on first login.</p>
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
                @if (!request()->routeIs('event.create') && auth()->user()->is_super_user)
                <div class="relative">
                    <button onclick="document.getElementById('navMenu').classList.toggle('hidden')" class="cm-trigger" aria-haspopup="true">
                        <span class="font-semibold display">{{ config('app.name') }}</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div id="navMenu" class="cm-menu cm-left hidden" role="menu">
                        <div class="cm-label">Manage</div>
                        <a href="{{ route('admin.users.index') }}" class="cm-item" role="menuitem"><i class="fa-solid fa-gauge-high"></i> Dashboard &amp; accounts</a>
                        @if (Route::has('admin.sms-usage'))<a href="{{ route('admin.sms-usage') }}" class="cm-item" role="menuitem"><i class="fa-solid fa-comment-sms"></i> SMS usage</a>@endif
                        <a href="{{ route('admin.logs.index') }}" class="cm-item" role="menuitem"><i class="fa-solid fa-clock-rotate-left"></i> Activity logs</a>
                        <div class="cm-sep"></div>
                        <div class="cm-label">Compliance</div>
                        @php($openRequests = \App\Models\DataRequest::whereIn('status', ['new', 'acknowledged'])->count())
                        <a href="{{ route('admin.compliance') }}" class="cm-item" role="menuitem"><i class="fa-solid fa-shield-halved"></i> Privacy &amp; opt-outs @if ($openRequests)<span class="badge" style="background:#fde8e8;color:#b42318;">{{ $openRequests }}</span>@endif</a>
                        <div class="cm-sep"></div>
                        <div class="cm-label">Your account</div>
                        <a href="{{ route('admin.account') }}" class="cm-item" role="menuitem"><i class="fa-solid fa-gear"></i> Account settings</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="cm-item cm-danger" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
                        </form>
                    </div>
                </div>
                @else
                {{-- Regular accounts (admin or viewer): every nav destination is now a
                     card on the landing page itself, so this is just a link back to
                     that landing page — no dropdown, nothing to toggle. Logout lives
                     in the right-side account menu instead (see below). --}}
                <a href="{{ route('dashboard') }}" class="font-semibold hover:opacity-80">Home</a>
                @endif
            @else
                <span class="font-semibold">{{ config('app.name') }}</span>
            @endauth

            <div class="flex-1"></div>

            {{-- Shown only when the browser says the app can be installed (Android/desktop Chrome) and it is not installed yet. --}}
            <button type="button" id="installBtn" class="hidden items-center gap-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-white px-3 py-1.5 text-xs font-semibold" style="display:none;">
                <i class="fa-solid fa-download"></i> Install app
            </button>

            @auth
            @php($rightEvent = app('currentEvent'))
            @php($isSuperUser = auth()->user()->is_super_user)
            @php($isEventAdmin = ! $isSuperUser && $rightEvent && auth()->user()->isAdminOn($rightEvent))
            @if ($isSuperUser)
            {{-- System Admin already has Logout in their own left dropdown — unchanged. --}}
            <div class="text-right px-2 py-1">
                <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                <div class="text-xs opacity-75">Admin</div>
            </div>
            @else
            <div class="relative">
                <button onclick="document.getElementById('accountMenu').classList.toggle('hidden')" class="flex items-center gap-2 text-right px-2 py-1 rounded-lg hover:bg-white/10" aria-haspopup="true">
                    <div>
                        <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-xs opacity-75">{{ ucfirst($rightEvent ? auth()->user()->roleOn($rightEvent) : '') }}</div>
                    </div>
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div id="accountMenu" class="cm-menu cm-right hidden" role="menu" style="min-width:13rem">
                    <div class="cm-label">Your account</div>
                    @if ($isEventAdmin)
                    <button onclick="document.getElementById('accountSettingsModal').classList.remove('hidden'); document.getElementById('accountMenu').classList.add('hidden')" class="cm-item" role="menuitem"><i class="fa-solid fa-gear"></i> Account settings</button>
                    <div class="cm-sep"></div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="cm-item cm-danger" role="menuitem"><i class="fa-solid fa-right-from-bracket"></i> Log out</button>
                    </form>
                </div>
            </div>
            @endif
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full px-5 py-6">
        {{-- iPhone/iPad only: Safari has no install button, so explain the two taps. Hidden once dismissed or installed. --}}
        <div id="iosInstallTip" class="hidden mb-4 card p-3 text-sm items-start gap-3" style="display:none;">
            <i class="fa-solid fa-mobile-screen-button mt-0.5" style="color:var(--primary);"></i>
            <div class="flex-1">
                <strong>Install Fanikisha on your iPhone.</strong>
                Tap <i class="fa-solid fa-arrow-up-from-bracket"></i> <strong>Share</strong>, then <strong>Add to Home Screen</strong>.
            </div>
            <button type="button" id="iosInstallClose" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        @yield('content')
    </main>
</div>

@auth
<div id="accountSettingsModal" onclick="if(event.target===this) this.classList.add('hidden')" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 my-8 space-y-6">
        <div class="flex justify-between items-center">
            <h3 class="font-semibold text-lg">Account settings</h3>
            <button type="button" onclick="document.getElementById('accountSettingsModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form method="POST" action="{{ route('account.username.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <h4 class="text-sm font-semibold">Username</h4>
            <input type="text" name="username" value="{{ auth()->user()->username }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            @error('username')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary w-full justify-center">Save username</button>
        </form>

        <div class="border-t"></div>

        <form method="POST" action="{{ route('account.email.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <h4 class="text-sm font-semibold">Email</h4>
            <input type="email" name="email" value="{{ auth()->user()->email }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
            @error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary w-full justify-center">Save email</button>
        </form>

        <div class="border-t"></div>

        <form method="POST" action="{{ route('account.phone.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <h4 class="text-sm font-semibold">Phone</h4>
            <p class="text-xs text-gray-500">Used to send you a code if you ever need to reset your password.</p>
            <input type="tel" name="phone" value="{{ auth()->user()->phone }}" placeholder="e.g. +255700000000" class="w-full border rounded-lg px-3 py-2 text-sm">
            @error('phone')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary w-full justify-center">Save phone</button>
        </form>

        <div class="border-t"></div>

        <form method="POST" action="{{ route('password.own.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <h4 class="text-sm font-semibold">Password</h4>
            <div><label class="text-xs font-semibold">Current password</label><input type="password" name="current_password" class="w-full border rounded-lg px-3 py-2 text-sm" required></div>
            <div><label class="text-xs font-semibold">New password</label><input type="password" name="password" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="At least 8 characters, upper&lowercase + a number" required></div>
            <div><label class="text-xs font-semibold">Confirm new password</label><input type="password" name="password_confirmation" class="w-full border rounded-lg px-3 py-2 text-sm" required></div>
            @error('current_password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary w-full justify-center">Change password</button>
        </form>
    </div>
</div>

<div id="sessionWarningModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 text-center">
        <i class="fa-solid fa-clock text-2xl mb-3" style="color:var(--primary);"></i>
        <h3 class="font-semibold text-lg mb-1">Still there?</h3>
        <p class="text-sm text-gray-500 mb-4">You'll be signed out in <span id="sessionCountdown" class="font-semibold">60</span> seconds due to inactivity.</p>
        <button id="staySignedInBtn" class="btn btn-primary w-full justify-center"><i class="fa-solid fa-check"></i> Stay signed in</button>
    </div>
</div>

<script>
(function () {
    // Session idle timeout, in minutes, from the server's actual config — kept in sync
    // automatically with SESSION_LIFETIME rather than hardcoded here.
    const SESSION_LIFETIME_MINUTES = {{ (int) config('session.lifetime') }};
    const WARNING_SECONDS = 60; // how long before expiry the warning appears
    const LOGIN_URL = "{{ route('login') }}";
    const KEEP_ALIVE_URL = "{{ route('keep-alive') }}";

    // Wall-clock timestamps (not just setTimeout delays) for when the warning
    // and the actual expiry are due. Browsers throttle or fully pause
    // setTimeout/setInterval in a backgrounded tab (e.g. the person switches
    // to WhatsApp mid-form at a live event) — the server's session clock keeps
    // running regardless, so a purely timer-based approach can silently miss
    // the warning entirely and let a stale submit hit a raw 419 error. The
    // visibilitychange check below re-validates against real elapsed time the
    // moment the tab comes back to the foreground, catching exactly that case.
    let warnAt = Date.now() + (SESSION_LIFETIME_MINUTES * 60 - WARNING_SECONDS) * 1000;
    let expireAt = Date.now() + SESSION_LIFETIME_MINUTES * 60 * 1000;

    let warningTimer, countdownInterval, secondsLeft;

    function showWarning() {
        secondsLeft = Math.max(0, Math.round((expireAt - Date.now()) / 1000));
        document.getElementById('sessionCountdown').textContent = secondsLeft;
        document.getElementById('sessionWarningModal').classList.remove('hidden');
        clearInterval(countdownInterval);
        countdownInterval = setInterval(function () {
            secondsLeft = Math.max(0, Math.round((expireAt - Date.now()) / 1000));
            document.getElementById('sessionCountdown').textContent = secondsLeft;
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
                // The session has already expired server-side by this point (this
                // was deliberately timed to fire right as the idle window closes) — sending
                // the person to login now avoids leaving them stuck on a dead page.
                window.location.href = LOGIN_URL + '?timeout=1';
            }
        }, 1000);
    }

    function scheduleWarning() {
        warnAt = Date.now() + (SESSION_LIFETIME_MINUTES * 60 - WARNING_SECONDS) * 1000;
        expireAt = Date.now() + SESSION_LIFETIME_MINUTES * 60 * 1000;
        clearTimeout(warningTimer);
        clearInterval(countdownInterval);
        document.getElementById('sessionWarningModal').classList.add('hidden');
        warningTimer = setTimeout(showWarning, Math.max(0, warnAt - Date.now()));
    }

    document.getElementById('staySignedInBtn').addEventListener('click', function () {
        fetch(KEEP_ALIVE_URL, { credentials: 'same-origin' }).finally(scheduleWarning);
    });

    // Catches a backgrounded tab whose setTimeout got throttled/paused: the
    // instant the tab is foregrounded again, compare against the real
    // wall-clock deadlines rather than trusting the timer to have fired on
    // schedule while hidden.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible') return;
        if (Date.now() >= expireAt) {
            window.location.href = LOGIN_URL + '?timeout=1';
        } else if (Date.now() >= warnAt && document.getElementById('sessionWarningModal').classList.contains('hidden')) {
            showWarning();
        }
    });

    scheduleWarning();
})();

// Opens/closes a per-row action menu (e.g. admin Users table). Flips the menu to
// open upward instead of downward when the row is near the bottom of the viewport,
// so it never gets clipped off-screen for rows near the end of the table/page.
function toggleRowMenu(id) {
    const menu = document.getElementById(id);
    if (!menu) return;

    const opening = menu.classList.contains('hidden');
    document.querySelectorAll('.row-menu').forEach(function (m) {
        m.classList.add('hidden');
        m.classList.remove('bottom-full', 'mb-1');
    });

    if (opening) {
        menu.classList.remove('hidden');
        const rect = menu.getBoundingClientRect();
        const button = menu.previousElementSibling;
        const buttonBottom = button ? button.getBoundingClientRect().bottom : rect.bottom;

        if (buttonBottom + rect.height > window.innerHeight) {
            menu.classList.add('bottom-full', 'mb-1');
        }
    }
}

// Close the nav/user dropdown menus when clicking anywhere outside them, instead of
// only via their own trigger buttons (which just toggles them back open/shut).
document.addEventListener('click', function (e) {
    const navMenu = document.getElementById('navMenu');
    const accountMenu = document.getElementById('accountMenu');
    const exportMenu = document.getElementById('exportMenu');
    if (navMenu && !navMenu.classList.contains('hidden') && !e.target.closest('#navMenu') && !e.target.closest('button[onclick*="navMenu"]')) {
        navMenu.classList.add('hidden');
    }
    if (accountMenu && !accountMenu.classList.contains('hidden') && !e.target.closest('#accountMenu') && !e.target.closest('button[onclick*="accountMenu"]')) {
        accountMenu.classList.add('hidden');
    }
    if (exportMenu && !exportMenu.classList.contains('hidden') && !e.target.closest('#exportMenu') && !e.target.closest('button[onclick*="exportMenu"]')) {
        exportMenu.classList.add('hidden');
    }
    // Per-row action menus (e.g. admin Users table) — any number of rows, each
    // with its own id, so this closes whichever one is open rather than a fixed id.
    document.querySelectorAll('.row-menu').forEach(function (menu) {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !e.target.closest('button[onclick*="' + menu.id + '"]')) {
            menu.classList.add('hidden');
        }
    });
});
// Auto-refresh every 30s — skipped while typing into a field or while any modal
// (identified by the shared .fixed.inset-0 dialog pattern used across the app) is
// open, so it can't silently wipe out something you're in the middle of entering.
setInterval(function () {
    const typing = document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);
    const modalOpen = Array.from(document.querySelectorAll('.fixed.inset-0')).some(m => !m.classList.contains('hidden'));
    if (!typing && !modalOpen) {
        window.location.reload();
    }
}, 30000);

// Generic click-to-sort for any <table class="sortable-table">. Add data-sort="text"
// or data-sort="number" to a <th> to make that column sortable — no per-page JS needed.
document.querySelectorAll('table.sortable-table thead th[data-sort]').forEach(function (th) {
    const label = th.textContent.trim();
    th.style.cursor = 'pointer';
    th.innerHTML = label + ' <span class="sort-arrow text-gray-300"></span>';

    th.addEventListener('click', function () {
        const table = th.closest('table');
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
        if (rows.length < 2) return;

        const colIndex = Array.from(th.parentNode.children).indexOf(th);
        const type = th.dataset.sort;
        const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';

        table.querySelectorAll('thead th[data-sort]').forEach(function (h) {
            h.dataset.sortDir = '';
            h.querySelector('.sort-arrow').textContent = '';
        });
        th.dataset.sortDir = dir;
        th.querySelector('.sort-arrow').textContent = dir === 'asc' ? '▲' : '▼';

        rows.sort(function (a, b) {
            let av = a.children[colIndex]?.textContent.trim() ?? '';
            let bv = b.children[colIndex]?.textContent.trim() ?? '';
            let cmp;
            if (type === 'number') {
                cmp = (parseFloat(av.replace(/[^0-9.\-]/g, '')) || 0) - (parseFloat(bv.replace(/[^0-9.\-]/g, '')) || 0);
            } else {
                cmp = av.localeCompare(bv);
            }
            return dir === 'asc' ? cmp : -cmp;
        });

        rows.forEach(r => tbody.appendChild(r));
    });
});
</script>
@endauth

<div id="confirmModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 id="confirmModalTitle" class="font-semibold text-lg mb-2">Are you sure?</h3>
        <p id="confirmModalMessage" class="text-sm text-gray-500 mb-5"></p>
        <div class="flex gap-2">
            <button type="button" id="confirmModalCancel" class="btn btn-ghost flex-1 justify-center">Cancel</button>
            <button type="button" id="confirmModalOk" class="btn btn-danger flex-1 justify-center"><i class="fa-solid fa-trash"></i> Delete</button>
        </div>
    </div>
</div>
<script>
// Generic search box for every list table on the page. Tables inside the main
// content get a "Search…" input above them; typing hides non-matching rows.
// A table with data-search-groups (Event Management) is filtered by committee
// instead: a committee stays visible if its name or any member matches.
(function () {
    const tables = Array.from(document.querySelectorAll('main table')).filter(function (t) {
        return !t.closest('.fixed') && !t.hasAttribute('data-no-search') && t.tBodies.length && t.querySelectorAll('tbody tr').length;
    });
    const seen = new Set();

    tables.forEach(function (table) {
        if (seen.has(table)) return;
        seen.add(table);

        const grouped = table.hasAttribute('data-search-groups');
        const wrap = table.closest('.card') || table.parentElement;

        // Pages with a single data row don't need a search box.
        const dataRows = Array.from(table.tBodies[0].rows).filter(function (r) { return !r.querySelector('td[colspan]') || grouped; });
        if (dataRows.length < 2) return;

        const box = document.createElement('div');
        box.className = 'relative mb-3';
        box.innerHTML = '<i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>' +
            '<input type="search" placeholder="Search…" aria-label="Search" class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm bg-white">';
        wrap.parentNode.insertBefore(box, wrap);

        const noMatch = document.createElement('div');
        noMatch.className = 'text-center text-sm text-gray-400 py-6 hidden';
        noMatch.textContent = 'No matches found.';
        wrap.parentNode.insertBefore(noMatch, wrap.nextSibling);

        const input = box.querySelector('input');

        function groupsOf(rows) {
            const groups = [];
            rows.forEach(function (r) {
                const first = r.querySelector('td[colspan]');
                const isCommitteeHeader = first && /^committee$/i.test(first.textContent.trim());
                if (isCommitteeHeader || !groups.length) groups.push([]);
                groups[groups.length - 1].push(r);
            });
            return groups;
        }

        input.addEventListener('input', function () {
            const q = input.value.trim().toLowerCase();
            const rows = Array.from(table.tBodies[0].rows);
            let visible = 0;

            if (grouped) {
                groupsOf(rows).forEach(function (g) {
                    const match = !q || g.some(function (r) { return r.textContent.toLowerCase().includes(q); });
                    g.forEach(function (r) { r.style.display = match ? '' : 'none'; });
                    if (match) visible++;
                });
            } else {
                rows.forEach(function (r) {
                    if (r.querySelector('td[colspan]')) { r.style.display = q ? 'none' : ''; return; }
                    const match = !q || r.textContent.toLowerCase().includes(q);
                    r.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
            }

            noMatch.classList.toggle('hidden', !q || visible > 0);
        });
    });
})();
</script>

<script>
(function () {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmModalTitle');
    const messageEl = document.getElementById('confirmModalMessage');
    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
            e.preventDefault();
            pendingForm = form;
            // Remember which button was pressed: requestSubmit() without it drops the button's name/value.
            pendingSubmitter = e.submitter || null;
            titleEl.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
            messageEl.textContent = form.getAttribute('data-confirm');

            const buttonLabel = form.getAttribute('data-confirm-button') || 'Delete';
            const buttonIcon = form.getAttribute('data-confirm-icon') || 'fa-trash';
            const isDestructive = !form.hasAttribute('data-confirm-button') || form.hasAttribute('data-confirm-danger');
            okBtn.innerHTML = '<i class="fa-solid ' + buttonIcon + '"></i> ' + buttonLabel;
            okBtn.classList.toggle('btn-danger', isDestructive);
            okBtn.classList.toggle('btn-primary', !isDestructive);

            modal.classList.remove('hidden');
        }
    }, true);

    function close() {
        modal.classList.add('hidden');
        pendingForm = null;
        pendingSubmitter = null;
    }

    cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });

    okBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        modal.classList.add('hidden');
        if (pendingForm.requestSubmit) {
            pendingForm.requestSubmit(pendingSubmitter || undefined);
        } else {
            pendingForm.submit();
        }
        pendingForm = null;
        pendingSubmitter = null;
    });
})();
</script>

<script>
// Offline check-in keeps a guest list and queued scans on this phone (see the Check-in page). They belong
// to whoever was logged in, so logging out removes them — after a warning if some haven't been uploaded.
document.querySelectorAll('form[action$="/logout"]').forEach(function (form) {
    let confirmed = false;

    form.addEventListener('submit', function (e) {
        if (confirmed || !('indexedDB' in window)) return;
        e.preventDefault();

        function finish() {
            confirmed = true;

            const deleteDb = new Promise(function (resolve) {
                const req = indexedDB.deleteDatabase('fanikisha-checkin');
                req.onsuccess = req.onerror = req.onblocked = function () { resolve(); };
            });
            const clearCache = new Promise(function (resolve) {
                const worker = navigator.serviceWorker && navigator.serviceWorker.controller;
                if (!worker) return resolve();
                const channel = new MessageChannel();
                channel.port1.onmessage = function () { resolve(); };
                worker.postMessage({ type: 'CLEAR_RUNTIME' }, [channel.port2]);
                setTimeout(resolve, 1500);
            });

            Promise.all([deleteDb, clearCache]).then(function () { form.submit(); });
        }

        // Count scans still waiting to upload (without creating the database if it doesn't exist yet).
        const open = indexedDB.open('fanikisha-checkin');
        open.onupgradeneeded = function () { open.transaction.abort(); };
        open.onerror = function () { finish(); };
        open.onsuccess = function () {
            const db = open.result;
            if (!db.objectStoreNames.contains('queue')) { db.close(); return finish(); }

            const count = db.transaction('queue', 'readonly').objectStore('queue').count();
            count.onsuccess = function () {
                db.close();
                if (count.result > 0 && !window.confirm(count.result + ' check-in(s) on this phone have not been uploaded yet. Logging out will delete them. Log out anyway?')) {
                    return;
                }
                finish();
            };
            count.onerror = function () { db.close(); finish(); };
        };
    });
});
</script>

<script>
// Registered after the page has fully loaded so it never competes with or
// delays the actual page content — see public/sw.js for what it does.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        // ?v=... changes on every deploy (App\Support\PwaVersion), which is what makes phones pick up a new worker.
        navigator.serviceWorker.register('/sw.js?v={{ \App\Support\PwaVersion::hash() }}').catch(function () {
            // Fails silently — e.g. on http (non-HTTPS) local dev. The app
            // works identically either way; this is a pure enhancement.
        });
    });
}
</script>

<script>
// "Install app" button (Android / desktop Chrome) and the "Add to Home Screen" tip (iPhone / iPad).
(function () {
    const KEY = 'fk_install_tip_dismissed';
    const btn = document.getElementById('installBtn');
    const tip = document.getElementById('iosInstallTip');
    let deferredPrompt = null;

    function installed() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    }
    function isDismissed() {
        try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
    }
    function remember() {
        try { localStorage.setItem(KEY, '1'); } catch (e) { /* private mode: it simply shows again next time */ }
    }
    function show(el) { if (el) { el.style.display = 'flex'; el.classList.remove('hidden'); } }
    function hide(el) { if (el) { el.style.display = 'none'; el.classList.add('hidden'); } }

    if (installed()) return; // already running as an app: nothing to offer

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        show(btn);
    });

    if (btn) {
        btn.addEventListener('click', async function () {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            try { await deferredPrompt.userChoice; } catch (e) { /* ignore */ }
            deferredPrompt = null;
            hide(btn);
        });
    }

    window.addEventListener('appinstalled', function () { hide(btn); hide(tip); });

    const ua = navigator.userAgent || '';
    const isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isIos && !isDismissed()) show(tip);

    const close = document.getElementById('iosInstallClose');
    if (close) close.addEventListener('click', function () { remember(); hide(tip); });
})();
</script>

</body>
</html>