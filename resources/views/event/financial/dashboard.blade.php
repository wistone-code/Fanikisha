@extends('layouts.app')
@section('title', 'Financial Status — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('financial.index') }}" class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Dashboard</a>
    <a href="{{ route('financial.index', ['tab' => 'individual']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">Pledge status</a>
</div>

@php($dLine = $event->pledge_deadline ? now()->startOfDay()->diffInDays($event->pledge_deadline, false) : null)
<div class="rounded-xl p-5 mb-5 {{ $dLine !== null && $dLine < 0 ? 'bg-red-50' : ($dLine !== null && $dLine <= 7 ? 'bg-amber-50' : 'bg-green-50') }}">
    <div class="flex justify-between items-center flex-wrap gap-3">
        <div>
            <div class="text-xs uppercase font-bold opacity-70">Pledge deadline</div>
            <div class="text-2xl font-semibold font-serif mt-1">{{ $event->pledge_deadline->format('M j, Y') }}</div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold font-mono">{{ $dLine === null ? '—' : abs($dLine) }}</div>
            <div class="text-xs font-semibold">{{ $dLine === null ? 'no deadline set' : ($dLine >= 0 ? 'days remaining' : 'days overdue') }}</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-calendar-day"></i> Event day</div><div class="text-xl font-semibold mt-1">{{ $event->event_date->format('M j, Y') }}</div><div class="text-xs text-gray-400 mt-1">ceremony date</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-sack-dollar"></i> Ceremony budget</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['budget']) }}</div><div class="text-xs text-gray-400 mt-1">sum of provider budgets</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-money-bill-wave"></i> Expenditure</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['expenditure']) }}</div><div class="text-xs text-gray-400 mt-1">paid to providers</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-hand-holding-dollar"></i> Total pledge</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['total_pledged']) }}</div><div class="text-xs text-gray-400 mt-1">sum of all pledges</div></div>
    <div class="card p-5">
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-vault"></i> Collected</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['collected']) }}</div>
        <div class="h-1.5 rounded-full bg-gray-100 mt-2 overflow-hidden"><div class="h-full" style="background:var(--primary);width:{{ $stats['total_pledged'] > 0 ? min(100, $stats['collected']/$stats['total_pledged']*100) : 0 }}%"></div></div>
        <div class="text-xs text-gray-400 mt-1">sum of paid pledges</div>
    </div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-scale-balanced"></i> Pending pledge</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['remain']) }}</div><div class="text-xs text-gray-400 mt-1">total pledge − collected</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-scale-balanced"></i> Balance</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['balance']) }}</div><div class="text-xs text-gray-400 mt-1">collected − expenditure</div></div>
    <div class="card p-5">
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-arrows-left-right"></i> Budget variance</div>
        <div class="text-xl font-semibold mt-1 {{ $stats['variance'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $stats['variance'] > 0 ? '-' : '' }}{{ number_format(abs($stats['variance'])) }}</div>
        <div class="text-xs text-gray-400 mt-1">ceremony budget − collected</div>
    </div>
</div>
@endsection
