@extends('layouts.app')
@section('title', 'Entrance Check-in — '.config('app.name'))

@section('content')
@include('event.guests._tabs', ['active' => 'checkin'])

<div class="mb-4 flex justify-between items-start flex-wrap gap-2">
    <div>
        <h2 class="text-xl font-semibold">Entrance Check-in</h2>
        <p class="text-sm text-gray-500"><span id="checkinCount">{{ $checkedInCount }}</span> of <span id="expectedCount">{{ $eligibleCount }}</span> invited guests checked in · <span id="peopleIn">{{ $stats['people_in'] }}</span> people through the door</p>
    </div>
    @if ($isAdmin)
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('checkin.door-list') }}" target="_blank" class="btn btn-ghost !py-1.5 !px-3 text-xs"><i class="fa-solid fa-print"></i> Door list</a>
        <form method="POST" action="{{ route('event.settings.checkin-confirm') }}" class="flex items-center gap-1 text-xs">@csrf @method('PATCH')
            <label class="flex items-center gap-1"><input type="checkbox" name="checkin_confirm_name" value="1" @checked(app('currentEvent')->checkin_confirm_name) onchange="this.form.submit()"> Confirm name before checking in</label></form>
    </div>
    @endif
</div>

@if ($isAdmin && count($stats['per_scanner']))
<div class="card p-3 mb-4 text-xs text-gray-600" id="scannerStats">Checked in by: @foreach ($stats['per_scanner'] as $row)<strong>{{ $row['name'] }}</strong> {{ $row['count'] }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</div>
@endif

<div class="card p-4 mb-4" id="offlineBar">
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
        <span id="netStatus" class="badge badge-admin"><i class="fa-solid fa-wifi text-[10px]"></i>&nbsp;Online</span>
        <span>Guests on this phone: <strong id="cachedCount">0</strong></span>
        <span>Waiting to sync: <strong id="queueCount">0</strong></span>
        <span>Last sync: <strong id="lastSync">never</strong></span>
    </div>
    <div class="flex gap-2 mt-3 flex-wrap">
        <button type="button" id="prepareBtn" class="btn btn-primary !py-1.5 !px-3 text-xs"><i class="fa-solid fa-cloud-arrow-down"></i> Prepare for offline</button>
        <button type="button" id="syncBtn" class="btn btn-ghost !py-1.5 !px-3 text-xs"><i class="fa-solid fa-rotate"></i> Sync now</button>
        <button type="button" id="clearOfflineBtn" class="btn btn-ghost !py-1.5 !px-3 text-xs text-red-600"><i class="fa-solid fa-trash"></i> Clear offline data</button>
    </div>
    <p id="offlineMsg" class="text-xs text-gray-500 mt-2">Before the event, tap <strong>Prepare for offline</strong> while you have internet. Scanning then keeps working without a connection, and check-ins upload by themselves when signal returns.</p>
</div>

<div id="conflictCard" class="hidden card p-4 mb-4 border-2 border-amber-500 bg-amber-50">
    <div class="text-sm font-semibold text-amber-800 mb-1"><i class="fa-solid fa-triangle-exclamation"></i> Already checked in elsewhere</div>
    <p class="text-xs text-amber-800 mb-2">These guests were scanned on this phone while offline, but another scanner had already checked them in. The earlier check-in stands.</p>
    <div id="conflictList" class="space-y-1 text-sm"></div>
    <button type="button" id="dismissConflicts" class="btn btn-ghost !py-1 !px-2 text-xs mt-2">Dismiss</button>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <div class="card p-5">
        <div class="text-sm font-semibold mb-3">Scan QR code</div>
        <div id="qr-reader" class="rounded-lg overflow-hidden"></div>
        <button id="startScanBtn" class="btn btn-primary w-full justify-center mt-3"><i class="fa-solid fa-camera"></i> Start camera</button>
        <button id="stopScanBtn" class="btn btn-ghost w-full justify-center mt-2 hidden"><i class="fa-solid fa-stop"></i> Stop camera</button>
        <p class="text-xs text-gray-400 mt-2">Point the camera at the QR code on the guest's e-card.</p>
        <p id="scanStruggleHint" class="hidden text-xs text-amber-600 mt-2"><i class="fa-solid fa-triangle-exclamation"></i> Having trouble scanning? Move closer, raise the guest's screen brightness, or use search instead.</p>
    </div>

    <div class="card p-5">
        <div class="text-sm font-semibold mb-3">Or search by name, card code or phone</div>
        <input type="text" id="searchInput" placeholder="Name, card code (e.g. K7M2Q) or last digits of phone" autocomplete="off" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <div id="searchResults" class="space-y-2 max-h-72 overflow-y-auto"></div>
        <p class="text-xs text-gray-400 mt-2">For guests whose phone is flat or who have no smartphone — ask for the code printed under the QR on their card.</p>
    </div>
</div>

<div id="resultCard" class="hidden card p-5 mt-4"></div>

<div class="card p-5 mt-4">
    <div class="text-sm font-semibold mb-3">Arrival log</div>
    <div id="arrivalsList" class="space-y-2 max-h-96 overflow-y-auto">
        @forelse ($arrivals as $arrival)
            <div class="flex justify-between items-center border rounded-lg px-3 py-2">
                <span class="text-sm">{{ $arrival->name }}@if ($arrival->seat) <span class="text-xs text-gray-400">· {{ $arrival->seat }}</span>@endif</span>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">{{ $arrival->checked_in_at->format('g:i A, M j') }}@if ($arrival->by_name && $isAdmin) · {{ $arrival->by_name }}@endif</span>
                    @if ($isAdmin)<form method="POST" action="{{ route('checkin.undo', $arrival) }}" data-confirm="Remove {{ $arrival->name }}'s check-in? They'll show as not-yet-arrived again." data-confirm-title="Undo check-in?" data-confirm-button="Undo" data-confirm-icon="fa-rotate-left">
                        @csrf @method('DELETE')
                        <button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600" title="Undo check-in"><i class="fa-solid fa-rotate-left"></i></button>
                    </form>@endif
                </div>
            </div>
        @empty
            <p id="noArrivals" class="text-xs text-gray-400">No one checked in yet.</p>
        @endforelse
    </div>
</div>

<div class="card p-5 mt-4">
    <div class="text-sm font-semibold mb-3">Live — latest arrivals at every door <span class="text-xs text-gray-400 font-normal">(updates every 20 seconds)</span></div>
    <div id="liveList" class="space-y-1 text-sm text-gray-600"><span class="text-xs text-gray-400">Waiting for the first update…</span></div>
</div>

<script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script>
    const csrfToken = {{ Js::from(csrf_token()) }};
    const verifyUrl = {{ Js::from(route('checkin.verify')) }};
    const searchUrl = {{ Js::from(route('checkin.search')) }};
    const undoUrlTemplate = {{ Js::from(route('checkin.undo', ['pledge' => '__ID__'])) }};
    const guestListUrl = {{ Js::from(route('checkin.guest-list')) }};
    const syncUrl = {{ Js::from(route('checkin.sync')) }};
    const tokenUrl = {{ Js::from(route('checkin.token')) }};
    const currentEventId = {{ Js::from(app('currentEvent')->id) }};
    const statsUrl = {{ Js::from(route('checkin.stats')) }};
    const isAdmin = {{ Js::from($isAdmin) }};
    const confirmName = {{ Js::from((bool) app('currentEvent')->checkin_confirm_name) }};

    let html5QrCode;
    let scanning = false;
    let lastScannedToken = null;
    let missStreak = 0;
    let scanStruggleTimer;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    // The check-in decision itself is made by the server whenever there is a connection.
    // Only when the request can't reach the server (no signal, or the session needs a login)
    // is the scan decided from the guest list saved on this phone and queued for later sync.
    function escapeAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Optional safety step: show the guest's name and ask before recording the check-in.
    async function confirmGuest(raw) {
        let name = null;
        try {
            if (await cacheIsForThisEvent()) {
                const g = await dbGet('guests', tokenFrom(raw));
                if (g) { if (g.checked_in_at) return true; name = g.name; }
            }
        } catch (e) { /* no saved list */ }

        if (!name && navigator.onLine) {
            try {
                const r = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ token: raw, preview: true }),
                });
                if (r.ok) { const d = await r.json(); if (d.already) return true; name = d.name; }
            } catch (e) { /* fall through: just check in */ }
        }

        return name ? window.confirm('Check in ' + name + '?') : true;
    }

    async function verifyToken(token) {
        if (confirmName && !(await confirmGuest(token))) {
            lastScannedToken = null;
            return;
        }

        if (!navigator.onLine) {
            return renderResult(await localVerify(token));
        }

        try {
            const res = await fetch(verifyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ token: token }),
            });

            if (res.status === 404) {
                // The server answered: this card isn't in this event (or it was cancelled).
                const miss = await res.json().catch(function () { return {}; });
                return renderResult({ found: false, revoked: !!miss.revoked });
            }
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json();
            if (data.found) {
                await rememberServerCheckin(token, data);
            }
            renderResult(data);
        } catch (e) {
            renderResult(await localVerify(token));
        }
    }

    function showScanToast(message, kind) {
        // A fixed-position toast at the top of the screen, so the operator gets
        // an unmissable notification regardless of scroll position — the camera
        // preview can push the inline result card below the fold, and this
        // doesn't depend on looking at any particular part of the page.
        const existing = document.getElementById('scanToast');
        if (existing) existing.remove();

        const styles = {
            success: 'bg-green-600 text-white',
            warning: 'bg-red-600 text-white',
            error: 'bg-gray-800 text-white',
        };

        const toast = document.createElement('div');
        toast.id = 'scanToast';
        toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-lg shadow-lg text-sm font-semibold text-center max-w-[90%] '
            + (styles[kind] || styles.success);
        toast.innerHTML = message;
        document.body.appendChild(toast);

        setTimeout(function () {
            if (toast.parentNode) toast.remove();
        }, kind === 'warning' ? 5000 : 3000);
    }

    function renderResult(data) {
        const el = document.getElementById('resultCard');
        el.classList.remove('hidden');
        el.className = 'card p-5 mt-4'; // reset any alert styling from a previous scan

        // Every result closes the camera — one scan attempt per guest, whether
        // it succeeds, is a duplicate, or isn't recognized at all. The operator
        // deliberately taps "Start camera" again for the next attempt, rather
        // than it staying open and possibly catching a stray scan before
        // they're ready to move on.
        stopScanning();

        if (data.noCache) {
            el.innerHTML = '<div class="text-amber-700 font-semibold"><i class="fa-solid fa-triangle-exclamation"></i> No signal and no guest list saved on this phone.</div>'
                + '<div class="text-gray-600 text-sm mt-1">Tap <strong>Prepare for offline</strong> while you have internet, before the event.</div>';
            showScanToast('<i class="fa-solid fa-triangle-exclamation"></i> No saved guest list', 'error');
            return;
        }

        if (!data.found && data.revoked) {
            el.classList.add('border-2', 'border-red-600', 'bg-red-50');
            el.innerHTML = '<div class="text-red-700 font-bold text-lg"><i class="fa-solid fa-ban"></i> CARD CANCELLED</div><div class="text-red-700 text-sm">This card was replaced or withdrawn by the hosts. Do not admit on this card.</div>';
            showScanToast('<i class="fa-solid fa-ban"></i> CARD CANCELLED', 'warning');
            return;
        }

        if (!data.found) {
            el.innerHTML = '<div class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> No matching invitation found.</div>';
            showScanToast('<i class="fa-solid fa-circle-xmark"></i> No matching invitation found', 'error');
            return;
        }

        if (data.already) {
            el.classList.add('border-2', 'border-red-600', 'bg-red-50');
            el.innerHTML = '<div class="text-red-700 font-bold text-lg"><i class="fa-solid fa-triangle-exclamation"></i> ALREADY CHECKED IN</div>'
                + (data.name ? '<div class="text-red-800 text-sm font-semibold mt-1">' + escapeHtml(data.name) + '</div>' : '')
                + '<div class="text-red-600 text-sm">at ' + escapeHtml(data.checked_in_at) + (data.checked_in_by && isAdmin ? ' · by ' + escapeHtml(data.checked_in_by) : '') + '</div>';
            showScanToast('<i class="fa-solid fa-triangle-exclamation"></i> ALREADY CHECKED IN', 'warning');
            return;
        }

        el.innerHTML = '<div class="text-green-600 font-bold text-lg"><i class="fa-solid fa-circle-check"></i> Checked in</div>'
            + '<div class="text-gray-800 text-sm font-semibold mt-1">' + escapeHtml(data.name) + '</div>'
            + '<div class="text-gray-600 text-sm">at ' + escapeHtml(data.checked_in_at) + '</div>'
            + (data.seat ? '<div class="mt-2 inline-block rounded-lg px-3 py-1 text-base font-bold" style="background:var(--primary);color:#fff;"><i class="fa-solid fa-chair"></i> ' + escapeHtml(data.seat) + '</div>' : '')
            + (data.people > 1 ? '<div class="text-gray-700 text-sm mt-1"><i class="fa-solid fa-user-group"></i> ' + data.people + ' people on this card</div>' : '')
            + (data.group ? '<div class="text-gray-500 text-xs">Group: ' + escapeHtml(data.group) + '</div>' : '')
            + (data.meal ? '<div class="text-gray-500 text-xs">Meal: ' + escapeHtml(data.meal) + '</div>' : '')
            + (data.offline ? '<div class="text-amber-700 text-xs mt-1"><i class="fa-solid fa-cloud-arrow-up"></i> Saved on this phone — uploads when signal returns.</div>' : '');
        showScanToast('<i class="fa-solid fa-circle-check"></i> Checked in', 'success');

        addArrival(data.id, data.name, data.checked_in_at, data.seat);
        bumpCheckedInCount();
    }

    function addArrival(id, name, checkedInAt, seat) {
        const list = document.getElementById('arrivalsList');
        const empty = document.getElementById('noArrivals');
        if (empty) empty.remove();

        const undoUrl = id ? undoUrlTemplate.replace('__ID__', id) : null;
        const row = document.createElement('div');
        row.className = 'flex justify-between items-center border rounded-lg px-3 py-2';
        row.innerHTML = '<span class="text-sm">' + escapeHtml(name) + (seat ? ' <span class="text-xs text-gray-400">· ' + escapeHtml(seat) + '</span>' : '') + '</span>'
            + '<div class="flex items-center gap-2">'
            + '<span class="text-xs text-gray-500">' + escapeHtml(checkedInAt) + '</span>'
            + (!undoUrl ? '<span class="text-[10px] text-amber-700">waiting to sync</span>' : !isAdmin ? '' : '<form method="POST" action="' + undoUrl + '" data-confirm="Remove ' + escapeHtml(name) + '\'s check-in? They\'ll show as not-yet-arrived again." data-confirm-title="Undo check-in?" data-confirm-button="Undo" data-confirm-icon="fa-rotate-left">'
            + '<input type="hidden" name="_token" value="' + csrfToken + '">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600" title="Undo check-in"><i class="fa-solid fa-rotate-left"></i></button>'
            + '</form>')
            + '</div>';
        list.prepend(row);
    }

    function bumpCheckedInCount() {
        const el = document.getElementById('checkinCount');
        if (!el) return;
        el.textContent = String(parseInt(el.textContent, 10) + 1);
    }

    document.getElementById('startScanBtn').addEventListener('click', function () {
        if (scanning) return; // already running — ignore a stray second tap

        if (typeof Html5Qrcode === 'undefined') {
            alert('The QR scanner library failed to load (likely a network/ad-blocker issue). Try refreshing the page, or use the search box instead.');
            return;
        }

        html5QrCode = new Html5Qrcode('qr-reader');

        function armStruggleHint() {
            clearTimeout(scanStruggleTimer);
            document.getElementById('scanStruggleHint').classList.add('hidden');
            scanStruggleTimer = setTimeout(function () {
                document.getElementById('scanStruggleHint').classList.remove('hidden');
            }, 6000);
        }

        html5QrCode.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 280, height: 280 },
                // Uses the phone's native barcode detector when the browser
                // supports one (most current Android Chrome) instead of the
                // pure-JS decoder — meaningfully faster and more reliable,
                // especially for screen-to-camera scans (glare, moiré from a
                // guest's own phone screen) where the JS decoder alone often
                // fails silently. Falls back to the JS decoder automatically
                // wherever the native API isn't available.
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            },
            function (decodedText) {
                // The scanner keeps decoding the same code every ~100ms while it's
                // in view. This fires the check only once per "presentation" of a
                // card — from when it enters view until it's pulled away — rather
                // than on a fixed timer. A timer-based cooldown alone caused the
                // alert to keep re-popping every couple of seconds for as long as
                // an operator held a card steady in frame, with no real break.
                // The miss callback below tracks when the code briefly drops out
                // of view (card removed), which is what actually clears the lock —
                // holding it continuously in view now only triggers one request.
                armStruggleHint(); // a successful decode resets the "stuck" clock
                missStreak = 0;

                if (decodedText === lastScannedToken) {
                    return;
                }
                lastScannedToken = decodedText;

                verifyToken(decodedText);
            },
            function () {
                // Per-frame scan miss. A few consecutive misses (roughly half a
                // second at 10fps) means the code has actually left the frame —
                // e.g. the operator pulled the card away — so the same card can
                // trigger a fresh check the next time it's shown. A single stray
                // miss (a blurry frame, brief motion) doesn't count, to avoid
                // resetting the lock while a card is still genuinely in view.
                missStreak += 1;
                if (missStreak > 5) {
                    lastScannedToken = null;
                }
            }
        ).then(function () {
            scanning = true;
            document.getElementById('startScanBtn').classList.add('hidden');
            document.getElementById('stopScanBtn').classList.remove('hidden');
            armStruggleHint();
        }).catch(function (err) {
            alert('Could not start the camera: ' + (err && err.message ? err.message : err) + '\n\nCheck camera permissions and try again, or use the search box instead.');
        });
    });

    function stopScanning() {
        if (html5QrCode && scanning) {
            scanning = false;
            lastScannedToken = null;
            missStreak = 0;
            clearTimeout(scanStruggleTimer);
            document.getElementById('scanStruggleHint').classList.add('hidden');
            html5QrCode.stop().then(function () {
                document.getElementById('startScanBtn').classList.remove('hidden');
                document.getElementById('stopScanBtn').classList.add('hidden');
            });
        }
    }

    document.getElementById('stopScanBtn').addEventListener('click', stopScanning);

    let searchTimer;
    document.getElementById('searchInput').addEventListener('input', function (e) {
        clearTimeout(searchTimer);
        const q = e.target.value;
        searchTimer = setTimeout(async function () {
            let results;
            try {
                if (!navigator.onLine) throw new Error('offline');
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                results = await res.json();
            } catch (err) {
                results = await localSearch(q);
            }

            const container = document.getElementById('searchResults');
            if (!results.length) {
                container.innerHTML = '<p class="text-xs text-gray-400">No matches.</p>';
                return;
            }
            container.innerHTML = results.map(function (r) {
                const badge = r.checked_in
                    ? '<span class="text-xs text-amber-600">Checked in ' + escapeHtml(r.checked_in_at) + '</span>'
                    : '<button type="button" class="search-checkin-btn btn btn-primary !py-1 !px-2 text-xs" data-token="' + escapeAttr(r.invite_token) + '">Check in</button>';
                return '<div class="flex justify-between items-center border rounded-lg px-3 py-2"><span class="text-sm">' + escapeHtml(r.name)
                    + (r.code ? ' <span class="text-[11px] text-gray-400 tracking-wider">' + escapeHtml(r.code) + '</span>' : '')
                    + (r.seat ? ' <span class="text-[11px] text-gray-400">· ' + escapeHtml(r.seat) + '</span>' : '') + '</span>' + badge + '</div>';
            }).join('');
        }, 300);
    });

    // One listener for every "Check in" button in the results (the buttons are rebuilt on each search).
    document.getElementById('searchResults').addEventListener('click', function (e) {
        const btn = e.target.closest('.search-checkin-btn');
        if (btn) verifyToken(btn.dataset.token);
    });

    // ================= Offline support =================
    // The guest list lives in IndexedDB on this phone (name, last 4 phone digits, card token,
    // card type, check-in time — nothing else). Scans made without signal are queued there and
    // uploaded in one batch later; the server then applies them first-scan-wins.

    const DB_NAME = 'fanikisha-checkin';
    let dbPromise = null;

    function openDb() {
        if (!dbPromise) {
            dbPromise = new Promise(function (resolve, reject) {
                const req = indexedDB.open(DB_NAME, 1);
                req.onupgradeneeded = function () {
                    const db = req.result;
                    db.createObjectStore('guests', { keyPath: 'token' });
                    db.createObjectStore('queue', { keyPath: 'token' });
                    db.createObjectStore('meta', { keyPath: 'key' });
                };
                req.onsuccess = function () { resolve(req.result); };
                req.onerror = function () { reject(req.error); };
            });
        }
        return dbPromise;
    }

    function idb(store, mode, fn) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                const tx = db.transaction(store, mode);
                const result = fn(tx.objectStore(store));
                tx.oncomplete = function () { resolve(result && 'result' in result ? result.result : undefined); };
                tx.onerror = function () { reject(tx.error); };
                tx.onabort = function () { reject(tx.error); };
            });
        });
    }

    const dbGet = function (store, key) { return idb(store, 'readonly', function (s) { return s.get(key); }); };
    const dbPut = function (store, value) { return idb(store, 'readwrite', function (s) { return s.put(value); }); };
    const dbDelete = function (store, key) { return idb(store, 'readwrite', function (s) { return s.delete(key); }); };
    const dbAll = function (store) { return idb(store, 'readonly', function (s) { return s.getAll(); }); };
    const dbClear = function (store) { return idb(store, 'readwrite', function (s) { return s.clear(); }); };

    function displayTime(date) {
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
            + ', ' + date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // A scanned QR holds the card's full link; the token is its last path segment.
    function tokenFrom(raw) {
        const parts = String(raw).trim().replace(/\/+$/, '').split('/');
        return parts[parts.length - 1];
    }

    async function cacheIsForThisEvent() {
        const meta = await dbGet('meta', 'cache');
        return meta && meta.eventId === currentEventId;
    }

    async function localVerify(raw) {
        const token = tokenFrom(raw);

        if (!(await cacheIsForThisEvent())) {
            return { found: false, noCache: true };
        }

        const guest = await dbGet('guests', token);
        if (!guest) {
            return { found: false };
        }

        // Same guest scanned twice on this phone (or already checked in when the list was saved).
        if (guest.checked_in_at) {
            return { found: true, already: true, name: guest.name, checked_in_at: guest.checked_in_at };
        }

        const now = new Date();
        guest.checked_in_at = displayTime(now);
        guest.pending = true;
        await dbPut('guests', guest);
        await dbPut('queue', { token: token, name: guest.name, scanned_at: now.toISOString() });
        refreshStatus();

        return { found: true, already: false, offline: true, id: null, name: guest.name, checked_in_at: guest.checked_in_at, seat: guest.seat, people: guest.people };
    }

    // Keeps the saved list in step with check-ins the server just confirmed, so a later
    // offline scan of the same card still says "already checked in".
    async function rememberServerCheckin(raw, data) {
        try {
            const token = tokenFrom(raw);
            const guest = await dbGet('guests', token);
            if (guest) {
                guest.checked_in_at = data.checked_in_at;
                guest.pending = false;
                await dbPut('guests', guest);
            }
        } catch (e) { /* no saved list yet — nothing to keep in step */ }
    }

    async function localSearch(q) {
        if (!(await cacheIsForThisEvent())) return [];
        const needle = q.trim().toLowerCase();
        const guests = await dbAll('guests');

        return guests
            .filter(function (g) {
                if (!needle) return true;
                return g.name.toLowerCase().indexOf(needle) !== -1
                    || (g.phone_last && needle.length >= 4 && g.phone_last === needle.replace(/\D+/g, '').slice(-4))
                    || (g.code && g.code.toLowerCase() === needle.replace(/[^a-z0-9]/g, ''))
                    || g.token.toLowerCase() === needle;
            })
            .slice(0, 20)
            .map(function (g) {
                return { invite_token: g.token, name: g.name, code: g.code, seat: g.seat, checked_in: !!g.checked_in_at, checked_in_at: g.checked_in_at };
            });
    }

    function setMsg(text, isError) {
        const el = document.getElementById('offlineMsg');
        el.textContent = text;
        el.className = 'text-xs mt-2 ' + (isError ? 'text-red-600' : 'text-gray-500');
    }

    async function refreshStatus() {
        const net = document.getElementById('netStatus');
        if (navigator.onLine) {
            net.className = 'badge badge-admin';
            net.innerHTML = '<i class="fa-solid fa-wifi text-[10px]"></i>&nbsp;Online';
        } else {
            net.className = 'badge';
            net.style.background = '#fbe9e8';
            net.style.color = '#b23a32';
            net.innerHTML = '<i class="fa-solid fa-plane text-[10px]"></i>&nbsp;Offline';
        }

        try {
            const meta = await dbGet('meta', 'cache');
            const sameEvent = meta && meta.eventId === currentEventId;
            const guests = sameEvent ? await dbAll('guests') : [];
            const queue = await dbAll('queue');
            const sync = await dbGet('meta', 'sync');

            document.getElementById('cachedCount').textContent = guests.length;
            document.getElementById('queueCount').textContent = queue.length;
            document.getElementById('lastSync').textContent = sync ? displayTime(new Date(sync.at)) : 'never';
        } catch (e) { /* IndexedDB unavailable (e.g. private mode) — offline mode just stays off */ }
    }

    // Asks the service worker to keep a copy of this page and its files, so the page itself
    // opens with no signal. (The very first visit isn't covered by the worker yet — this makes sure it is.)
    async function cachePageForOffline() {
        if (!('serviceWorker' in navigator)) return;
        // `ready` never resolves if no worker was registered (e.g. plain-http dev), so don't wait forever.
        const reg = await Promise.race([
            navigator.serviceWorker.ready,
            new Promise(function (resolve) { setTimeout(function () { resolve(null); }, 5000); }),
        ]);
        if (!reg || !(reg.active || navigator.serviceWorker.controller)) return;

        const urls = [location.pathname + location.search]
            .concat(Array.from(document.querySelectorAll('link[rel="stylesheet"][href], script[src]')).map(function (n) { return n.href || n.src; }));

        await new Promise(function (resolve) {
            const channel = new MessageChannel();
            channel.port1.onmessage = function () { resolve(); };
            (reg.active || navigator.serviceWorker.controller).postMessage({ type: 'CACHE_URLS', urls: urls }, [channel.port2]);
            setTimeout(resolve, 15000);
        });
    }

    async function prepareOffline() {
        const btn = document.getElementById('prepareBtn');
        if (!navigator.onLine) {
            setMsg('You need an internet connection to prepare. Try again when you have signal.', true);
            return;
        }

        btn.disabled = true;
        setMsg('Saving the guest list on this phone…');

        try {
            const res = await fetch(guestListUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            // Keep any unsynced scans: their guests stay marked as checked in locally.
            const queue = await dbAll('queue');
            const pending = {};
            queue.forEach(function (q) { pending[q.token] = true; });

            await dbClear('guests');
            for (const g of data.guests) {
                g.pending = !!pending[g.token];
                await dbPut('guests', g);
            }
            await dbPut('meta', { key: 'cache', eventId: data.event_id, savedAt: data.generated_at });

            await cachePageForOffline();
            setMsg('Ready — ' + data.guests.length + ' guest(s) saved. Scanning now works without internet on this phone.');
        } catch (e) {
            setMsg('Could not save the guest list. Check your connection and try again. If it keeps failing, log in again.', true);
        } finally {
            btn.disabled = false;
            refreshStatus();
        }
    }

    let syncing = false;

    async function syncNow(silent) {
        // Claimed before any await: the "online" event and the page-load sync can fire together,
        // and two uploads of the same scans would make the second one report false conflicts.
        if (syncing) return;
        syncing = true;
        document.getElementById('syncBtn').disabled = true;

        let queue = [];

        try {
            queue = await dbAll('queue');
            if (!queue.length) {
                if (!silent) setMsg('Nothing waiting to sync.');
                return;
            }
            if (!navigator.onLine) {
                if (!silent) setMsg('No connection — ' + queue.length + ' check-in(s) are safe on this phone and will upload when signal returns.', true);
                return;
            }

            if (!silent) setMsg('Uploading ' + queue.length + ' check-in(s)…');

            // A fresh token, because this page may have been open for hours. If the login has
            // expired this fails and the queue is left untouched.
            const tokRes = await fetch(tokenUrl, { headers: { 'Accept': 'application/json' } });
            if (!tokRes.ok) throw new Error('login');
            const token = (await tokRes.json()).csrf;

            const conflicts = [];
            for (let i = 0; i < queue.length; i += 500) {
                const batch = queue.slice(i, i + 500);
                const res = await fetch(syncUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ scans: batch.map(function (q) { return { token: q.token, scanned_at: q.scanned_at }; }) }),
                });
                if (!res.ok) throw new Error(res.status === 419 || res.status === 401 ? 'login' : 'HTTP ' + res.status);

                const data = await res.json();
                for (const r of data.results) {
                    await dbDelete('queue', r.token);
                    const guest = await dbGet('guests', r.token);
                    if (guest) {
                        guest.pending = false;
                        if (r.checked_in_at) guest.checked_in_at = r.checked_in_at;
                        await dbPut('guests', guest);
                    }
                    if (r.status === 'already') conflicts.push({ name: r.name, at: r.checked_in_at });
                    if (r.status === 'unknown') conflicts.push({ name: r.revoked ? 'Cancelled card (not valid)' : 'Unrecognised card', at: null });
                }
            }

            await dbPut('meta', { key: 'sync', at: new Date().toISOString() });
            showConflicts(conflicts);
            setMsg('Synced ' + queue.length + ' check-in(s).' + (conflicts.length ? ' ' + conflicts.length + ' had already been checked in elsewhere — see the list above.' : ''));
        } catch (e) {
            if (e && e.message === 'login') {
                setMsg('Your login has expired. Log in again to upload — ' + queue.length + ' check-in(s) are safe on this phone.', true);
            } else {
                setMsg('Could not upload right now. ' + queue.length + ' check-in(s) are safe on this phone and will be retried.', true);
            }
        } finally {
            syncing = false;
            document.getElementById('syncBtn').disabled = false;
            refreshStatus();
        }
    }

    function showConflicts(conflicts) {
        const card = document.getElementById('conflictCard');
        if (!conflicts.length) return;
        document.getElementById('conflictList').innerHTML = conflicts.map(function (c) {
            return '<div>' + escapeHtml(c.name) + (c.at ? ' <span class="text-xs text-gray-500">— first checked in ' + escapeHtml(c.at) + '</span>' : '') + '</div>';
        }).join('');
        card.classList.remove('hidden');
    }

    async function clearOfflineData() {
        const queue = await dbAll('queue').catch(function () { return []; });
        const warning = queue.length
            ? queue.length + ' check-in(s) have not been uploaded yet and will be lost. Clear anyway?'
            : 'Remove the saved guest list from this phone?';
        if (!confirm(warning)) return;

        await Promise.all([dbClear('guests'), dbClear('queue'), dbClear('meta')]);
        setMsg('Offline data removed from this phone.');
        refreshStatus();
    }

    document.getElementById('prepareBtn').addEventListener('click', prepareOffline);
    document.getElementById('syncBtn').addEventListener('click', function () { syncNow(false); });
    document.getElementById('clearOfflineBtn').addEventListener('click', clearOfflineData);
    document.getElementById('dismissConflicts').addEventListener('click', function () {
        document.getElementById('conflictCard').classList.add('hidden');
    });

    window.addEventListener('online', function () { refreshStatus(); syncNow(true); });
    window.addEventListener('offline', refreshStatus);

    // Live numbers and the latest arrivals from every door.
    async function pollStats() {
        if (!navigator.onLine || document.hidden) return;
        try {
            const res = await fetch(statsUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const d = await res.json();
            document.getElementById('checkinCount').textContent = d.checked_in;
            document.getElementById('expectedCount').textContent = d.expected;
            document.getElementById('peopleIn').textContent = d.people_in;
            const box = document.getElementById('scannerStats');
            if (box && d.per_scanner.length) {
                box.innerHTML = 'Checked in by: ' + d.per_scanner.map(function (r) { return '<strong>' + escapeHtml(r.name) + '</strong> ' + r.count; }).join(' · ');
            }
            document.getElementById('liveList').innerHTML = d.recent.length ? d.recent.map(function (r) {
                return '<div class="flex justify-between"><span>' + escapeHtml(r.name) + (r.seat ? ' <span class="text-xs text-gray-400">· ' + escapeHtml(r.seat) + '</span>' : '') + '</span><span class="text-xs text-gray-400">' + escapeHtml(r.time) + (r.by && isAdmin ? ' · ' + escapeHtml(r.by) : '') + '</span></div>';
            }).join('') : '<span class="text-xs text-gray-400">No one has arrived yet.</span>';
        } catch (e) { /* offline or logged out — try again next time */ }
    }
    pollStats();
    setInterval(pollStats, 20000);

    refreshStatus();
    if (navigator.onLine) syncNow(true);
</script>
@endsection
