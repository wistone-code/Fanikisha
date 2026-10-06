<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $L['invited'] }} {{ $event->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@php
    $hasDetails = $maxPlus > 0 || count($mealOptions) > 0 || $event->rsvp_dietary_enabled || $event->rsvp_message_enabled;
    $date = $event->event_date->copy()->locale($lang)->translatedFormat('l, F j, Y');
    $timeText = $event->event_time ? ' '.$L['at'].' '.$event->event_time : '';
    $note = $lang === 'sw' ? ($event->landmark_note_sw ?: $event->landmark_note_en) : ($event->landmark_note_en ?: $event->landmark_note_sw);
    $custom = $event->card_text_en || $event->card_text_sw ? ($lang === 'sw' ? ($event->card_text_sw ?: $event->card_text_en) : ($event->card_text_en ?: $event->card_text_sw)) : null;
    $maps = $event->mapsUrl();
    $wallOpen = $event->photo_wall_enabled && $event->photo_wall_token && ! $event->photo_wall_uploads_blocked;
@endphp
<style>
    body{font-family:'Inter',sans-serif;}
    h1,.display{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:9px;padding:11px 16px;font-size:13.5px;font-weight:600;cursor:pointer;}
    .btn-primary{background:{{ $theme['primary'] }};color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
    .fld{width:100%;border:1px solid #d9dee2;border-radius:9px;padding:9px 11px;font-size:14px;background:#fff;}
    /* html2canvas (used for Save/Share) cannot read the oklch() colours Tailwind 4 defines, so the card uses plain hex values. */
    #card{--color-gray-50:#f9fafb;--color-gray-100:#f3f4f6;--color-gray-200:#e5e7eb;--color-gray-300:#d1d5db;--color-gray-400:#9ca3af;--color-gray-500:#6b7280;--color-gray-600:#4b5563;--color-gray-700:#374151;--color-gray-800:#1f2937;--color-gray-900:#111827;--color-white:#ffffff;--color-black:#000000;}
    #card .border,#card .border-t{border-color:#e5e7eb;}
    /* ---- card looks ---- */
    #card .hdr{background:linear-gradient(160deg, {{ $theme['primary'] }} 0%, {{ $theme['primary_dark'] }} 100%);}
    .tpl-floral .hdr{background:
        radial-gradient(circle at 12% 18%, {{ $theme['accent'] }}55 0 14px, transparent 15px),
        radial-gradient(circle at 88% 78%, {{ $theme['accent'] }}55 0 22px, transparent 23px),
        radial-gradient(circle at 80% 12%, #ffffff33 0 10px, transparent 11px),
        radial-gradient(circle at 18% 86%, #ffffff33 0 18px, transparent 19px),
        linear-gradient(160deg, {{ $theme['primary'] }} 0%, {{ $theme['primary_dark'] }} 100%);}
    .tpl-floral{border:3px solid {{ $theme['accent'] }};}
    .tpl-elegant .hdr{background:{{ $theme['primary_dark'] }};box-shadow:inset 0 0 0 2px {{ $theme['accent'] }};margin:10px 10px 0;border-radius:10px;}
    .tpl-elegant h1{letter-spacing:.04em;}
    .tpl-elegant .body{background:#fffdf8;}
    .tpl-modern .hdr{background:{{ $theme['primary'] }};text-align:left;padding-left:28px!important;}
    .tpl-modern h1{font-size:2rem;line-height:1.1;font-weight:700;}
    .tpl-modern{border-radius:6px!important;}
    .tpl-festive .hdr{background:
        radial-gradient(circle, {{ $theme['accent'] }} 0 3px, transparent 4px) 0 0/26px 26px,
        radial-gradient(circle, #ffffff66 0 2px, transparent 3px) 13px 13px/26px 26px,
        linear-gradient(160deg, {{ $theme['primary'] }} 0%, {{ $theme['primary_dark'] }} 100%);}
    .tpl-kids{border-radius:32px!important;border:4px dashed {{ $theme['accent'] }};}
    .tpl-kids .hdr{background:
        radial-gradient(circle at 15% 25%, #ffffff44 0 26px, transparent 27px),
        radial-gradient(circle at 85% 70%, {{ $theme['accent'] }}77 0 34px, transparent 35px),
        {{ $theme['primary'] }};}
    .tpl-kids h1{font-size:1.8rem;}
    /* ---- envelope ---- */
    #envelope{position:fixed;inset:0;z-index:50;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;background:radial-gradient(120% 140% at 20% 0%, {{ $theme['primary_dark'] }} 0%, #0A1319 70%);transition:opacity .6s .5s;}
    #envelope.open{opacity:0;pointer-events:none;}
    .env{position:relative;width:260px;height:170px;background:{{ $theme['accent'] }};border-radius:6px;box-shadow:0 20px 40px #0008;cursor:pointer;}
    .env .flap{position:absolute;left:0;top:0;width:0;height:0;border-left:130px solid transparent;border-right:130px solid transparent;border-top:100px solid {{ $theme['primary'] }};transform-origin:top;transition:transform .6s;z-index:3;}
    .env .paper{position:absolute;left:12px;right:12px;top:10px;height:150px;background:#fff;border-radius:4px;transition:transform .7s .5s;z-index:1;display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:13px;color:#555;padding:8px;text-align:center;}
    .env .front{position:absolute;inset:0;z-index:2;border-left:130px solid {{ $theme['accent'] }};border-right:130px solid {{ $theme['accent'] }};border-bottom:90px solid {{ $theme['primary'] }}cc;border-top:80px solid transparent;border-radius:6px;box-sizing:border-box;}
    #envelope.open .flap{transform:rotateX(180deg);z-index:0;}
    #envelope.open .paper{transform:translateY(-120px);}
    @media (prefers-reduced-motion: reduce){#envelope{display:none!important;}}
    .cd-el{position:absolute;transform:translate(-50%,-50%);white-space:nowrap;line-height:1.1;font-weight:700;text-align:center;}
</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6 gap-4" style="background:radial-gradient(120% 140% at 20% 0%, {{ $theme['primary_dark'] }} 0%, #0A1319 70%);">

    <div id="envelope" role="button" tabindex="0" aria-label="{{ $L['open'] }}">
        <div class="env" id="envBox"><div class="paper">{{ $event->name }}</div><div class="front"></div><div class="flap"></div></div>
        <div class="text-white/80 text-sm font-semibold">{{ $L['open'] }} <i class="fa-solid fa-hand-pointer"></i></div>
    </div>

    <div class="w-full max-w-sm flex justify-end gap-2 -mb-2">
        <a href="{{ route('guest.rsvp', [$pledge->invite_token, 'lang' => $lang === 'sw' ? 'en' : 'sw']) }}" class="text-xs font-semibold text-white/80 underline">{{ $L['language'] }}</a>
    </div>

    <div id="card" class="tpl-{{ $template }} bg-white rounded-2xl shadow-2xl max-w-sm w-full text-center overflow-hidden">
    @if ($layout)
        {{-- The host's own designed card, with the guest's details placed on top. --}}
        <div id="customCard" style="position:relative;line-height:0;">
            <img src="{{ route('guest.rsvp.design', $pledge->invite_token) }}" alt="" style="width:100%;height:auto;display:block;">
            @foreach (['name' => $pledge->name, 'table' => $showSeat ? $pledge->seatLabel() : null, 'code' => $pledge->card_code] as $key => $text)
                @if (($layout[$key]['show'] ?? false) && $text)
                <div class="cd-el" data-fs="{{ (float) ($layout[$key]['size'] ?? 6) }}" style="left:{{ (float) $layout[$key]['x'] }}%;top:{{ (float) $layout[$key]['y'] }}%;color:{{ preg_match('/^#[0-9a-fA-F]{6}$/', $layout[$key]['color'] ?? '') ? $layout[$key]['color'] : '#000000' }};">{{ $text }}</div>
                @endif
            @endforeach
            @if ($layout['qr']['show'] ?? true)
            <img class="qr-img" alt="QR" style="position:absolute;transform:translate(-50%,-50%);left:{{ (float) ($layout['qr']['x'] ?? 50) }}%;top:{{ (float) ($layout['qr']['y'] ?? 80) }}%;width:{{ (float) ($layout['qr']['size'] ?? 28) }}%;background:#fff;padding:2%;border-radius:4px;">
            @endif
        </div>
    @else
        <div class="hdr pt-10 pb-8 px-8 relative">
            <span class="absolute top-3 right-3 text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full" style="background:{{ $theme['accent'] }}; color:{{ $theme['primary_dark'] }};">{{ $pledge->card_type === 'double' ? $L['double_card'] : $L['single_card'] }}</span>
            @if ($event->hasCardPhoto())
            <img src="{{ route('guest.rsvp.photo', $pledge->invite_token) }}" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg mx-auto mb-4" alt="">
            @endif
            <div class="text-xs uppercase tracking-widest font-semibold mb-2" style="color:{{ $theme['accent'] }};">{{ $L['invited'] }}</div>
            <h1 class="text-2xl font-semibold text-white">{{ $event->name }}</h1>
            <div class="text-sm font-medium mt-1" style="color:{{ $theme['accent'] }};">{{ $event->event_type }}</div>
        </div>
        <div class="body p-8">
            <p class="text-sm text-gray-500 mb-1">{{ $date }}{{ $timeText }}</p>
            @if ($event->place || $event->venue_name)
            <p class="text-sm text-gray-500 mb-1">{{ $event->venueLine() }}</p>
            @if ($note)<p class="text-xs text-gray-400 mb-1"><i class="fa-solid fa-signs-post"></i> {{ $note }}</p>@endif
            @endif
            @if ($maps)
            <a href="{{ $maps }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold mb-2" style="color:{{ $theme['primary'] }};"><i class="fa-solid fa-location-dot"></i> {{ $L['view_map'] }}</a>
            @endif
            <p class="text-sm mt-3">{{ $L['dear'] }} {{ $pledge->name }}, {{ $event->isEcard() ? $L['welcome_ecard'] : $L['welcome_contrib'] }}</p>
            @if ($custom)<p class="text-sm mt-3 italic text-gray-600">{{ $custom }}</p>@endif

            @if ($showSeat)
            <div class="mt-4 rounded-xl px-4 py-2 inline-block" style="background:{{ $theme['accent'] }}33;color:{{ $theme['primary_dark'] }};">
                <div class="text-[10px] uppercase tracking-wide font-semibold">{{ $L['table'] }}</div>
                <div class="text-lg font-bold">{{ $pledge->seatLabel() }}</div>
            </div>
            @endif

            <div class="border-t mt-5 pt-5">
                <img class="qr-img mx-auto rounded-lg border" alt="QR" style="max-width:200px;width:100%;height:auto;padding:6px;background:#fff;">
                <p class="text-xs text-gray-400 mt-2">{{ $L['show_entrance'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $L['your_code'] }}: <strong class="tracking-widest">{{ $pledge->card_code }}</strong></p>
            </div>
        </div>
    @endif
    </div>

    {{-- RSVP (outside the saved image so the picture stays clean) --}}
    <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-5 text-center">
        @if (session('rsvp_error'))<p class="text-sm text-red-600 mb-2">{{ session('rsvp_error') }}</p>@endif
        @if ($errors->any())<p class="text-sm text-red-600 mb-2">{{ $errors->first() }}</p>@endif

        @if (! $rsvpOpen)
            <p class="text-sm text-gray-500">{{ $L['closed'] }}</p>
        @else
            @if ($pledge->rsvp_status === 'attending')
            <div id="rsvpDone"><p class="text-sm font-semibold" style="color:{{ $theme['primary'] }};"><i class="fa-solid fa-circle-check"></i> {{ $L['confirmed'] }}</p>
                @if ($pledge->plus_ones > 0)<p class="text-xs text-gray-500 mt-1">+{{ $pledge->plus_ones }}</p>@endif
            </div>
            @elseif ($pledge->rsvp_status === 'not_attending')
            <div id="rsvpDone"><p class="text-sm font-semibold text-gray-500">{{ $L['declined'] }}</p></div>
            @else
            <p class="text-sm font-semibold text-gray-700 mb-3" id="rsvpAsk">{{ $L['will_attend'] }}</p>
            @endif

            <form id="rsvpForm" method="POST" action="{{ route('guest.rsvp.respond', $pledge->invite_token) }}" class="{{ $pledge->rsvp_status ? 'hidden' : '' }} space-y-3 text-left">
                @csrf
                <input type="hidden" name="lang" value="{{ $lang }}">
                <div id="detailsBox" class="space-y-3 {{ $hasDetails && $pledge->rsvp_status !== 'attending' ? 'hidden' : '' }}">
                    @if ($maxPlus > 0)
                    <div><label class="text-xs font-semibold">{{ $L['extra_guests'] }}</label>
                        <select name="plus_ones" class="fld">@for ($i = 0; $i <= $maxPlus; $i++)<option value="{{ $i }}" @selected((int) old('plus_ones', $pledge->plus_ones) === $i)>{{ $i }}</option>@endfor</select></div>
                    @endif
                    @if (count($mealOptions))
                    <div><label class="text-xs font-semibold">{{ $L['meal'] }}</label>
                        <select name="meal_choice" class="fld"><option value="">—</option>@foreach ($mealOptions as $m)<option value="{{ $m }}" @selected(old('meal_choice', $pledge->meal_choice) === $m)>{{ $m }}</option>@endforeach</select></div>
                    @endif
                    @if ($event->rsvp_dietary_enabled)
                    <div><label class="text-xs font-semibold">{{ $L['dietary'] }} <span class="text-gray-400 font-normal">({{ $L['optional'] }})</span></label>
                        <input type="text" name="dietary_note" maxlength="255" value="{{ old('dietary_note', $pledge->dietary_note) }}" class="fld"></div>
                    @endif
                    @if ($event->rsvp_message_enabled)
                    <div><label class="text-xs font-semibold">{{ $L['message'] }} <span class="text-gray-400 font-normal">({{ $L['optional'] }})</span></label>
                        <textarea name="host_message" rows="2" maxlength="500" class="fld">{{ old('host_message', $pledge->host_message) }}</textarea></div>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="submit" name="response" value="attending" id="yesBtn" class="btn btn-primary flex-1"><i class="fa-solid fa-check"></i> {{ $hasDetails && ! $pledge->rsvp_status ? $L['yes'] : ($pledge->rsvp_status === 'attending' ? $L['send_response'] : $L['yes']) }}</button>
                    <button type="submit" name="response" value="not_attending" class="btn btn-ghost flex-1">{{ $L['no'] }}</button>
                </div>
            </form>
            @if ($pledge->rsvp_status)
            <button type="button" id="changeBtn" class="text-xs text-gray-400 underline mt-2">{{ $L['change'] }}</button>
            @endif
        @endif
    </div>

    <div class="flex gap-2 w-full max-w-sm flex-wrap">
        <button onclick="shareCard()" class="btn btn-primary flex-1"><i class="fa-solid fa-share-nodes"></i> {{ $L['share'] }}</button>
        <button onclick="saveCardAsImage()" class="btn btn-ghost flex-1"><i class="fa-solid fa-download"></i> {{ $L['save'] }}</button>
        <a href="{{ route('guest.rsvp.calendar', $pledge->invite_token) }}" class="btn btn-ghost flex-1"><i class="fa-solid fa-calendar-plus"></i> {{ $L['add_calendar'] }}</a>
        @if ($maps)<a href="{{ $maps }}" target="_blank" rel="noopener" class="btn btn-ghost flex-1"><i class="fa-solid fa-diamond-turn-right"></i> {{ $L['view_map'] }}</a>@endif
    </div>

    @if ($event->card_has_music || $event->card_music_url)
    <button type="button" id="musicBtn" class="btn btn-ghost w-full max-w-sm"><i class="fa-solid fa-music"></i> <span id="musicLabel">{{ $L['play'] }}</span></button>
    <audio id="bgAudio" loop preload="none" src="{{ $event->card_has_music ? route('guest.rsvp.music', $pledge->invite_token) : $event->card_music_url }}"></audio>
    @endif

    @if ($event->card_video_url)
        @if ($videoEmbed)
        <div class="w-full max-w-sm"><button type="button" id="videoBtn" class="btn btn-ghost w-full"><i class="fa-solid fa-circle-play"></i> {{ $L['video'] }}</button><div id="videoBox" class="mt-2 hidden"><iframe data-src="{{ $videoEmbed }}" class="w-full rounded-xl" style="aspect-ratio:16/9;border:0" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe></div></div>
        @else
        <a href="{{ $event->card_video_url }}" target="_blank" rel="noopener" class="btn btn-ghost w-full max-w-sm"><i class="fa-solid fa-circle-play"></i> {{ $L['video'] }}</a>
        @endif
    @endif

    @if ($wallOpen)
    <a href="{{ route('wall.show', [$event->photo_wall_token, 'c' => $pledge->invite_token]) }}" class="btn btn-ghost w-full max-w-sm"><i class="fa-solid fa-images"></i> {{ $L['photos'] }}</a>
    @endif

    <script src="{{ asset('js/qrcode.js') }}"></script>
    <script src="{{ asset('js/html2canvas.min.js') }}"></script>
    <script>
        const cardFileName = {{ Js::from(Str::slug($event->name).'-invitation.png') }};
        const cardTitle = {{ Js::from($L['invited'].' '.$event->name) }};
        const cardLink = {{ Js::from($pledge->inviteLink()) }};

        // The QR is drawn on the phone (no outside service), so it also works on a slow or blocked network.
        (function () {
            try {
                const qr = qrcode(0, 'M');
                qr.addData(cardLink);
                qr.make();
                const src = qr.createDataURL(8, 2);
                document.querySelectorAll('.qr-img').forEach(function (img) { img.src = src; });
            } catch (e) {}
        })();

        // Text on a custom-designed card scales with the card's width.
        function fitCustom() {
            const c = document.getElementById('customCard');
            if (!c) return;
            const w = c.clientWidth;
            c.querySelectorAll('.cd-el').forEach(function (el) { el.style.fontSize = (w * parseFloat(el.dataset.fs) / 100) + 'px'; });
        }
        fitCustom();
        window.addEventListener('resize', fitCustom);
        window.addEventListener('load', fitCustom);

        // Envelope: opens on tap, only once per browser session.
        (function () {
            const env = document.getElementById('envelope');
            let seen = false;
            try { seen = sessionStorage.getItem('fk_env_{{ $pledge->id }}') === '1'; } catch (e) {}
            if (seen || window.location.hash === '#card') { env.style.display = 'none'; return; }
            function openEnv() {
                env.classList.add('open');
                try { sessionStorage.setItem('fk_env_{{ $pledge->id }}', '1'); } catch (e) {}
                const a = document.getElementById('bgAudio');
                if (a) { a.play().then(function () { setMusicLabel(true); }).catch(function () {}); }
                setTimeout(function () { env.style.display = 'none'; }, 1300);
            }
            env.addEventListener('click', openEnv);
            env.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') openEnv(); });
        })();

        // RSVP form: "Yes" reveals the extra questions first when the hosts asked any.
        (function () {
            const form = document.getElementById('rsvpForm');
            if (!form) return;
            const details = document.getElementById('detailsBox');
            const yes = document.getElementById('yesBtn');
            const hasDetails = {{ Js::from($hasDetails) }};
            yes.addEventListener('click', function (e) {
                if (hasDetails && details.classList.contains('hidden')) {
                    e.preventDefault();
                    details.classList.remove('hidden');
                    yes.lastChild.textContent = ' ' + {{ Js::from($L['send_response']) }};
                }
            });
            const change = document.getElementById('changeBtn');
            if (change) change.addEventListener('click', function () {
                form.classList.remove('hidden');
                if (details) details.classList.remove('hidden');
                const done = document.getElementById('rsvpDone'); if (done) done.classList.add('hidden');
                change.classList.add('hidden');
            });
        })();

        function setMusicLabel(playing) {
            const l = document.getElementById('musicLabel');
            if (l) l.textContent = playing ? {{ Js::from($L['pause']) }} : {{ Js::from($L['play']) }};
        }
        (function () {
            const a = document.getElementById('bgAudio'); const b = document.getElementById('musicBtn');
            if (!a || !b) return;
            b.addEventListener('click', function () {
                if (a.paused) { a.play().then(function () { setMusicLabel(true); }); } else { a.pause(); setMusicLabel(false); }
            });
        })();
        (function () {
            const b = document.getElementById('videoBtn'); if (!b) return;
            b.addEventListener('click', function () {
                const box = document.getElementById('videoBox'); const f = box.querySelector('iframe');
                if (!f.src) f.src = f.dataset.src;
                box.classList.toggle('hidden');
            });
        })();

        function renderCard() {
            return html2canvas(document.getElementById('card'), { backgroundColor: '#ffffff', scale: 2, useCORS: true }).catch(function (e) {
                alert({{ Js::from($lang === 'sw' ? 'Imeshindikana kuhifadhi picha. Jaribu tena au piga picha ya skrini.' : 'Could not save the picture. Please try again or take a screenshot.') }});
                throw e;
            });
        }

        function saveCardAsImage() {
            renderCard().then(function (canvas) {
                const link = document.createElement('a');
                link.download = cardFileName;
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }

        function shareCard() {
            renderCard().then(function (canvas) {
                canvas.toBlob(async function (blob) {
                    const file = new File([blob], cardFileName, { type: 'image/png' });

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        try {
                            await navigator.share({ files: [file], title: cardTitle });
                        } catch (e) {
                            // Person cancelled the share sheet — nothing to do.
                        }
                    } else {
                        saveCardAsImage();
                    }
                }, 'image/png');
            });
        }
    </script>
</body>
</html>
