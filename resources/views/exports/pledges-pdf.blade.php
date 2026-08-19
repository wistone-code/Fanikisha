<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $event->name }} — Pledges</title>
<style>
    body{font-family:Helvetica,Arial,sans-serif;padding:32px;color:#1a1a1a;}
    h1{font-size:20px;margin:0 0 4px;}
    .sub{color:#666;font-size:13px;margin-bottom:20px;}
    table{width:100%;border-collapse:collapse;font-size:13px;}
    th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #ddd;}
    th{background:#f4f4f4;font-weight:bold;}
    tfoot td{font-weight:bold;border-top:2px solid #333;}
</style>
</head>
<body>
    <h1>{{ $event->name }} — Pledges</h1>
    <div class="sub">{{ $pledges->count() }} pledge{{ $pledges->count() === 1 ? '' : 's' }} &middot; Generated {{ now()->format('M j, Y') }}</div>
    <table>
        <thead><tr><th>Name</th><th>Pledge amount</th><th>Paid</th><th>Remain</th><th>Phone</th></tr></thead>
        <tbody>
        @foreach ($pledges as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td>{{ number_format($p->amount) }}</td>
                <td>{{ number_format($p->paid) }}</td>
                <td>{{ number_format($p->remaining()) }}</td>
                <td>{{ $p->phone }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td>Total</td><td>{{ number_format($totalPledged) }}</td><td>{{ number_format($totalPaid) }}</td><td>{{ number_format($totalPledged - $totalPaid) }}</td><td></td></tr>
        </tfoot>
    </table>
</body>
</html>
