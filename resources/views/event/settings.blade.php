@extends('layouts.app')
@section('title', 'Setting — '.config('app.name'))

@section('content')
<div class="mb-4"><h2 class="text-xl font-semibold">Setting</h2><p class="text-sm text-gray-500">Only admins can update event details.</p></div>

<div class="card p-6 max-w-md mb-4">
    <div class="text-sm font-semibold mb-1"><i class="fa-solid fa-comment-sms"></i> SMS quota</div>
    @if ($event->sms_quota === null)
        <p class="text-sm text-gray-600">{{ $event->sms_sent_count }} sent — no cap set (unlimited).</p>
    @else
        <p class="text-sm {{ $event->sms_sent_count >= $event->sms_quota ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
            {{ $event->sms_sent_count }} of {{ $event->sms_quota }} sent
            ({{ max(0, $event->sms_quota - $event->sms_sent_count) }} remaining)
        </p>
        @if ($event->sms_sent_count >= $event->sms_quota)
        <p class="text-xs text-red-500 mt-1">Quota reached — SMS sending is paused until it's raised.</p>
        @endif
    @endif
    <p class="text-xs text-gray-400 mt-2">This cap is set by the system admin. Contact them to request a change.</p>
</div>

@if ($event->hasFeature('cards'))
<div class="card p-6 max-w-md mb-4">
    <div class="text-sm font-semibold mb-1"><i class="fa-brands fa-whatsapp"></i> WhatsApp invitations</div>
    <p class="text-sm {{ $event->whatsappRemaining() === 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
        {{ $event->whatsapp_sent_count }} of {{ $event->whatsapp_quota }} sent
        ({{ $event->whatsappRemaining() }} remaining)
    </p>
    @if ($event->whatsappRemaining() === 0)
    <p class="text-xs text-red-500 mt-1">No WhatsApp invitations left — use SMS, or ask the system admin to add more.</p>
    @endif
    <p class="text-xs text-gray-400 mt-2">Only invitations use this quota. It is set by the system admin.</p>
</div>
@endif

