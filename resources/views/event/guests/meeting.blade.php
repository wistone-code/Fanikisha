@extends('layouts.app')
@section('title', 'Guest Management — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('guests.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Event invitation</a>
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Meeting invitation</span>
</div>

<div class="grid grid-cols-1 {{ $isAdmin ? 'lg:grid-cols-2' : '' }} gap-5 items-start">
    <div>
        <div class="mb-3">
            <h2 class="text-xl font-semibold">Meeting invitation</h2>
            @if ($isAdmin)<p class="text-sm text-gray-500">Invite pledgers to a planning meeting — no payment required.</p>@endif
        </div>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3">Name</th><th class="px-4 py-3">Phone</th>@if($isAdmin)<th class="px-4 py-3"></th>@endif</tr></thead>
                <tbody>
                @forelse ($pledges as $p)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                        <td class="px-4 py-3">{{ $p->phone ?? '—' }}</td>
                        @if ($isAdmin)
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('guests.meeting.sms', $p) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</a>
                            <a href="{{ route('guests.meeting.whatsapp', $p) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No pledges yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($isAdmin)
    <div>
        <div class="mb-3"><h2 class="text-xl font-semibold">Meeting message</h2><p class="text-sm text-gray-500">Use <code>{name}</code>, <code>{event}</code>, <code>{place}</code></p></div>
        <div class="card p-5">
            <form method="POST" action="{{ route('guests.message.meeting') }}">
                @csrf @method('PATCH')
                <textarea name="meeting_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('meeting') }}</textarea>
                <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
