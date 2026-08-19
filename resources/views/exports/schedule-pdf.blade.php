<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $event->name }} — Ceremony Schedule</title>
<style>
    body{font-family:Helvetica,Arial,sans-serif;padding:32px;color:#1a1a1a;}
    h1{font-size:20px;margin:0 0 4px;}
    .sub{color:#666;font-size:13px;margin-bottom:20px;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #ddd;}
    th{background:#f4f4f4;font-weight:bold;}
</style>
</head>
<body>
    <h1>{{ $event->name }} — Ceremony Schedule</h1>
    <div class="sub">{{ $items->count() }} item{{ $items->count() === 1 ? '' : 's' }} &middot; Generated {{ now()->format('M j, Y') }}</div>
    <table>
        <thead><tr><th>Event</th><th>Date</th><th>Time</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->title }}</td>
                <td>{{ $item->date->format('M j, Y') }}</td>
                <td>{{ $item->time ? \Carbon\Carbon::parse($item->time)->format('g:i A') : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>