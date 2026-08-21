<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay {{ $event->name }} — {{ $pledge->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    body{font-family:'Inter',sans-serif;}
    h1,.display{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:9px;padding:11px 16px;font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none;}
    .btn-primary{background:{{ $theme['primary'] }};color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6 gap-4" style="background:radial-gradient(120% 140% at 20% 0%, {{ $theme['primary_dark'] }} 0%, #0A1319 70%);">

    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden">
        <div class="pt-8 pb-6 px-8 text-center" style="background:linear-gradient(160deg, {{ $theme['primary'] }} 0%, {{ $theme['primary_dark'] }} 100%);">
            <div class="text-xs uppercase tracking-widest font-semibold mb-1" style="color:{{ $theme['accent'] }};">Pay for</div>
            <h1 class="text-xl font-semibold text-white">{{ $event->name }}</h1>
        </div>

        <div class="p-6">
            <p class="text-sm text-gray-500 mb-4">Dear {{ $pledge->name }}, here's your balance:</p>

            <div class="flex justify-between text-sm border-b pb-2 mb-2">
                <span class="text-gray-500">Pledged</span>
                <span class="font-semibold">{{ number_format($pledge->amount) }}</span>
            </div>
            <div class="flex justify-between text-sm border-b pb-2 mb-2">
                <span class="text-gray-500">Already paid</span>
                <span class="font-semibold">{{ number_format($pledge->paid) }}</span>
            </div>
            <div class="flex justify-between text-base mb-6">
                <span class="font-semibold">Balance owed</span>
                <span class="font-bold" style="color:{{ $theme['primary'] }};">{{ number_format($pledge->remaining()) }}</span>
            </div>

            @if ($event->hasPayoutNumber())
            <div class="border-t pt-5">
                <p class="text-xs text-gray-500 mb-1">Send payment directly to:</p>
                <div class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2.5 mb-1">
                    <span id="payoutNumber" class="font-mono font-semibold text-sm">{{ $event->payout_phone }}</span>
                    <button onclick="copyNumber()" class="text-xs font-semibold" style="color:{{ $theme['primary'] }};">Copy</button>
                </div>
                @if ($event->payout_network)
                <p class="text-xs text-gray-400 mb-4">Registered on {{ $event->payout_network }} — other networks can still send via cross-network transfer.</p>
                @endif

                <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode($event->payout_phone) }}" alt="QR code for {{ $event->payout_phone }}" class="mx-auto mb-4 rounded-lg border">

                <p class="text-xs text-gray-500 mb-2">Tap your network below to open its Send Money menu:</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Models\Event::NETWORK_USSD_CODES as $network => $code)
                    <a href="tel:{{ str_replace('#', '%23', $code) }}" class="btn {{ $event->payout_network === $network ? 'btn-primary' : 'btn-ghost' }} !text-xs">{{ $network }}</a>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">This opens your network's own menu — choose "Send Money", enter the number above, then the amount, then confirm with your PIN.</p>
            </div>
            @else
            <div class="border-t pt-5 text-center">
                <p class="text-sm text-gray-500">The organizer hasn't set up a payment number yet — please contact them directly to arrange payment.</p>
            </div>
            @endif
        </div>
    </div>

    <script>
        function copyNumber() {
            const text = document.getElementById('payoutNumber').textContent;
            navigator.clipboard.writeText(text).catch(function () {});
        }
    </script>
</body>
</html>
