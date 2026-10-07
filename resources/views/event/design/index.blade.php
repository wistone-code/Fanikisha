@extends('layouts.app')
@section('title', 'Card design — '.config('app.name'))

@section('content')
@include('event.guests._tabs', ['active' => 'design'])

<div class="mb-4"><h2 class="text-xl font-semibold">Card design &amp; venue</h2><p class="text-sm text-gray-500">How guests' cards look, where the event is, and the reminder on the day.</p></div>

<div class="grid lg:grid-cols-2 gap-5 items-start">

<div class="card p-5 space-y-3">
    <div class="text-sm font-semibold">Card style</div>
    <form method="POST" action="{{ route('design.card') }}" class="space-y-3 text-sm">
        @csrf @method('PATCH')
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach ($templates as $key)
            <label class="border rounded-lg p-2 text-center cursor-pointer text-xs {{ $event->card_template === $key ? 'ring-2' : '' }}" style="--tw-ring-color:var(--primary)">
                <input type="radio" name="card_template" value="{{ $key }}" class="sr-only" @checked($event->card_template === $key) onchange="this.form.querySelectorAll('label').forEach(l=>l.classList.remove('ring-2'));this.parentElement.classList.add('ring-2')">
                <div class="font-semibold">{{ \App\Services\CardTemplateService::TEMPLATES[$key]['en'] }}</div>
                <div class="text-gray-400">{{ \App\Services\CardTemplateService::TEMPLATES[$key]['sw'] }}</div>
                @if (in_array($key, $suited))<div class="text-[10px] mt-1" style="color:var(--primary)">suits {{ $event->event_type }}</div>@endif
            </label>
            @endforeach
        </div>
        <div><label class="text-xs font-semibold">Card language (guests can switch)</label>
            <select name="card_default_lang" class="w-full border rounded-lg px-3 py-2"><option value="en" @selected($event->card_default_lang === 'en')>English</option><option value="sw" @selected($event->card_default_lang === 'sw')>Kiswahili</option></select></div>
        <div><label class="text-xs font-semibold">Your message — English</label><textarea name="card_text_en" rows="2" maxlength="500" class="w-full border rounded-lg px-3 py-2">{{ $event->card_text_en }}</textarea></div>
        <div><label class="text-xs font-semibold">Ujumbe wako — Kiswahili</label><textarea name="card_text_sw" rows="2" maxlength="500" class="w-full border rounded-lg px-3 py-2">{{ $event->card_text_sw }}</textarea></div>
        <div><label class="text-xs font-semibold">Video link <span class="text-gray-400 font-normal">(YouTube plays on the card; other links open in a new tab)</span></label><input type="url" name="card_video_url" value="{{ $event->card_video_url }}" class="w-full border rounded-lg px-3 py-2"></div>
        <div><label class="text-xs font-semibold">Music link <span class="text-gray-400 font-normal">(direct .mp3 link — or upload a file below)</span></label><input type="url" name="card_music_url" value="{{ $event->card_music_url }}" class="w-full border rounded-lg px-3 py-2"></div>
        @if ($errors->hasAny(['card_video_url', 'card_music_url', 'card_template']))<p class="text-xs text-red-600">{{ $errors->first() }}</p>@endif
        <button class="btn btn-primary">Save style</button>
    </form>

    <div class="border-t pt-3 text-sm">
        <div class="font-semibold mb-1">Music file</div>
        @if ($event->card_has_music)
        <p class="text-xs text-gray-500 mb-2">Music file saved{{ $musicSize ? ' ('.number_format($musicSize / 1048576, 1).' MB)' : '' }}. Guests tap “Play music” on their card. It plays instead of the music link above.</p>
        <form method="POST" action="{{ route('design.music.remove') }}">@csrf @method('DELETE') <button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600">Remove music</button></form>
        @else
        <form method="POST" action="{{ route('design.music.upload') }}" enctype="multipart/form-data" class="flex gap-2 flex-wrap">@csrf
            <input type="file" name="music" accept="audio/*" required class="text-xs"><button class="btn btn-ghost !py-1 !px-2 text-xs">Upload (max 10 MB)</button></form>
        @error('music')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @endif
    </div>
</div>

