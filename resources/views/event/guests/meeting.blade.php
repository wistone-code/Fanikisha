@extends('layouts.app')
@section('title', 'Guest Management — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('guests.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Event invitation</a>
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Meeting invitation</span>
    <a href="{{ route('guests.index', ['tab' => 'rsvp']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">RSVP</a>
    @if ($isAdmin)<a href="{{ route('checkin.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Check-in</a>@endif
</div>

<div class="mb-3"><h2 class="text-xl font-semibold">Meeting invitation</h2>@if ($isAdmin)<p class="text-sm text-gray-500">Sent as a single broadcast to everyone with a phone number on file — write your own message below, there's no starter text.</p>@endif</div>

@if (!$isAdmin)
<div class="card p-5"><p class="text-sm whitespace-pre-wrap">{{ $event->messageOrDefault('meeting') }}</p></div>
@else
<div class="card p-5 max-w-xl">
    <div class="text-xs font-semibold mb-2">Meeting message <span class="text-gray-400 font-normal">— use {event}, {place}, {date}</span></div>
    <form method="POST" action="{{ route('guests.message.meeting') }}" class="mb-5">
        @csrf @method('PATCH')
        <textarea name="meeting_message" rows="6" placeholder="Write the meeting invitation message…" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('meeting') }}</textarea>
        @error('meeting_message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
    </form>
    <div class="border-t pt-4">
        <form method="POST" action="{{ route('guests.meeting.broadcast-sms') }}" onsubmit="return confirm('Send this meeting invitation via SMS to all pledgers now?')">
            @csrf
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-tower-broadcast"></i> Broadcast SMS to all pledgers</button>
        </form>
        <p class="text-xs text-gray-400 mt-2">Sends one group SMS to every pledger with a phone number on file.</p>
    </div>
</div>
@endif
@endsection