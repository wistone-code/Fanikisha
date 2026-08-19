@extends('layouts.app')
@section('title', 'Announcement — '.config('app.name'))

@section('content')
@if (!$isAdmin)
    <div class="mb-3"><h2 class="text-xl font-semibold">Announcement</h2></div>
    <div class="card p-5"><p class="text-sm whitespace-pre-wrap">{{ $event->messageOrDefault('announcement') }}</p></div>
@else
    <div class="mb-3"><h2 class="text-xl font-semibold">Announcement</h2><p class="text-sm text-gray-500">Use <code>{name}</code>, <code>{event}</code>, <code>{place}</code></p></div>
    <div class="card p-5 max-w-xl">
        <form method="POST" action="{{ route('guests.message.announcement') }}" class="mb-5">
            @csrf @method('PATCH')
            <textarea name="announcement_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('announcement') }}</textarea>
            <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
        </form>

        <div class="border-t pt-4">
            <form method="POST" action="{{ route('guests.broadcast-sms') }}" id="broadcastForm">
                @csrf
                <div id="phoneInputs"></div>
                <button type="button" id="broadcastBtn" class="btn btn-primary w-full justify-center">
                    <i class="fa-solid fa-address-book"></i> Broadcast SMS from phone book
                </button>
            </form>
            <p class="text-xs text-gray-400 mt-2">Opens your phone's contacts to pick recipients, where supported. Otherwise falls back to saved condolence contacts.</p>
        </div>
    </div>

    <script>
    document.getElementById('broadcastBtn').addEventListener('click', async () => {
        const form = document.getElementById('broadcastForm');
        const container = document.getElementById('phoneInputs');
        container.innerHTML = '';

        // Real phone-book access (Contact Picker API — currently Android Chrome only, and
        // only over a secure context). Opening this over plain HTTP, or a Permissions-Policy
        // that denies "contacts", would otherwise surface a browser-level "content is
        // blocked" screen — so we check isSecureContext (and wrap the whole feature
        // detection in try/catch) before ever touching the API, rather than let that
        // block reach the person. Any failure here just falls through to the server-side
        // fallback of saved contacts (see GuestController::broadcastSms).
        let hasPicker = false;
        try { hasPicker = window.isSecureContext && !!(navigator.contacts && navigator.contacts.select); }
        catch (e) { hasPicker = false; }

        if (hasPicker) {
            try {
                const picked = await navigator.contacts.select(['tel'], { multiple: true });
                const phones = picked.flatMap(c => c.tel || []).filter(Boolean);
                phones.forEach(p => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'phones[]';
                    input.value = p;
                    container.appendChild(input);
                });
            } catch (e) {
                // Picker cancelled or blocked — submit with no phones[], and the
                // server falls back to saved condolence contacts automatically.
            }
        }

        form.submit();
    });
    </script>
@endif
@endsection
