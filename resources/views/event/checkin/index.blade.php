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
    <p class="text-sm text-gray-500">{{ $checkedInCount }} of {{ $eligibleCount }} invited guests checked in</p>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.4/html5-qrcode.min.js"></script>
<script>
    const csrfToken = {{ Js::from(csrf_token()) }};
    const verifyUrl = {{ Js::from(route('checkin.verify')) }};
    const searchUrl = {{ Js::from(route('checkin.search')) }};

    let html5QrCode;
    let scanning = false;

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

        if (!data.found) {
            el.innerHTML = '<div class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> No matching invitation found.</div>';
            return;
        }

        const status = data.already
            ? '<div class="text-amber-600 font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation"></i> Already checked in at ' + escapeHtml(data.checked_in_at) + '</div>'
            : '<div class="text-green-600 font-semibold mb-1"><i class="fa-solid fa-circle-check"></i> Checked in at ' + escapeHtml(data.checked_in_at) + '</div>';

        el.innerHTML = status
            + '<div class="text-lg font-semibold">' + escapeHtml(data.name) + '</div>'
            + '<div class="text-xs text-gray-500 mt-1">Pledged ' + escapeHtml(data.amount) + ' — Paid ' + escapeHtml(data.paid) + ' — Balance ' + escapeHtml(data.remain) + '</div>';
    }

    document.getElementById('startScanBtn').addEventListener('click', function () {
        html5QrCode = new Html5Qrcode('qr-reader');
        html5QrCode.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function (decodedText) {
                verifyToken(decodedText);
            },
            function () { /* ignore per-frame scan misses */ }
        ).then(function () {
            scanning = true;
            document.getElementById('startScanBtn').classList.add('hidden');
            document.getElementById('stopScanBtn').classList.remove('hidden');
        }).catch(function () {
            alert('Could not start the camera. Check camera permissions and try again, or use the search box instead.');
        });
    });

    document.getElementById('stopScanBtn').addEventListener('click', function () {
        if (html5QrCode && scanning) {
            html5QrCode.stop().then(function () {
                scanning = false;
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
