@extends('layouts.app')
@section('title', 'Ceremony Schedule — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Ceremony schedule</h2>
        @if ($isAdmin)<p class="text-sm text-gray-500">Plan out the run of show — event and time for each item.</p>@endif
    </div>
    <div class="flex gap-2 flex-wrap">
        @if ($isAdmin && trim($broadcastMessage) !== '' && $pledgers->isNotEmpty())
        <a href="sms:{{ $pledgers->map(fn ($p) => rawurlencode($p->phone))->implode(';') }}?body={{ rawurlencode($broadcastMessage) }}" class="btn btn-primary"><i class="fa-solid fa-tower-broadcast"></i> Share schedule</a>
        @endif
        @if ($items->count())
        <a href="{{ route('schedule.export.excel') }}" class="btn btn-ghost"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('schedule.export.pdf') }}" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        @endif
        @if ($isAdmin)
        <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add item</button>
        @endif
    </div>
</div>

<div class="card overflow-x-auto mb-4">
    <table class="w-full text-sm sortable-table">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3" data-sort="text">Event</th><th class="px-4 py-3" data-sort="text">Date</th><th class="px-4 py-3" data-sort="text">Time</th>@if($isAdmin)<th class="px-4 py-3"></th>@endif</tr></thead>
        <tbody>
        @forelse ($items as $item)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $item->title }}</td>
                <td class="px-4 py-3">{{ $item->date->format('M j, Y') }}</td>
                <td class="px-4 py-3">{{ $item->time ? \Carbon\Carbon::parse($item->time)->format('g:i A') : '—' }}</td>
                @if ($isAdmin)
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="document.getElementById('editItem{{ $item->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i> Edit</button>
                    <form method="POST" action="{{ route('schedule.destroy', $item) }}" class="inline" onsubmit="return confirm('Delete this schedule item?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                @endif
            </tr>
            @if ($isAdmin)
            <div id="editItem{{ $item->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-4">Edit schedule item</h3>
                    <form method="POST" action="{{ route('schedule.update', $item) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="text-xs font-semibold">Event</label><input type="text" name="title" value="{{ $item->title }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs font-semibold">Date</label><input type="date" name="date" value="{{ $item->date->format('Y-m-d') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                            <div><label class="text-xs font-semibold">Time</label><input type="time" name="time" value="{{ $item->time }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="document.getElementById('editItem{{ $item->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No schedule items yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if ($isAdmin)
<div class="card p-5 mb-4 max-w-xl">
    <div class="text-xs font-semibold mb-2">Broadcast message <span class="text-gray-400 font-normal">— use {event}, {place}, {date}. Write your own — there's no starter text.</span></div>
    <form method="POST" action="{{ route('schedule.message') }}" class="mb-4">
        @csrf @method('PATCH')
        <textarea name="schedule_message" rows="5" placeholder="Write the schedule announcement…" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('schedule') }}</textarea>
        @error('schedule_message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Save message</button>
    </form>
    @if (trim($broadcastMessage) === '')
    <p class="text-xs text-gray-400 border-t pt-4">Save a message above, then use "Share schedule" at the top of the page to text all pledgers.</p>
    @elseif ($pledgers->isEmpty())
    <p class="text-xs text-gray-400 border-t pt-4">No contacts with a phone number to message.</p>
    @endif
</div>
@endif

@if ($isAdmin)
<div id="addScheduleModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add schedule item</h3>
        <form method="POST" action="{{ route('schedule.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Event</label><input type="text" name="title" required placeholder="e.g. Vows, Reception" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-xs font-semibold">Date</label><input type="date" name="date" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-semibold">Time</label><input type="time" name="time" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add item</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection