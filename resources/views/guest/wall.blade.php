<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Photos — {{ $event->name }}</title>
<meta name="robots" content="noindex">
<style>
    *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;background:#0A1319;color:#fff;min-height:100vh}
    header{padding:22px 16px;background:linear-gradient(160deg,{{ $theme['primary'] }},{{ $theme['primary_dark'] }});text-align:center}
    h1{margin:0;font-size:20px} .sub{opacity:.8;font-size:13px;margin-top:4px}
    main{max-width:720px;margin:0 auto;padding:16px}
    .box{background:#fff;color:#1B2429;border-radius:14px;padding:16px;margin-bottom:16px}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:12px 16px;font-weight:600;font-size:14px;background:{{ $theme['primary'] }};color:#fff;cursor:pointer;width:100%}
    .btn[disabled]{opacity:.5}
    input[type=text],input[type=password]{width:100%;border:1px solid #d9dee2;border-radius:9px;padding:10px;font-size:15px;margin:6px 0 10px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:4px}
    .grid button{padding:0;border:0;background:#1d2a32;aspect-ratio:1;overflow:hidden;cursor:pointer}
    .grid img{width:100%;height:100%;object-fit:cover;display:block}
    #lb{position:fixed;inset:0;background:#000e;display:none;align-items:center;justify-content:center;flex-direction:column;gap:10px;z-index:9}
    #lb img{max-width:96vw;max-height:80vh}
    #lb button{background:#fff2;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-size:13px}
    .msg{font-size:13px;margin-top:8px}
</style>
</head>
<body>
<header><h1>{{ $event->name }}</h1><div class="sub">Shared photos · Picha za pamoja</div></header>
<main>
@if ($guest)
    <a href="{{ route('guest.rsvp', $guest->invite_token) }}" style="display:inline-block;margin:0 0 12px;color:#fff;opacity:.9;font-size:14px;font-weight:600;text-decoration:none">&larr; Back to my card</a>
@endif
@if ($needsPin)
    <div class="box"><strong>Enter the PIN</strong><p style="font-size:13px;color:#555;margin:4px 0">Ask the hosts for the PIN to see the photos.</p>
        <form method="POST" action="{{ route('wall.pin', $event->photo_wall_token) }}">@csrf
            <input type="password" name="pin" inputmode="numeric" maxlength="12" required autofocus>
            @error('pin')<div class="msg" style="color:#b91c1c">{{ $message }}</div>@enderror
            <button class="btn">Open</button></form></div>
@elseif ($needsCard)
    <div class="box"><strong>Open this from your invitation card</strong><p style="font-size:13px;color:#555;margin:4px 0">Only invited guests can see and add photos. Please tap the “Event photos” button on your e-card.</p></div>
@else
    @if ($closedReason)
        <div class="box" style="color:#555;font-size:14px">{{ $closedReason }}</div>
    @else
        <div class="box">
            <strong>Add your photos</strong>
            <p style="font-size:12px;color:#666;margin:4px 0 10px">Up to {{ $perGuest }} photos each. Please share only photos you are happy for other guests to see.</p>
            @unless ($guest)<input type="text" id="nm" maxlength="80" placeholder="Your name (optional)">@endunless
            <input type="file" id="pick" accept="image/*" multiple style="display:none">
            <button class="btn" id="pickBtn" type="button">Choose photos</button>
            <div class="msg" id="msg"></div>
        </div>
    @endif

    <div class="grid" id="grid">
    @foreach ($photos as $p)
        <button type="button" data-id="{{ $p->id }}"><img loading="lazy" src="{{ route('wall.thumb', [$event->photo_wall_token, $p->id]) }}" alt=""></button>
    @endforeach
    </div>
    @if ($photos->isEmpty())<p style="text-align:center;opacity:.6;font-size:14px;margin-top:30px">No photos yet — be the first!</p>@endif
@endif
</main>

<div id="lb"><img id="lbImg" alt=""><div><button id="lbReport" type="button">Report</button> <button id="lbClose" type="button">Close</button></div></div>

<script>
const base = {{ Js::from(route('wall.show', $event->photo_wall_token)) }};
const uploadUrl = {{ Js::from(route('wall.upload', $event->photo_wall_token)) }};
const csrf = {{ Js::from(csrf_token()) }};
const cardToken = {{ Js::from($guest?->invite_token) }};
const maxSide = 1600;

// Photos are shrunk on the phone first, so a 6 MB camera picture uploads as a few hundred KB.
function shrink(file) {
    return new Promise(function (resolve) {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = function () {
            const ratio = Math.min(1, maxSide / Math.max(img.width, img.height));
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * ratio); c.height = Math.round(img.height * ratio);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob(function (b) { resolve(b ? new File([b], 'photo.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.82);
        };
        img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
    });
}

const pick = document.getElementById('pick'), pickBtn = document.getElementById('pickBtn'), msg = document.getElementById('msg');
if (pick) {
    pickBtn.addEventListener('click', function () { pick.click(); });
    pick.addEventListener('change', async function () {
        const files = Array.from(pick.files).slice(0, 5);
        if (!files.length) return;
        pickBtn.disabled = true; msg.textContent = 'Uploading…';
        const fd = new FormData();
        for (const f of files) fd.append('photos[]', await shrink(f));
        const nm = document.getElementById('nm'); if (nm && nm.value) fd.append('name', nm.value);
        if (cardToken) fd.append('c', cardToken);
        try {
            const res = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd });
            const data = await res.json().catch(function () { return {}; });
            msg.textContent = data.message || (res.ok ? 'Done' : 'Upload failed — try again.');
            if (res.ok) setTimeout(function () { location.reload(); }, 900);
        } catch (e) { msg.textContent = 'No connection — please try again.'; }
        pickBtn.disabled = false; pick.value = '';
    });
}

let current = null;
document.getElementById('grid')?.addEventListener('click', function (e) {
    const b = e.target.closest('button'); if (!b) return;
    current = b.dataset.id;
    document.getElementById('lbImg').src = base + '/photos/' + current;
    document.getElementById('lb').style.display = 'flex';
});
document.getElementById('lbClose').addEventListener('click', function () { document.getElementById('lb').style.display = 'none'; document.getElementById('lbImg').src = ''; });
document.getElementById('lbReport').addEventListener('click', function () {
    if (!current) return;
    fetch(base + '/photos/' + current + '/report', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
        .then(function () { alert('Thank you — the hosts will review it.'); });
});
</script>
</body>
</html>
