@extends('layouts.app')
@section('title', 'Event Setting — '.config('app.name'))

@section('content')
<div class="mb-4"><h2 class="text-xl font-semibold">Event Setting</h2><p class="text-sm text-gray-500">Only admins can update event details.</p></div>

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
        <div><label class="text-xs font-semibold">Pledge deadline</label><input type="date" name="pledge_deadline" value="{{ $event->pledge_deadline->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save changes</button>
    </form>
</div>

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

<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Invitation e-card photo</div>
    <p class="text-xs text-gray-500 mb-3">Shown on the guest invitation card (e.g. a photo of the couple, celebrant, or family). JPG/PNG/WebP, up to 5MB.</p>
    @if ($event->hasCardPhoto())
    <div class="mb-3">
        <img src="{{ route('event.settings.card-photo.view') }}" class="w-24 h-24 rounded-full object-cover border" alt="Current card photo">
    </div>
    <form method="POST" action="{{ route('event.settings.card-photo.remove') }}" class="mb-4" onsubmit="return confirm('Remove the card photo?')">
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

<div class="card p-6 max-w-md mt-4">
    <div class="text-sm font-semibold mb-1">Your mobile money number</div>
    <p class="text-xs text-gray-500 mb-3">Shown on each pledger's "Pay now" page so they can send payment directly to you. Fanikisha never handles the money — this just makes it easy for them to find your number and open the right menu.</p>
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
            <input type="tel" name="payout_phone" value="{{ $event->payout_phone }}" placeholder="0712 345 678" class="w-full border rounded-lg px-3 py-2 text-sm">
        </div>
        @error('payout_network')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save payout details</button>
    </form>
</div>
@endsection