<div class="card p-6 max-w-md">
    <form method="POST" action="{{ route('event.settings.update') }}" class="space-y-3">
        @csrf @method('PATCH')
        <div><label class="text-xs font-semibold">Event name</label><input type="text" name="name" value="{{ $event->name }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div>
            <label class="text-xs font-semibold">Event type</label>
            <select name="event_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                @foreach ($types as $type)
                <option value="{{ $type }}" @selected($event->event_type === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-xs font-semibold">Place</label><input type="text" name="place" value="{{ $event->place }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="text-xs font-semibold">Event date</label><input type="date" name="event_date" value="{{ $event->event_date->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @unless ($event->isEcard())
        <div><label class="text-xs font-semibold">Pledge deadline</label><input type="date" name="pledge_deadline" value="{{ $event->pledge_deadline->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @endunless
        @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save changes</button>
    </form>
</div>

<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1"><i class="fa-solid fa-palette"></i> Theme color</div>
    <p class="text-xs text-gray-500 mb-3">Pick the main color used across the app and on your guests' e-cards and payment pages. {{ $event->theme_color ? 'You are using a custom color.' : 'Right now it follows your event type.' }}</p>
    @php($themeService = app(\App\Services\EventThemeService::class))
    @php($currentColor = $event->theme_color ?: $themeService->for($event->event_type)['primary'])
    <form method="POST" action="{{ route('event.settings.theme-color') }}" class="space-y-3">
        @csrf @method('PATCH')
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Services\EventThemeService::PRESETS as $name => $hex)
            <label class="cursor-pointer" title="{{ $name }}">
                <input type="radio" name="theme_color" value="{{ $hex }}" class="sr-only peer theme-preset" @checked(strtolower($currentColor) === strtolower($hex))>
                <span class="block w-9 h-9 rounded-full border-2 border-white ring-1 ring-gray-300 peer-checked:ring-2 peer-checked:ring-offset-1 peer-checked:ring-gray-800" style="background:{{ $hex }};"></span>
            </label>
            @endforeach
        </div>
        <div>
            <label for="themeColorName" class="text-xs font-semibold">Search by color name</label>
            <input type="text" id="themeColorName" list="themeColorNames" autocomplete="off" placeholder="e.g. maroon, rose gold, emerald, #8a2d3b"
                class="w-full border rounded-lg px-3 py-2 text-sm">
            <datalist id="themeColorNames"></datalist>
            <p id="themeColorNameHint" class="text-xs text-gray-500 mt-1 hidden"></p>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-semibold">Or choose your own</label>
            <input type="color" id="customThemeColor" value="{{ $currentColor }}" class="w-10 h-8 p-0 border rounded cursor-pointer">
            <span id="customThemeHex" class="text-xs text-gray-500 font-mono">{{ strtolower($currentColor) }}</span>
        </div>
        <div class="rounded-lg px-3 py-2 text-xs text-white" id="themePreview" style="background:{{ $currentColor }};">Preview — this is how headers and buttons will look.</div>
        @error('theme_color')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <div class="flex gap-2 flex-wrap">
            <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save color</button>
        </div>
    </form>
    @if ($event->theme_color)
    <form method="POST" action="{{ route('event.settings.theme-color') }}" class="mt-2">
        @csrf @method('PATCH')
        <input type="hidden" name="theme_color" value="">
        <button class="btn btn-ghost"><i class="fa-solid fa-rotate-left"></i> Use the default for {{ $event->event_type }}</button>
    </form>
    @endif
</div>
<script>
(function () {
    const custom = document.getElementById('customThemeColor');
    const hexLabel = document.getElementById('customThemeHex');
    const preview = document.getElementById('themePreview');
    const form = custom.closest('form');

    // A custom color is sent as the form's color: add (or update) a hidden field that wins over the swatches.
    let hidden = null;
    function setColor(hex) {
        preview.style.background = hex;
        hexLabel.textContent = hex;
    }

    function useCustom(hex) {
        custom.value = hex;
        setColor(hex);
        form.querySelectorAll('.theme-preset').forEach(function (r) { r.checked = false; });
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'theme_color';
            form.appendChild(hidden);
        }
        hidden.value = hex;
    }

    custom.addEventListener('input', function () { useCustom(custom.value); });

    // ---- Search by color name (English and Swahili names, any web color name, or a #hex code) ----
    const nameInput = document.getElementById('themeColorName');
    const nameHint = document.getElementById('themeColorNameHint');
    const nameList = document.getElementById('themeColorNames');

    // Event-friendly colors, already dark enough for white text. Swahili names are search words only.
    const NAMED = {
        'navy': '#1f3a52', 'royal blue': '#3d5a99', 'ocean': '#1f7a8c', 'teal': '#1f6b6b',
        'emerald': '#2e7d5b', 'purple': '#6b3fa0', 'plum': '#7b2d5b', 'burgundy': '#8a2d3b',
        'terracotta': '#b0553a', 'gold': '#8a6a1f', 'charcoal': '#3a4750',
        'maroon': '#7a1f2b', 'wine': '#6e1f3a', 'red': '#b22222', 'crimson': '#a3162c',
        'rose gold': '#a5605a', 'rose': '#a8466a', 'pink': '#b0457a', 'hot pink': '#b0256a',
        'magenta': '#9c1f7a', 'lilac': '#7d5a9e', 'lavender': '#6f5d9e', 'violet': '#6a3fa0',
        'champagne': '#8c7350', 'bronze': '#8a5a2b', 'copper': '#9a5530', 'brown': '#6b4226',
        'chocolate': '#5c3a21', 'coffee': '#5a3e2b', 'beige': '#8a7a5c', 'cream': '#8c7d5e',
        'orange': '#b8541c', 'peach': '#b0654a', 'coral': '#b0503f', 'mustard': '#8f7414',
        'yellow': '#8f7a10', 'olive': '#5e6b2b', 'green': '#2e7d32', 'sage': '#5b7059',
        'mint': '#2f7a62', 'forest green': '#2d5a3a', 'turquoise': '#1f7f7a', 'sky blue': '#2f6f9e',
        'blue': '#1f4fa0', 'cobalt': '#1f4a8a', 'black': '#1a1a1a', 'grey': '#5a6068', 'gray': '#5a6068',
        'silver': '#5f6b75', 'white': '#5a6068',
        // Swahili
        'nyekundu': '#b22222', 'damu ya mzee': '#7a1f2b', 'waridi': '#b0457a', 'pinki': '#b0457a',
        'zambarau': '#6b3fa0', 'bluu': '#1f4fa0', 'buluu': '#1f4fa0', 'samawati': '#2f6f9e',
        'bluu bahari': '#1f3a52', 'kijani': '#2e7d32', 'kijani kibichi': '#2d5a3a', 'njano': '#8f7a10',
        'manjano': '#8f7a10', 'dhahabu': '#8a6a1f', 'machungwa': '#b8541c', 'chungwa': '#b8541c',
        'kahawia': '#6b4226', 'hudhurungi': '#6b4226', 'nyeusi': '#1a1a1a', 'kijivu': '#5a6068',
        'fedha': '#5f6b75', 'shaba': '#9a5530', 'nyeupe': '#5a6068'
    };
    Object.keys(NAMED).forEach(function (n) {
        const o = document.createElement('option');
        o.value = n;
        nameList.appendChild(o);
    });

    const ctx = document.createElement('canvas').getContext('2d');
    // Any color the browser knows (e.g. "darkslateblue"), turned into #rrggbb; null if unknown.
    function browserColor(text) {
        ctx.fillStyle = '#010203';
        ctx.fillStyle = text;
        const v = ctx.fillStyle;
        if (v === '#010203' && text.replace(/\s/g, '') !== '#010203') return null;
        return /^#[0-9a-f]{6}$/i.test(v) ? v.toLowerCase() : null;
    }

    function luminance(hex) {
        return [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16) / 255; })
            .map(function (v) { return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); })
            .reduce(function (s, v, i) { return s + v * [0.2126, 0.7152, 0.0722][i]; }, 0);
    }
    // Same rule as the server: luminance must be 0.4 or less so white text stays readable.
    function darkEnough(hex) {
        let rgb = [1, 3, 5].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
        let out = hex;
        for (let k = 0; k < 20 && luminance(out) > 0.4; k++) {
            rgb = rgb.map(function (c) { return Math.round(c * 0.9); });
            out = '#' + rgb.map(function (c) { return c.toString(16).padStart(2, '0'); }).join('');
        }
        return out;
    }

    function hint(text, isError) {
        nameHint.textContent = text;
        nameHint.classList.toggle('hidden', !text);
        nameHint.classList.toggle('text-red-600', !!isError);
        nameHint.classList.toggle('text-gray-500', !isError);
    }

    function findColor() {
        const raw = nameInput.value.trim().toLowerCase();
        if (!raw) { hint(''); return; }
        let hex = NAMED[raw] || null;
        if (!hex) {
            const hexText = /^#?[0-9a-f]{6}$/.test(raw) ? (raw[0] === '#' ? raw : '#' + raw) : raw;
            hex = browserColor(hexText) || browserColor(raw.replace(/\s+/g, ''));
        }
        if (!hex) {
            hint('Color not found. Try another name, or pick it below.', true);
            return;
        }
        const usable = darkEnough(hex);
        useCustom(usable);
        hint(usable === hex
            ? 'Found: ' + usable + '. Tap "Save color" to keep it.'
            : 'That color is too light for white text, so a darker shade was chosen: ' + usable + '. Tap "Save color" to keep it.');
    }

    nameInput.addEventListener('change', findColor);
    nameInput.addEventListener('input', function () {
        if (NAMED[nameInput.value.trim().toLowerCase()]) findColor();
    });
    nameInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); findColor(); }
    });

    form.querySelectorAll('.theme-preset').forEach(function (r) {
        r.addEventListener('change', function () {
            setColor(r.value);
            custom.value = r.value;
            if (hidden) { hidden.remove(); hidden = null; }
            nameInput.value = '';
            hint('');
        });
    });
})();
</script>

