@extends('layouts.app')
@section('title', 'After the event — '.config('app.name'))

@section('content')
@include('event.guests._tabs', ['active' => 'after'])

<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div><h2 class="text-xl font-semibold">After the event</h2><p class="text-sm text-gray-500">Thank your guests, then see how it went.</p></div>
    <a href="{{ route('after.recap') }}" target="_blank" class="btn btn-ghost"><i class="fa-solid fa-chart-column"></i> Event recap</a>
</div>

<div class="card p-5 max-w-2xl">
    <div class="text-sm font-semibold mb-1">Thank-you messages (SMS)</div>
    <p class="text-xs text-gray-500 mb-4">Guests who were checked in get one message; everyone else gets a gentler “we missed you”. Each person is thanked once.</p>
    <form method="POST" action="{{ route('after.update') }}" class="space-y-3 text-sm">
        @csrf @method('PATCH')
        <div><label class="text-xs font-semibold">Signed by <span class="text-gray-400 font-normal">(use {hosts} in the message)</span></label><input type="text" name="host_names" maxlength="160" value="{{ $event->host_names }}" placeholder="Asha &amp; Juma" class="w-full border rounded-lg px-3 py-2"></div>
        <div><label class="text-xs font-semibold">For guests who came</label><textarea name="thank_you_attended_message" rows="3" class="w-full border rounded-lg px-3 py-2">{{ $event->messageOrDefault('thank_you_attended', false) }}</textarea></div>
        <p class="text-xs {{ $preview['parts'] > 1 ? 'text-amber-700' : 'text-gray-500' }}" style="margin-top:-6px">Sample (saved wording, as sent to a guest named Amina Juma): {{ $preview['length'] }} characters = <b>{{ $preview['parts'] }} SMS</b>@if ($preview['parts'] > 1). Shorten the wording or the signed-by name to fit one SMS (160 characters)@endif.<br><span class="text-gray-400">{{ $preview['text'] }}</span></p>
        <div><label class="text-xs font-semibold">For guests who could not come</label><textarea name="thank_you_absent_message" rows="3" class="w-full border rounded-lg px-3 py-2">{{ $event->messageOrDefault('thank_you_absent', false) }}</textarea></div>
        @unless ($event->isEcard())
        <label class="flex items-center gap-2"><input type="checkbox" name="thank_you_acknowledge_paid" value="1" @checked($event->thank_you_acknowledge_paid)> Also thank guests for the amount they contributed</label>
        @endunless
        <label class="flex items-start gap-2"><input type="checkbox" name="feedback_enabled" value="1" class="mt-1" @checked($event->feedback_enabled)> <span>Ask guests who came to rate the event <span class="text-gray-400">(adds a "Rate the event" link to their thank-you SMS, the short default wording still fits in one SMS; ratings can be sent from the event day for 14 days)</span></span></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="thank_you_enabled" value="1" @checked($event->thank_you_enabled)> Send automatically the morning after, at <input type="time" name="thank_you_time" value="{{ $event->thank_you_time }}" class="border rounded-lg px-2 py-1"></label>
        <button class="btn btn-primary">Save</button>
    </form>

    <div class="border-t mt-5 pt-4 text-sm">
        <p class="mb-2"><strong>{{ $attendedCount }}</strong> guest(s) who came and <strong>{{ $absentCount }}</strong> who didn't are waiting to be thanked. {{ $alreadySent }} already thanked.</p>
        <form method="POST" action="{{ route('after.send') }}" data-confirm="Send thank-you messages now? Each one uses SMS quota." data-confirm-title="Send thank-yous?" data-confirm-button="Send">
            @csrf <button class="btn btn-primary" @disabled(! $eventOver || ($attendedCount + $absentCount) === 0)><i class="fa-solid fa-heart"></i> Send thank-yous now</button>
        </form>
        @unless ($eventOver)<p class="text-xs text-gray-400 mt-2">Available once the event day has arrived.</p>@endunless
    </div>
</div>
@include('event.after._feedback', ['feedback' => $feedback, 'event' => $event])
@endsection
