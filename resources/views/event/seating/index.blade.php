@extends('layouts.app')
@section('title', 'Seating — '.config('app.name'))

@section('content')
@include('event.guests._tabs', ['active' => 'seating'])

@php($mode = $event->seating_mode)
@php($unit = $mode === 'row' ? 'Row' : 'Table')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Seating</h2>
        <p class="text-sm text-gray-500">Decide where each guest sits. Guests see their place on their card once you publish it.</p>
    </div>
    <form method="POST" action="{{ route('seating.publish') }}" class="flex items-center gap-2 text-sm">
        @csrf @method('PATCH')
        <label class="flex items-center gap-2"><input type="checkbox" name="seating_published" value="1" @checked($event->seating_published) onchange="this.form.submit()"> Show seats on cards &amp; at the door</label>
    </form>
</div>

<div class="card p-4 mb-4">
    <form method="POST" action="{{ route('seating.mode') }}" class="flex flex-wrap items-center gap-3 text-sm">
        @csrf @method('PATCH')
        <span class="font-semibold">Seating style</span>
        <select name="seating_mode" class="border rounded-lg px-3 py-2" onchange="this.form.submit()">
            <option value="none" @selected($mode === 'none')>No seating plan</option>
            <option value="zone" @selected($mode === 'zone')>Zones / areas (e.g. Family, VIP, Friends)</option>
            <option value="table" @selected($mode === 'table')>Tables</option>
            <option value="row" @selected($mode === 'row')>Rows</option>
        </select>
    </form>
</div>

@if ($mode === 'none')
<div class="card p-8 text-center text-gray-400 text-sm">Pick a seating style above to start. Zones are the simplest; tables let you set how many people fit.</div>
@else

@if (count($warnings))
<div class="card p-4 mb-4 border-2 border-amber-400 bg-amber-50 text-sm text-amber-900">
    <div class="font-semibold mb-1"><i class="fa-solid fa-triangle-exclamation"></i> Check these</div>
    <ul class="list-disc ml-5">@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
</div>
@endif

