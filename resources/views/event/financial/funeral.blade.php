@extends('layouts.app')
@section('title', 'Financial Status — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Dashboard</span>
</div>

<div class="rounded-xl p-5 mb-5 bg-green-50">
    <div class="text-xs uppercase font-bold opacity-70">Funeral day</div>
    <div class="text-2xl font-semibold font-serif mt-1">{{ $event->event_date->format('M j, Y') }}</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-sack-dollar"></i> Funeral budget</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['budget']) }}</div><div class="text-xs text-gray-400 mt-1">sum of providers</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-money-bill-wave"></i> Expenditure</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['expenditure']) }}</div><div class="text-xs text-gray-400 mt-1">paid to providers</div></div>
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-vault"></i> Total condolences collected</div><div class="text-xl font-semibold mt-1">{{ number_format($stats['collected']) }}</div></div>
    <div class="card p-5">
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-arrows-left-right"></i> Budget variance</div>
        <div class="text-xl font-semibold mt-1 {{ $stats['variance'] > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $stats['variance'] > 0 ? '-' : '' }}{{ number_format(abs($stats['variance'])) }}</div>
    </div>
</div>
@endsection
