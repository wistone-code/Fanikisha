<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Door list — {{ $event->name }}</title>
<style>
    body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;color:#111;margin:20px;font-size:13px}
    h1{font-size:18px;margin:0}.sub{color:#555;margin-bottom:12px}
    table{width:100%;border-collapse:collapse}th,td{border-bottom:1px solid #ccc;padding:5px 6px;text-align:left}
    th{font-size:11px;text-transform:uppercase;color:#555}.box{display:inline-block;width:14px;height:14px;border:1.5px solid #333;border-radius:2px}
    .code{font-family:ui-monospace,monospace;letter-spacing:.1em;font-weight:700}
    @media print{.noprint{display:none} body{margin:8mm}}
</style>
</head>
<body>
<div class="noprint" style="text-align:right"><button onclick="window.print()">Print</button></div>
<h1>{{ $event->name }} — door list</h1>
<div class="sub">{{ $event->event_date->format('l, F j, Y') }} · {{ $guests->count() }} cards · printed {{ now()->format('M j, g:i A') }}</div>
<table>
    <thead><tr><th></th><th>Name</th><th>Code</th><th>Seat</th><th>People</th><th>Phone</th></tr></thead>
    <tbody>
    @foreach ($guests as $g)
        <tr>
            <td><span class="box"></span></td>
            <td>{{ $g->name }}{{ $g->rsvp_status === 'not_attending' ? ' (declined)' : '' }}</td>
            <td class="code">{{ $g->card_code }}</td>
            <td>{{ $g->seatLabel() ?? '' }}</td>
            <td>{{ $g->headcount() ?: 1 }}</td>
            <td>{{ $g->phone ? '…'.substr(preg_replace('/\D+/', '', $g->phone), -4) : '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
