@extends('layouts.app')
@section('title', 'RSVP Status — '.config('app.name'))

@php($attending = $invited->where('rsvp_status', 'attending')->count())
@php($notAttending = $invited->where('rsvp_status', 'not_attending')->count())
@php($awaiting = $invited->whereNull('rsvp_status')->count())

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('guests.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Event invitation</a>
    <a href="{{ route('guests.index', ['tab' => 'meeting']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">Meeting invitation</a>
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">RSVP</span>
    @if ($isAdmin)<a href="{{ route('checkin.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Check-in</a>@endif
</div>

<div class="mb-3"><h2 class="text-xl font-semibold">RSVP status</h2><p class="text-sm text-gray-500">Only guests whose invitation has been activated can RSVP.</p></div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-circle-check"></i> Attending</div><div class="text-xl font-semibold mt-1">{{ $attending }}</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-circle-xmark"></i> Not attending</div><div class="text-xl font-semibold mt-1">{{ $notAttending }}</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-hourglass-half"></i> Awaiting response</div><div class="text-xl font-semibold mt-1">{{ $awaiting }}</div></div>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm sortable-table">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3" data-sort="text">Name</th>
                <th class="px-4 py-3" data-sort="text">RSVP</th>
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
                <td class="px-4 py-3 text-gray-500">{{ $p->rsvp_at?->format('M j, g:i A') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">No invitations activated yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
