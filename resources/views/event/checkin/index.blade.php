@extends('layouts.app')
@section('title', 'Entrance Check-in — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('guests.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Event invitation</a>
    <a href="{{ route('guests.index', ['tab' => 'meeting']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">Meeting invitation</a>
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Check-in</span>
</div>

<div class="mb-4">
    <h2 class="text-xl font-semibold">Entrance Check-in</h2>
    <p class="text-sm text-gray-500"><span id="checkinCount">{{ $checkedInCount }}</span> of {{ $eligibleCount }} invited guests checked in</p>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <div class="card p-5">
        <div class="text-sm font-semibold mb-3">Scan QR code</div>
        <div id="qr-reader" class="rounded-lg overflow-hidden"></div>
        <button id="startScanBtn" class="btn btn-primary w-full justify-center mt-3"><i class="fa-solid fa-camera"></i> Start camera</button>
        <button id="stopScanBtn" class="btn btn-ghost w-full justify-center mt-2 hidden"><i class="fa-solid fa-stop"></i> Stop camera</button>
        <p class="text-xs text-gray-400 mt-2">Point the camera at the QR code on the guest's e-card.</p>
    </div>

    <div class="card p-5">
        <div class="text-sm font-semibold mb-3">Or search by name</div>
        <input type="text" id="searchInput" placeholder="Type a name…" class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <div id="searchResults" class="space-y-2 max-h-72 overflow-y-auto"></div>
        <p class="text-xs text-gray-400 mt-2">For guests without a smartphone to show a QR code.</p>
    </div>
</div>

<div id="resultCard" class="hidden card p-5 mt-4"></div>

<div class="card p-5 mt-4">
    <div class="text-sm font-semibold mb-3">Arrival log</div>
    <div id="arrivalsList" class="space-y-2 max-h-96 overflow-y-auto">
        @forelse ($arrivals as $arrival)
            <div class="flex justify-between items-center border rounded-lg px-3 py-2">
                <span class="text-sm">{{ $arrival->name }}</span>
                <span class="text-xs text-gray-500">{{ $arrival->checked_in_at->format('g:i A, M j') }}</span>
            </div>
        @empty
            <p id="noArrivals" class="text-xs text-gray-400">No one checked in yet.</p>
        @endforelse
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
<script>
    const csrfToken = {{ Js::from(csrf_token()) }};
    const verifyUrl = {{ Js::from(route('checkin.verify')) }};
    const searchUrl = {{ Js::from(route('checkin.search')) }};

    let html5QrCode;
    let scanning = false;
    let scanLocked = false;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    async function verifyToken(token) {
        try {
            const res = await fetch(verifyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ token: token }),
            });
            const data = await res.json();
            renderResult(data);
        } catch (e) {
            renderResult({ found: false });
        }
    }

    function renderResult(data) {
        const el = document.getElementById('resultCard');
        el.classList.remove('hidden');
        el.className = 'card p-5 mt-4'; // reset any alert styling from a previous scan

        if (!data.found) {
            el.innerHTML = '<div class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> No matching invitation found.</div>';
            return;
        }

        if (data.already) {
            el.classList.add('border-4', 'border-red-600', 'bg-red-50', 'animate-pulse');
            el.innerHTML = '<div class="text-red-700 font-extrabold text-2xl mb-1"><i class="fa-solid fa-triangle-exclamation"></i> ALREADY CHECKED IN</div>'
                + '<div class="text-red-600 font-semibold mb-2">at ' + escapeHtml(data.checked_in_at) + '</div>'
                + '<div class="text-lg font-semibold">' + escapeHtml(data.name) + '</div>'
                + '<div class="text-xs text-gray-500 mt-1">Pledged ' + escapeHtml(data.amount) + ' — Paid ' + escapeHtml(data.paid) + ' — Balance ' + escapeHtml(data.remain) + '</div>';
            return;
        }

        el.innerHTML = '<div class="text-green-600 font-semibold mb-1"><i class="fa-solid fa-circle-check"></i> Checked in at ' + escapeHtml(data.checked_in_at) + '</div>'
            + '<div class="text-lg font-semibold">' + escapeHtml(data.name) + '</div>'
            + '<div class="text-xs text-gray-500 mt-1">Pledged ' + escapeHtml(data.amount) + ' — Paid ' + escapeHtml(data.paid) + ' — Balance ' + escapeHtml(data.remain) + '</div>';

        addArrival(data.name, data.checked_in_at);
        bumpCheckedInCount();
    }

    function addArrival(name, checkedInAt) {
        const list = document.getElementById('arrivalsList');
        const empty = document.getElementById('noArrivals');
        if (empty) empty.remove();

        const row = document.createElement('div');
        row.className = 'flex justify-between items-center border rounded-lg px-3 py-2';
        row.innerHTML = '<span class="text-sm">' + escapeHtml(name) + '</span>'
            + '<span class="text-xs text-gray-500">' + escapeHtml(checkedInAt) + '</span>';
        list.prepend(row);
    }

    function bumpCheckedInCount() {
        const el = document.getElementById('checkinCount');
        if (!el) return;
        el.textContent = String(parseInt(el.textContent, 10) + 1);
    }

    document.getElementById('startScanBtn').addEventListener('click', function () {
        if (typeof Html5Qrcode === 'undefined') {
            alert('The QR scanner library failed to load (likely a network/ad-blocker issue). Try refreshing the page, or use the search box instead.');
            return;
        }

        html5QrCode = new Html5Qrcode('qr-reader');
        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function (decodedText) {
                // The scanner keeps decoding the same code every ~100ms while it's
                // in view. Without this guard, a rescan of an already-checked-in
                // guest could fire several verify requests before the first one's
                // database write lands — each one would read "not checked in yet"
                // and none would ever surface the already-checked-in state. Locking
                // ensures only one request is in flight at a time, and the short
                // cooldown after it resolves gives the operator a moment to move
                // the card away before scanning resumes.
                if (scanLocked) return;
                scanLocked = true;
                if (html5QrCode) html5QrCode.pause(true);

                verifyToken(decodedText).finally(function () {
                    setTimeout(function () {
                        scanLocked = false;
                        if (html5QrCode && scanning) html5QrCode.resume();
                    }, 2000);
                });
            },
            function () { /* ignore per-frame scan misses */ }
        ).then(function () {
            scanning = true;
            document.getElementById('startScanBtn').classList.add('hidden');
            document.getElementById('stopScanBtn').classList.remove('hidden');
        }).catch(function (err) {
            alert('Could not start the camera: ' + (err && err.message ? err.message : err) + '\n\nCheck camera permissions and try again, or use the search box instead.');
        });
    });

    document.getElementById('stopScanBtn').addEventListener('click', function () {
        if (html5QrCode && scanning) {
            scanning = false;
            scanLocked = false;
            html5QrCode.stop().then(function () {
                document.getElementById('startScanBtn').classList.remove('hidden');
                document.getElementById('stopScanBtn').classList.add('hidden');
            });
        }
    });

    let searchTimer;
    document.getElementById('searchInput').addEventListener('input', function (e) {
        clearTimeout(searchTimer);
        const q = e.target.value;
        searchTimer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (results) {
                    const container = document.getElementById('searchResults');
                    if (!results.length) {
                        container.innerHTML = '<p class="text-xs text-gray-400">No matches.</p>';
                        return;
                    }
                    container.innerHTML = results.map(function (r) {
                        const badge = r.checked_in
                            ? '<span class="text-xs text-amber-600">Checked in ' + escapeHtml(r.checked_in_at) + '</span>'
                            : '<button class="btn btn-primary !py-1 !px-2 text-xs" onclick="verifyToken(' + JSON.stringify(r.invite_token) + ')">Check in</button>';
                        return '<div class="flex justify-between items-center border rounded-lg px-3 py-2"><span class="text-sm">' + escapeHtml(r.name) + '</span>' + badge + '</div>';
                    }).join('');
                });
        }, 300);
    });
</script>
@endsection
