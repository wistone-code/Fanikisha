@extends('layouts.app')
@section('title', $event->name.' — '.config('app.name'))

@section('content')
<div class="rounded-2xl p-8 mb-6 text-white" style="background:radial-gradient(120% 160% at 0% 0%, #35597A 0%, var(--primary) 45%, #0B1721 100%);">
    <div class="flex justify-between items-center flex-wrap gap-6">
        <div>
            @if ($event->event_type)<div class="text-xs uppercase tracking-wide opacity-75 font-semibold mb-1">{{ $event->event_type }}</div>@endif
            <h1 class="text-3xl font-semibold mb-1">{{ $event->name }}</h1>
            <div class="text-sm opacity-90">{{ $event->event_date->format('M j, Y') }}@if($event->place) &middot; {{ $event->place }}@endif</div>
        </div>

        @if ($event->showsCountdown())
        @php($days = now()->startOfDay()->diffInDays($event->event_date, false))
        <div class="w-24 h-24 rounded-full border-4 border-white/25 flex flex-col items-center justify-center">
            <div class="text-2xl font-bold font-serif">{{ abs($days) }}</div>
            <div class="text-[9px] uppercase opacity-80">{{ $days >= 0 ? 'days left' : 'days ago' }}</div>
        </div>
        @endif
    </div>
</div>

@if ($quickLinks)
    @php($labels = app(\App\Services\NavLabelService::class)->for($event))
    @php($routeNames = ['financial' => 'financial.index', 'pledges' => 'pledges.index', 'providers' => 'providers.index', 'schedule' => 'schedule.index', 'invitations' => 'guests.index', 'settings' => 'event.settings'])
    @php($icons = ['financial' => 'chart-pie', 'pledges' => 'hand-holding-dollar', 'providers' => 'truck-fast', 'schedule' => 'calendar-days', 'invitations' => 'envelope-open-text', 'settings' => 'gear'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach ($quickLinks as $id)
        <a href="{{ route($routeNames[$id]) }}" class="card p-4 flex items-center gap-3 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:#E7EDF1;color:var(--primary);">
                <i class="fa-solid fa-{{ $icons[$id] }}"></i>
            </div>
            <div class="font-medium">{{ $labels[$id] }}</div>
        </a>
        @endforeach
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold">Total pledged</div><div class="text-2xl font-semibold mt-1">{{ number_format($stats['total_pledged']) }}</div><div class="text-xs text-gray-400 mt-1">{{ $stats['pledge_count'] }} pledges</div></div>
        <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold">Collected</div><div class="text-2xl font-semibold mt-1">{{ number_format($stats['collected']) }}</div><div class="text-xs text-gray-400 mt-1">of {{ number_format($stats['total_pledged']) }}</div></div>
        <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold">Ceremony budget</div><div class="text-2xl font-semibold mt-1">{{ number_format($stats['budget']) }}</div><div class="text-xs text-gray-400 mt-1">across providers</div></div>
    </div>
@endif
@endsection
