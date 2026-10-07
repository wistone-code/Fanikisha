@extends('layouts.app')
@section('title', 'RSVP Status — '.config('app.name'))

@php($attending = $invited->where('rsvp_status', 'attending')->count())
@php($notAttending = $invited->where('rsvp_status', 'not_attending')->count())
@php($awaiting = $invited->whereNull('rsvp_status')->count())
@php($yesCards = $invited->where('rsvp_status', 'attending'))
@php($totalPeople = $yesCards->sum(fn ($p) => $p->headcount()))
@php($plusTotal = (int) $yesCards->sum('plus_ones'))
@php($partnerTotal = $yesCards->where('card_type', 'double')->count())
@php($mealCounts = $yesCards->whereNotNull('meal_choice')->groupBy('meal_choice')->map->count())

@section('content')
@include('event.guests._tabs', ['active' => 'rsvp'])

<div class="flex justify-between items-start flex-wrap gap-2"><div class="mb-3"><h2 class="text-xl font-semibold">RSVP status</h2><p class="text-sm text-gray-500">{{ $event->isEcard() ? 'Responses from guests who opened their e-card.' : 'Only guests whose invitation has been activated can RSVP.' }}</p></div>
@if ($isAdmin)<a href="{{ route('guests.export') }}" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export CSV</a>@endif</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-circle-check"></i> Attending</div><div class="text-xl font-semibold mt-1">{{ $attending }}</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-circle-xmark"></i> Not attending</div><div class="text-xl font-semibold mt-1">{{ $notAttending }}</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-hourglass-half"></i> Awaiting response</div><div class="text-xl font-semibold mt-1">{{ $awaiting }}</div></div>
</div>

<div class="card p-4 mb-4 text-sm">
    <strong>{{ $totalPeople }}</strong> people expected ({{ $attending }} invitations{{ $partnerTotal ? ", incl. {$partnerTotal} partner(s) on double cards" : '' }}{{ $plusTotal ? ($partnerTotal ? ' and' : ', incl.')." {$plusTotal} extra guest(s)" : '' }}).
    @if ($mealCounts->count())<div class="text-xs text-gray-500 mt-1">Meals: @foreach ($mealCounts as $meal => $n){{ $meal }} × {{ $n }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</div>@endif
    @if ($event->rsvp_cutoff_date)<div class="text-xs text-gray-500 mt-1">Replies close {{ $event->rsvp_cutoff_date->format('M j, Y') }}.</div>@endif
</div>

@if ($isAdmin)
<details class="card p-4 mb-4" {{ $errors->any() ? 'open' : '' }}>
    <summary class="text-sm font-semibold cursor-pointer">RSVP questions &amp; deadline</summary>
    <form method="POST" action="{{ route('rsvp.settings') }}" class="mt-3 space-y-3 text-sm">
        @csrf @method('PATCH')
        <label class="flex items-center gap-2"><input type="checkbox" name="rsvp_plus_ones_enabled" value="1" @checked($event->rsvp_plus_ones_enabled)> Let guests say how many extra people they bring</label>
        <div class="flex gap-3 flex-wrap text-xs">
            <label>Single card: up to <input type="number" name="rsvp_max_plus_single" min="0" max="10" value="{{ $event->rsvp_max_plus_single }}" class="w-14 border rounded px-2 py-1"></label>
            <label>Double card: up to <input type="number" name="rsvp_max_plus_double" min="0" max="10" value="{{ $event->rsvp_max_plus_double }}" class="w-14 border rounded px-2 py-1"></label>
        </div>
        <label class="flex items-center gap-2"><input type="checkbox" name="rsvp_meal_enabled" value="1" @checked($event->rsvp_meal_enabled)> Ask for a meal choice</label>
        <div><label class="text-xs font-semibold">Meal options <span class="text-gray-400 font-normal">(one per line)</span></label><textarea name="rsvp_meal_options" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Chicken&#10;Beef&#10;Vegetarian">{{ $event->rsvp_meal_options }}</textarea></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="rsvp_dietary_enabled" value="1" @checked($event->rsvp_dietary_enabled)> Ask about dietary needs / allergies</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="rsvp_message_enabled" value="1" @checked($event->rsvp_message_enabled)> Let guests leave a message for the hosts</label>
        <div><label class="text-xs font-semibold">Last day to reply</label> <input type="date" name="rsvp_cutoff_date" value="{{ $event->rsvp_cutoff_date?->format('Y-m-d') }}" class="border rounded-lg px-2 py-1 text-sm"></div>
        <button class="btn btn-primary">Save</button>
    </form>
</details>
@endif

<div class="card overflow-x-auto">
    <table class="w-full text-sm sortable-table">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3" data-sort="text">Name</th>
                <th class="px-4 py-3" data-sort="text">RSVP</th>
                <th class="px-4 py-3" data-sort="text">People</th>
                <th class="px-4 py-3" data-sort="text">Details</th>
                <th class="px-4 py-3" data-sort="text">Responded</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invited as $p)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                <td class="px-4 py-3">
                    @if ($p->rsvp_status === 'attending')
                    <span class="badge badge-admin"><i class="fa-solid fa-check text-[9px]"></i> Attending</span>
                    @elseif ($p->rsvp_status === 'not_attending')
                    <span class="text-xs text-gray-500">Not attending</span>
                    @else
                    <span class="text-xs text-gray-400">Awaiting response</span>
                    @endif
                </td>
                <td class="px-4 py-3">@if ($p->rsvp_status === 'attending'){{ $p->headcount() }}@if ($p->headcount() > 1)<div class="text-[11px] text-gray-400">guest{{ $p->card_type === 'double' ? ' + partner' : '' }}{{ (int) $p->plus_ones ? ' + '.(int) $p->plus_ones.' extra' : '' }}</div>@endif @else — @endif</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ collect([$p->meal_choice, $p->dietary_note ? 'Diet: '.$p->dietary_note : null, $p->host_message ? '“'.\Illuminate\Support\Str::limit($p->host_message, 80).'”' : null])->filter()->implode(' · ') ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-500 rsvp-timestamp" data-utc="{{ $p->rsvp_at?->clone()->timezone('UTC')->toIso8601String() }}">{{ $p->rsvp_at?->timezone('Africa/Dar_es_Salaam')->format('M j, g:i A') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No invitations activated yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
// Same idea as the admin Logs page: show each RSVP response time in whoever is
// actually viewing this page's own local timezone, converted client-side from
// the raw UTC instant. Rows with no response yet have no data-utc and are left
// showing "—".
document.querySelectorAll('.rsvp-timestamp[data-utc]').forEach(function (cell) {
    const iso = cell.dataset.utc;
    if (!iso) return;

    const date = new Date(iso);
    if (isNaN(date)) return;

    cell.textContent = date.toLocaleString(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
});
</script>
@endsection
