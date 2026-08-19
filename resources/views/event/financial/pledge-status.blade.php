@extends('layouts.app')
@section('title', 'Pledge status — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('financial.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Dashboard</a>
    <a href="{{ route('financial.index', ['tab' => 'individual']) }}" class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Pledge status</a>
</div>

@php($total = array_sum($counts))
@php($colors = ['Completed' => '#0f9d58', 'Pending' => '#c07c1e', 'Overdue' => '#d64545'])
@php($acc = 0)
@php($stops = [])
@foreach ($counts as $label => $count)
    @php($start = $total ? $acc / $total * 360 : 0)
    @php($acc += $count)
    @php($end = $total ? $acc / $total * 360 : 0)
    @php($stops[] = "{$colors[$label]} {$start}deg {$end}deg")
@endforeach

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="card p-6 text-center">
        <div class="text-xs uppercase text-gray-400 font-semibold mb-4">Pledge status breakdown</div>
        <div class="w-44 h-44 rounded-full mx-auto mb-5" style="background: {{ $total ? 'conic-gradient('.implode(', ', $stops).')' : '#e5e7eb' }};">
            <div class="w-28 h-28 bg-white rounded-full mx-auto flex flex-col items-center justify-center" style="margin-top:32px;">
                <div class="text-2xl font-bold font-serif">{{ $total }}</div>
                <div class="text-[11px] text-gray-400">pledge{{ $total === 1 ? '' : 's' }}</div>
            </div>
        </div>
        <div class="flex justify-center gap-4 flex-wrap text-xs">
            @foreach ($counts as $label => $count)
            <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" style="background:{{ $colors[$label] }}"></span>{{ $label }} ({{ $count }})</div>
            @endforeach
        </div>
    </div>

    <div class="card p-6">
        <div class="text-xs uppercase text-gray-400 font-semibold mb-4">Collected vs pledged</div>
        <div class="flex justify-between items-baseline mb-2">
            <span class="text-3xl font-semibold font-serif">{{ $pct }}%</span>
            <span class="text-sm text-gray-400">{{ number_format($stats['collected']) }} of {{ number_format($stats['total_pledged']) }}</span>
        </div>
        <div class="h-5 rounded-full bg-gray-100 border overflow-hidden">
            <div class="h-full rounded-full" style="background:linear-gradient(90deg, var(--accent), var(--primary));width:{{ $pct }}%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mt-1"><span>0%</span><span>50%</span><span>100%</span></div>
        <div class="grid grid-cols-2 gap-3 mt-5">
            <div class="card p-3"><div class="text-xs text-gray-400">Total pledged</div><div class="font-semibold">{{ number_format($stats['total_pledged']) }}</div></div>
            <div class="card p-3"><div class="text-xs text-gray-400">Remaining</div><div class="font-semibold">{{ number_format($stats['remain']) }}</div></div>
        </div>
    </div>
</div>
@endsection