<div class="card p-5">
    <div class="text-sm font-semibold mb-2">Your own card design</div>
    <p class="text-xs text-gray-500 mb-3">Made a card in Canva or by a designer? Upload it, then place the guest's name and QR code on it.</p>
    @if (! $event->card_has_custom_design)
    <form method="POST" action="{{ route('design.custom.upload') }}" enctype="multipart/form-data" class="flex gap-2 flex-wrap">@csrf
        <input type="file" name="design" accept="image/png,image/jpeg,image/webp" required class="text-xs"><button class="btn btn-primary !py-1 !px-3 text-xs">Upload design</button></form>
    @error('design')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    @else
    <form method="POST" action="{{ route('design.custom.layout') }}" id="layoutForm">
        @csrf @method('PATCH')
        <input type="hidden" name="layout" id="layoutInput">
        <div id="stage" class="relative border rounded-lg overflow-hidden select-none touch-none mx-auto" style="max-width:340px;line-height:0">
            <img src="{{ route('design.custom.image') }}" alt="" style="width:100%;display:block" draggable="false">
        </div>
        <p class="text-[11px] text-gray-400 mt-1 text-center">Drag the items to place them.</p>
        <div id="controls" class="mt-3 space-y-2 text-xs"></div>
        <label class="flex items-center gap-2 text-sm mt-3"><input type="checkbox" name="use_custom_design" value="1" @checked($event->use_custom_design)> Use this design on guests' cards</label>
        <div class="flex gap-2 mt-3"><button class="btn btn-primary">Save layout</button></div>
    </form>
    <form method="POST" action="{{ route('design.custom.remove') }}" class="mt-2" data-confirm="Remove your design and go back to the standard card?" data-confirm-title="Remove design?">@csrf @method('DELETE') <button class="btn btn-ghost !py-1 !px-2 text-xs text-red-600">Remove design</button></form>
    @endif
</div>

<div class="card p-5">
    <div class="text-sm font-semibold mb-2">Venue &amp; map</div>
    <form method="POST" action="{{ route('design.venue') }}" class="space-y-3 text-sm">
        @csrf @method('PATCH')
        <div class="grid grid-cols-3 gap-2">
            <div><label class="text-xs font-semibold">Start time</label><input type="time" name="event_time" value="{{ $event->event_time }}" class="w-full border rounded-lg px-2 py-2"></div>
            <div class="col-span-2"><label class="text-xs font-semibold">Venue name</label><input type="text" name="venue_name" maxlength="160" value="{{ $event->venue_name }}" class="w-full border rounded-lg px-3 py-2"></div>
        </div>
        <div><label class="text-xs font-semibold">Address / area</label><input type="text" name="venue_address" maxlength="255" value="{{ $event->venue_address }}" class="w-full border rounded-lg px-3 py-2"></div>
        <div><label class="text-xs font-semibold">Landmark — English</label><input type="text" name="landmark_note_en" maxlength="200" value="{{ $event->landmark_note_en }}" placeholder="Opposite the main market" class="w-full border rounded-lg px-3 py-2"></div>
        <div><label class="text-xs font-semibold">Alama ya karibu — Kiswahili</label><input type="text" name="landmark_note_sw" maxlength="200" value="{{ $event->landmark_note_sw }}" placeholder="Mkabala na soko kuu" class="w-full border rounded-lg px-3 py-2"></div>
        <div>
            <div class="flex gap-2 mb-2 flex-wrap">
                <input type="text" id="geoQ" placeholder="Search a place…" class="flex-1 min-w-[140px] border rounded-lg px-3 py-2 text-sm">
                <button type="button" id="geoBtn" class="btn btn-ghost !py-1 !px-2 text-xs">Find</button>
                <button type="button" id="gpsBtn" class="btn btn-ghost !py-1 !px-2 text-xs"><i class="fa-solid fa-location-crosshairs"></i> I'm here</button>
            </div>
            <div id="map" style="height:240px" class="rounded-lg border"></div>
            <p class="text-[11px] text-gray-400 mt-1">Tap the map to drop the pin guests will get directions to. Map data © OpenStreetMap.</p>
            <input type="hidden" name="venue_lat" id="latIn" value="{{ $event->venue_lat }}"><input type="hidden" name="venue_lng" id="lngIn" value="{{ $event->venue_lng }}">
        </div>
        <button class="btn btn-primary">Save venue</button>
    </form>
</div>

<div class="card p-5">
    <div class="text-sm font-semibold mb-2">Event-day reminder (SMS)</div>
    <p class="text-xs text-gray-500 mb-3">On the morning of the event, each guest who has not declined gets a text with the time, venue and their own card link. Each guest gets it once.</p>
    <form method="POST" action="{{ route('design.day-reminder') }}" class="space-y-3 text-sm">
        @csrf @method('PATCH')
        <label class="flex items-center gap-2"><input type="checkbox" name="event_day_reminder_enabled" value="1" @checked($event->event_day_reminder_enabled)> Send it automatically</label>
        <div class="flex items-center gap-2">At <input type="time" name="event_day_reminder_time" value="{{ $event->event_day_reminder_time }}" class="border rounded-lg px-2 py-1"></div>
        <div><label class="text-xs font-semibold">Message <span class="text-gray-400 font-normal">({name} {event} {date} {time} {place} {link})</span></label>
            <textarea name="event_day_reminder_message" rows="3" class="w-full border rounded-lg px-3 py-2">{{ $event->messageOrDefault('event_day_reminder') }}</textarea></div>
        <button class="btn btn-primary">Save</button>
    </form>
    <form method="POST" action="{{ route('design.day-reminder.send') }}" class="mt-2" data-confirm="Send the reminder now to every guest who has not had it? Each message uses SMS quota." data-confirm-title="Send now?" data-confirm-button="Send">@csrf <button class="btn btn-ghost !py-1 !px-2 text-xs">Send now</button></form>
