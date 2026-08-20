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