@unless ($event->isFuneral() || $event->isEcard())
<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Automatic reminders</div>
    <p class="text-xs text-gray-500 mb-3">When enabled, the outstanding-pledge reminder broadcast (from the Pledges → Reminder page) sends itself automatically on this schedule — no need to tap "SMS all".</p>
    <form method="POST" action="{{ route('event.settings.auto-reminder') }}" class="space-y-3">
        @csrf @method('PATCH')
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="reminder_auto_enabled" value="1" @checked($event->reminder_auto_enabled)>
            Enable automatic sending
        </label>
        <div>
            <label class="text-xs font-semibold">Every how many days</label>
            <input type="number" name="reminder_auto_frequency_days" min="1" max="90" value="{{ $event->reminder_auto_frequency_days }}" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-semibold">Time of day (24hr)</label>
            <input type="time" name="reminder_auto_time" value="{{ substr($event->reminder_auto_time, 0, 5) }}" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        @error('reminder_auto_frequency_days')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('reminder_auto_time')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @if ($event->reminder_auto_last_sent_at)
        <p class="text-xs text-gray-400">Last auto-sent: {{ $event->reminder_auto_last_sent_at->format('M j, Y g:i A') }}</p>
        @endif
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save automatic reminder settings</button>
    </form>
</div>
@endunless