<div class="grid lg:grid-cols-3 gap-5 items-start">
    <div class="space-y-4">
        @if ($mode === 'zone' || $areas->count() || $mode !== 'zone')
        <div class="card p-4">
            <div class="text-sm font-semibold mb-2">{{ $mode === 'zone' ? 'Zones' : 'Areas (optional)' }}</div>
            @foreach ($areas as $a)
            <div class="flex justify-between items-center border rounded-lg px-3 py-2 mb-2 text-sm"><span>{{ $a->name }} <span class="text-xs text-gray-400">· {{ $a->guests->count() }} guest(s)</span></span>
                <form method="POST" action="{{ route('seating.areas.destroy', $a) }}" data-confirm="Remove {{ $a->name }}?" data-confirm-title="Remove area?">@csrf @method('DELETE')<button class="text-xs text-red-600"><i class="fa-solid fa-trash"></i></button></form></div>
            @endforeach
            <form method="POST" action="{{ route('seating.areas.store') }}" class="flex gap-2 mt-2">@csrf
                <input type="text" name="name" placeholder="e.g. VIP" required maxlength="80" class="flex-1 border rounded-lg px-3 py-2 text-sm"><button class="btn btn-primary !px-3">Add</button></form>
        </div>
        @endif

        @if ($mode !== 'zone')
        <div class="card p-4">
            <div class="text-sm font-semibold mb-2">{{ $unit }}s</div>
            @foreach ($tables as $t)
            @php($taken = $t->seatsTaken())
            <form method="POST" action="{{ route('seating.tables.update', $t) }}" class="border rounded-lg px-3 py-2 mb-2">
                @csrf @method('PATCH')
                <div class="flex gap-2 items-center">
                    <input type="text" name="name" value="{{ $t->name }}" class="flex-1 min-w-0 border rounded px-2 py-1 text-sm">
                    <input type="number" name="capacity" value="{{ $t->capacity }}" min="1" class="w-16 border rounded px-2 py-1 text-sm" title="Seats">
                    <button class="text-xs" title="Save"><i class="fa-solid fa-check"></i></button>
                    <button type="submit" form="delTable{{ $t->id }}" class="text-xs text-red-600" title="Remove"><i class="fa-solid fa-trash"></i></button>
                </div>
                <div class="h-1.5 bg-gray-100 rounded mt-2"><div class="h-1.5 rounded" style="width:{{ min(100, $t->capacity ? $taken / $t->capacity * 100 : 0) }}%;background:{{ $taken > $t->capacity ? '#dc2626' : 'var(--primary)' }}"></div></div>
                <div class="text-[11px] text-gray-500 mt-1">{{ $taken }} / {{ $t->capacity }} seats @if ($t->area)· {{ $t->area->name }}@endif</div>
            </form>
            <form id="delTable{{ $t->id }}" method="POST" action="{{ route('seating.tables.destroy', $t) }}" data-confirm="Remove {{ $t->name }}? Guests there become unseated." data-confirm-title="Remove?">@csrf @method('DELETE')</form>
            @endforeach
            <form method="POST" action="{{ route('seating.tables.store') }}" class="grid grid-cols-2 gap-2 mt-3 text-sm">@csrf
                <input type="text" name="name" placeholder="{{ $unit }} name" required maxlength="80" class="border rounded-lg px-3 py-2">
                <input type="number" name="capacity" value="8" min="1" class="border rounded-lg px-3 py-2" title="Seats">
                <select name="seating_area_id" class="border rounded-lg px-3 py-2"><option value="">No area</option>@foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                <input type="number" name="count" value="1" min="1" max="60" class="border rounded-lg px-3 py-2" title="How many to add">
                <button class="btn btn-primary col-span-2 justify-center">Add</button>
            </form>
            <form method="POST" action="{{ route('seating.auto-fill') }}" class="mt-3">@csrf <button class="btn btn-ghost w-full justify-center text-xs"><i class="fa-solid fa-wand-magic-sparkles"></i> Seat everyone without a place</button></form>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2 card overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3">Guest</th><th class="px-4 py-3">Group</th><th class="px-4 py-3">{{ $mode === 'zone' ? 'Zone' : $unit }}</th>@if ($mode !== 'zone')<th class="px-4 py-3">Seat</th>@endif<th></th></tr></thead>
            <tbody>
            @foreach ($guests as $g)
            <tr class="border-b last:border-0 align-middle">
                <td class="px-4 py-2"><div class="font-semibold">{{ $g->name }}</div><div class="text-[11px] text-gray-400">{{ $g->headcount() }} {{ $g->headcount() === 1 ? 'person' : 'people' }}{{ $g->rsvp_status === 'not_attending' ? ' · not attending' : '' }}</div></td>
                <td class="px-4 py-2"><input form="asg{{ $g->id }}" type="text" name="group_name" value="{{ $g->group_name }}" maxlength="80" placeholder="—" class="w-28 border rounded px-2 py-1 text-xs"></td>
                <td class="px-4 py-2">
                    @if ($mode === 'zone')
                    <select form="asg{{ $g->id }}" name="seating_area_id" class="border rounded px-2 py-1 text-xs"><option value="">—</option>@foreach ($areas as $a)<option value="{{ $a->id }}" @selected($g->seating_area_id === $a->id)>{{ $a->name }}</option>@endforeach</select>
                    @else
                    <select form="asg{{ $g->id }}" name="seating_table_id" class="border rounded px-2 py-1 text-xs"><option value="">—</option>@foreach ($tables as $t)<option value="{{ $t->id }}" @selected($g->seating_table_id === $t->id)>{{ $t->name }}</option>@endforeach</select>
                    @endif
                </td>
                @if ($mode !== 'zone')<td class="px-4 py-2"><input form="asg{{ $g->id }}" type="number" name="seat_number" value="{{ $g->seat_number }}" min="1" class="w-14 border rounded px-2 py-1 text-xs"></td>@endif
                <td class="px-4 py-2"><button form="asg{{ $g->id }}" class="btn btn-ghost !py-1 !px-2 text-xs">Save</button></td>
            </tr>
            @endforeach
            @if ($guests->isEmpty())<tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No guests with an active card yet.</td></tr>@endif
            </tbody>
        </table>
    </div>
</div>
@foreach ($guests as $g)
<form id="asg{{ $g->id }}" method="POST" action="{{ route('seating.assign', $g) }}" class="hidden">@csrf @method('PATCH')</form>
@endforeach
@endif
@endsection
