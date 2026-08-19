<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $event->name }} — Service Providers</title>
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
    <h1>{{ $event->name }} — Service Providers</h1>
    <div class="sub">{{ $providers->count() }} provider{{ $providers->count() === 1 ? '' : 's' }} &middot; Generated {{ now()->format('M j, Y') }}</div>
    <table>
        <thead><tr><th>Name</th><th>Service</th><th>Budget</th><th>Contact</th></tr></thead>
        <tbody>
        @foreach ($providers as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td>{{ $p->service }}</td>
                <td>{{ number_format($p->budget) }}</td>
                <td>{{ $p->phone }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="2">Total</td><td>{{ number_format($totalBudget) }}</td><td></td></tr>
        </tfoot>
    </table>
</body>
</html>