@unless ($event->isFuneral())
<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Invitation e-card photo</div>
    <p class="text-xs text-gray-500 mb-3">Shown on the guest invitation card (e.g. a photo of the couple, celebrant, or family). JPG/PNG/WebP, up to 5MB.</p>
    @if ($event->hasCardPhoto())
    <div class="mb-3">
        <img src="{{ route('event.settings.card-photo.view') }}" class="w-24 h-24 rounded-full object-cover border" alt="Current card photo">
    </div>
    <form method="POST" action="{{ route('event.settings.card-photo.remove') }}" class="mb-4" data-confirm="Remove the card photo?" data-confirm-title="Remove photo?">
        @csrf @method('DELETE')
        <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Remove photo</button>
    </form>
    @endif
    <form method="POST" action="{{ route('event.settings.card-photo.upload') }}" enctype="multipart/form-data" class="space-y-3">
        @csrf
        <input type="file" name="card_photo" accept="image/jpeg,image/png,image/webp" required class="w-full border rounded-lg px-3 py-2 text-sm">
        @error('card_photo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-upload"></i> {{ $event->hasCardPhoto() ? 'Replace photo' : 'Upload photo' }}</button>
    </form>
</div>
@endunless

@unless ($event->isEcard())
<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Your mobile money number</div>
    <p class="text-xs text-gray-500 mb-3">Shown on each {{ $event->isFuneral() ? "contributor's" : "pledger's" }} "Pay now" page so they can send payment directly to you. Fanikisha never handles the money — this just makes it easy for them to find your number and open the right menu.</p>
    <form method="POST" action="{{ route('event.settings.payout') }}" class="space-y-3">
        @csrf @method('PATCH')
        <div>
            <label class="text-xs font-semibold">Network</label>
            <select name="payout_network" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Select network</option>
                @foreach (\App\Models\Event::NETWORK_USSD_CODES as $network => $code)
                <option value="{{ $network }}" @selected($event->payout_network === $network)>{{ $network }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold">Phone number</label>
            <input type="tel" name="payout_phone" value="{{ $event->payout_phone }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        @error('payout_network')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save payout details</button>
    </form>
</div>
@endunless

@unless ($event->isFuneral() || $event->isEcard())
<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Card setting</div>
    <p class="text-xs text-gray-500 mb-3">When a pledge amount is at or above this figure, their e-card is generated as a "Double/Couple" card instead of "Single". Leave blank to always use Single cards. Changing this only affects pledges saved from now on — existing e-cards keep their current type.</p>
    <form method="POST" action="{{ route('event.settings.couple-threshold') }}" class="space-y-3">
        @csrf @method('PATCH')
        <input type="number" name="couple_threshold_amount" value="{{ $event->couple_threshold_amount }}" step="0.01" min="0" placeholder="e.g. 100000" class="w-full border rounded-lg px-3 py-2 text-sm">
        @error('couple_threshold_amount')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save</button>
    </form>
</div>
@endunless

<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">SMS language</div>
    <p class="text-xs text-gray-500 mb-3">Which language the automated SMS messages use — payment confirmations, reminders, invitations, and the rest. Only affects the default wording; any message you've already customized yourself keeps its own wording either way. (This is separate from the app's own interface language.)</p>
    <form method="POST" action="{{ route('event.settings.sms-language') }}" class="space-y-3">
        @csrf @method('PATCH')
        <select name="sms_language" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="en" @selected($event->sms_language === 'en')>English</option>
            <option value="sw" @selected($event->sms_language === 'sw')>Swahili</option>
        </select>
        @error('sms_language')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save</button>
    </form>
</div>
@endsection
