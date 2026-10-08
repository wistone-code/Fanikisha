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

@php($routeNames = ['financial' => 'financial.index', 'pledges' => 'pledges.index', 'providers' => 'providers.index', 'committees' => 'committees.index', 'schedule' => 'schedule.index', 'team' => 'team.index', 'invitations' => 'guests.index', 'settings' => 'event.settings'])
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    @foreach ($items as $item)
        @continue($item['id'] === 'home')
        @php($icons = explode(',', $item['icon']))
        <a href="{{ route($routeNames[$item['id']]) }}" class="card p-4 flex items-center gap-3 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center gap-0.5" style="background:#E7EDF1;color:var(--primary);">
                @foreach ($icons as $icon)
                <i class="fa-solid fa-{{ $icon }} {{ count($icons) > 1 ? 'text-xs' : '' }}"></i>
                @endforeach
            </div>
            <div class="font-medium">{{ $item['label'] }}</div>
        </a>
    @endforeach
</div>
@endsection