</div>

</div>

@if ($event->card_has_custom_design)
<script>
(function () {
    const layout = {{ Js::from($layout) }};
    const labels = { name: 'Guest name', qr: 'QR code', table: 'Table / seat', code: 'Card code' };
    const sample = { name: 'Guest Name', qr: '', table: 'Table 5', code: 'K7M2Q' };
    const stage = document.getElementById('stage'), controls = document.getElementById('controls');
    const nodes = {};

    function fit() {
        const w = stage.clientWidth;
        Object.keys(nodes).forEach(function (k) {
            const n = nodes[k], l = layout[k];
            n.style.display = l.show ? '' : 'none';
            n.style.left = l.x + '%'; n.style.top = l.y + '%';
            if (k === 'qr') { n.style.width = l.size + '%'; }
            else { n.style.fontSize = (w * l.size / 100) + 'px'; n.style.color = l.color; }
        });
    }

    Object.keys(labels).forEach(function (k) {
        const n = document.createElement('div');
        n.style.cssText = 'position:absolute;transform:translate(-50%,-50%);cursor:grab;white-space:nowrap;font-weight:700;line-height:1.1;outline:1px dashed #0006;';
        if (k === 'qr') { n.style.aspectRatio = '1'; n.style.background = 'repeating-conic-gradient(#222 0 25%, #fff 0 50%) 0 0/16% 16%'; n.style.border = '3px solid #fff'; }
        else { n.textContent = sample[k]; }
        stage.appendChild(n); nodes[k] = n;

        let drag = false;
        n.addEventListener('pointerdown', function (e) { drag = true; n.setPointerCapture(e.pointerId); e.preventDefault(); });
        n.addEventListener('pointermove', function (e) {
            if (!drag) return;
            const r = stage.getBoundingClientRect();
            layout[k].x = Math.max(0, Math.min(100, Math.round((e.clientX - r.left) / r.width * 1000) / 10));
            layout[k].y = Math.max(0, Math.min(100, Math.round((e.clientY - r.top) / r.height * 1000) / 10));
            fit();
        });
        n.addEventListener('pointerup', function () { drag = false; });

        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 flex-wrap';
        row.innerHTML = '<label class="flex items-center gap-1 w-28"><input type="checkbox" data-k="' + k + '" data-f="show"> ' + labels[k] + '</label>'
            + 'Size <input type="range" min="2" max="' + (k === 'qr' ? 60 : 16) + '" step="0.5" data-k="' + k + '" data-f="size" class="flex-1 min-w-[80px]">'
            + (k === 'qr' ? '' : '<input type="color" data-k="' + k + '" data-f="color">');
        controls.appendChild(row);
    });

    controls.querySelectorAll('input').forEach(function (inp) {
        const l = layout[inp.dataset.k], f = inp.dataset.f;
        if (f === 'show') inp.checked = !!l.show; else inp.value = l[f];
        inp.addEventListener('input', function () {
            l[f] = f === 'show' ? inp.checked : (f === 'size' ? parseFloat(inp.value) : inp.value);
            fit();
        });
    });

    document.getElementById('layoutForm').addEventListener('submit', function () {
        document.getElementById('layoutInput').value = JSON.stringify(layout);
    });
    window.addEventListener('resize', fit);
    stage.querySelector('img').addEventListener('load', fit);
    fit();
})();
</script>
@endif

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    if (typeof L === 'undefined') { document.getElementById('map').innerHTML = '<p class="p-3 text-xs text-gray-400">The map could not load (no connection). You can still save the address above.</p>'; return; }
    const latIn = document.getElementById('latIn'), lngIn = document.getElementById('lngIn');
    const has = latIn.value !== '' && lngIn.value !== '';
    const start = has ? [parseFloat(latIn.value), parseFloat(lngIn.value)] : [-6.7924, 39.2083]; // Dar es Salaam
    const map = L.map('map').setView(start, has ? 16 : 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    let marker = has ? L.marker(start).addTo(map) : null;
    function setPin(lat, lng, zoom) {
        latIn.value = lat.toFixed(7); lngIn.value = lng.toFixed(7);
        if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng]).addTo(map);
        if (zoom) map.setView([lat, lng], zoom);
    }
    map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });
    document.getElementById('gpsBtn').addEventListener('click', function () {
        if (!navigator.geolocation) return alert('Location is not available on this phone.');
        navigator.geolocation.getCurrentPosition(function (p) { setPin(p.coords.latitude, p.coords.longitude, 17); }, function () { alert('Could not get your location.'); });
    });
    document.getElementById('geoBtn').addEventListener('click', function () {
        const q = document.getElementById('geoQ').value.trim(); if (!q) return;
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=tz&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) { if (res[0]) setPin(parseFloat(res[0].lat), parseFloat(res[0].lon), 16); else alert('No place found — tap the map instead.'); })
            .catch(function () { alert('Search is not available right now — tap the map instead.'); });
    });
})();
</script>
@endsection
