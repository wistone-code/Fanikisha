<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>@yield('title', config('app.name'))</title>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="{{ $theme['primary'] ?? '#1F3A52' }}">
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
    .btn-primary{background:var(--primary);color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
    .btn-danger{background:#fbe9e8;color:#b23a32;}
    .card{background:#fff;border:1px solid #e2e6e9;border-radius:12px;}
    .badge{display:inline-flex;padding:2px 10px;border-radius:20px;font-size:11.5px;font-weight:600;}
    .badge-admin{background:#e7edf1;color:var(--primary);}
    .badge-viewer{background:#f0e7e5;color:#5c6b73;}
    /* iOS Safari auto-zooms the whole page when focusing any input under 16px —
       Tailwind's text-sm (14px) triggers this on every form field otherwise. */
    input.text-sm, select.text-sm, textarea.text-sm { font-size: 16px; }
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
                @if (!request()->routeIs('event.create') && auth()->user()->is_super_user)
                <div class="relative">
                    <button onclick="document.getElementById('navMenu').classList.toggle('hidden')" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/10">
                        <span class="font-semibold">{{ config('app.name') }}</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </button>
                    <div id="navMenu" class="hidden absolute left-0 mt-1 w-56 bg-white text-[#1B2429] rounded-xl shadow-xl p-1 z-40">
                        <a href="{{ route('admin.users.index') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">User Management</a>
                        <a href="{{ route('admin.logs.index') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Logs</a>
                        <a href="{{ route('admin.account') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Account Settings</a>
                        <div class="border-t my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">Logout</button>
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
                <button onclick="document.getElementById('accountMenu').classList.toggle('hidden')" class="flex items-center gap-2 text-right px-2 py-1 rounded-lg hover:bg-white/10">
                    <div>
                        <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-xs opacity-75">{{ ucfirst($rightEvent ? auth()->user()->roleOn($rightEvent) : '') }}</div>
                    </div>
                    <i class="fa-solid fa-chevron-down text-xs"></i>
                </button>
                <div id="accountMenu" class="hidden absolute right-0 mt-1 w-48 bg-white text-[#1B2429] rounded-xl shadow-xl p-1 z-40">
                    @if ($isEventAdmin)
                    <button onclick="document.getElementById('accountSettingsModal').classList.remove('hidden'); document.getElementById('accountMenu').classList.add('hidden')" class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50">Account settings</button>
                    <div class="border-t my-1"></div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="w-full text-left px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50">Logout</button>
                    </form>
                </div>
            </div>
            @endif
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full px-5 py-6">
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
(function () {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmModalTitle');
    const messageEl = document.getElementById('confirmModalMessage');
    const okBtn = document.getElementById('confirmModalOk');
    const cancelBtn = document.getElementById('confirmModalCancel');
    let pendingForm = null;

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
            e.preventDefault();
            pendingForm = form;
            titleEl.textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
            messageEl.textContent = form.getAttribute('data-confirm');
            modal.classList.remove('hidden');
        }
    }, true);

    function close() {
        modal.classList.add('hidden');
        pendingForm = null;
    }

    cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });

    okBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        modal.classList.add('hidden');
        if (pendingForm.requestSubmit) {
            pendingForm.requestSubmit();
        } else {
            pendingForm.submit();
        }
        pendingForm = null;
    });
})();
</script>

</body>
</html>