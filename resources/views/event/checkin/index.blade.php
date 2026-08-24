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
        <p id="scanStruggleHint" class="hidden text-xs text-amber-600 mt-2"><i class="fa-solid fa-triangle-exclamation"></i> Having trouble scanning? Move closer, raise the guest's screen brightness, or use search instead.</p>
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
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">{{ $arrival->checked_in_at->format('g:i A, M j') }}</span>
                    <form method="POST" action="{{ route('checkin.undo', $arrival) }}" data-confirm="Remove {{ $arrival->name }}'s check-in? They'll show as not-yet-arrived again." data-confirm-title="Undo check-in?">
                        @csrf @method('DELETE')
                        <button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600" title="Undo check-in"><i class="fa-solid fa-rotate-left"></i></button>
                    </form>
                </div>
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
    const undoUrlTemplate = {{ Js::from(route('checkin.undo', ['pledge' => '__ID__'])) }};

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

        if (!data.found) {
            el.innerHTML = '<div class="text-red-600 font-semibold"><i class="fa-solid fa-circle-xmark"></i> No matching invitation found.</div>';
            showScanToast('<i class="fa-solid fa-circle-xmark"></i> No matching invitation found', 'error');
            return;
        }

        if (data.already) {
            el.classList.add('border-2', 'border-red-600', 'bg-red-50');
            el.innerHTML = '<div class="text-red-700 font-bold text-lg"><i class="fa-solid fa-triangle-exclamation"></i> ALREADY CHECKED IN</div>'
                + '<div class="text-red-600 text-sm mt-1">at ' + escapeHtml(data.checked_in_at) + '</div>';
            showScanToast('<i class="fa-solid fa-triangle-exclamation"></i> ALREADY CHECKED IN', 'warning');
            return;
        }

        el.innerHTML = '<div class="text-green-600 font-bold text-lg"><i class="fa-solid fa-circle-check"></i> Checked in</div>'
            + '<div class="text-gray-600 text-sm mt-1">at ' + escapeHtml(data.checked_in_at) + '</div>';
        showScanToast('<i class="fa-solid fa-circle-check"></i> Checked in', 'success');

        addArrival(data.id, data.name, data.checked_in_at);
        bumpCheckedInCount();
    }

    function addArrival(id, name, checkedInAt) {
        const list = document.getElementById('arrivalsList');
        const empty = document.getElementById('noArrivals');
        if (empty) empty.remove();

        const undoUrl = undoUrlTemplate.replace('__ID__', id);
        const row = document.createElement('div');
        row.className = 'flex justify-between items-center border rounded-lg px-3 py-2';
        row.innerHTML = '<span class="text-sm">' + escapeHtml(name) + '</span>'
            + '<div class="flex items-center gap-2">'
            + '<span class="text-xs text-gray-500">' + escapeHtml(checkedInAt) + '</span>'
            + '<form method="POST" action="' + undoUrl + '" data-confirm="Remove ' + escapeHtml(name) + '\'s check-in? They\'ll show as not-yet-arrived again." data-confirm-title="Undo check-in?">'
            + '<input type="hidden" name="_token" value="' + csrfToken + '">'
            + '<input type="hidden" name="_method" value="DELETE">'
            + '<button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600" title="Undo check-in"><i class="fa-solid fa-rotate-left"></i></button>'
            + '</form>'
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
