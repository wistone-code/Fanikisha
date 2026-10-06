<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recap — {{ $event->name }}</title>
<style>
    body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#1B2429;max-width:760px;margin:0 auto;padding:20px}
    h1{margin:0 0 2px;font-size:22px} .sub{color:#667;font-size:13px;margin-bottom:18px}
    .g{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px}
    .c{border:1px solid #dde2e6;border-radius:10px;padding:12px} .c b{display:block;font-size:22px} .c span{font-size:11px;color:#667;text-transform:uppercase}
    h2{font-size:15px;margin:22px 0 8px} table{border-collapse:collapse;width:100%;font-size:13px} td,th{border-bottom:1px solid #eef0f2;padding:6px 4px;text-align:left}
    .bar{background:#eef1f3;border-radius:4px;height:10px}.bar i{display:block;background:{{ app(\App\Services\EventThemeService::class)->forEvent($event)['primary'] }};height:10px;border-radius:4px}
    @media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="noprint" style="text-align:right"><button onclick="window.print()">Print / Save as PDF</button></div>
<h1>{{ $event->name }} — recap</h1>
<div class="sub">{{ $event->event_date->format('l, F j, Y') }}{{ $event->place ? ' · '.$event->place : '' }}</div>

<div class="g">
    <div class="c"><b>{{ $stats['cards'] }}</b><span>Cards issued</span></div>
    <div class="c"><b>{{ $stats['opened'] }}</b><span>Opened</span></div>
    <div class="c"><b>{{ $stats['attending'] }}</b><span>Said yes</span></div>
    <div class="c"><b>{{ $stats['declined'] }}</b><span>Said no</span></div>
    <div class="c"><b>{{ $stats['no_reply'] }}</b><span>No reply</span></div>
    <div class="c"><b>{{ $stats['arrived'] }}</b><span>Checked in ({{ $stats['arrived_people'] }} people)</span></div>
    <div class="c"><b>{{ $stats['no_show'] }}</b><span>Said yes, did not come</span></div>
    <div class="c"><b>{{ $stats['walk_in'] }}</b><span>Came without a "yes"</span></div>
</div>

<p style="font-size:13px">Expected {{ $stats['expected_people'] }} people from the "yes" answers (including {{ $stats['plus_ones'] }} extra guests). {{ $smsUsed }} SMS used · {{ $stats['thanked'] }} thanked · {{ $photos }} photo(s) on the wall.</p>

@if ($money)
<h2>Contributions</h2>
<p style="font-size:13px">Pledged {{ number_format($money['total_pledged']) }} · Collected {{ number_format($money['collected']) }} · Remaining {{ number_format($money['remain']) }}</p>
@endif

@if ($byHour->count())
<h2>When guests arrived</h2>
<table>@php($max = max(1, $byHour->max()))
@foreach ($byHour as $hour => $n)<tr><td style="width:70px">{{ $hour }}</td><td><div class="bar"><i style="width:{{ $n / $max * 100 }}%"></i></div></td><td style="width:40px">{{ $n }}</td></tr>@endforeach
</table>
@endif

@if ($meals->count())
<h2>Meal choices</h2>
<table>@foreach ($meals as $meal => $n)<tr><td>{{ $meal }}</td><td style="width:60px">{{ $n }}</td></tr>@endforeach</table>
@endif

@if ($noShows->count())
<h2>Said yes but did not check in ({{ $noShows->count() }})</h2>
<table>@foreach ($noShows as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->phone }}</td></tr>@endforeach</table>
@endif
</body>
</html>
