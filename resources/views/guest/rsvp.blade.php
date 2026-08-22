<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>You're invited — {{ $event->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    body{font-family:'Inter',sans-serif;}
    h1,.display{font-family:'Fraunces',serif;}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border-radius:9px;padding:11px 16px;font-size:13.5px;font-weight:600;cursor:pointer;}
    .btn-primary{background:{{ $theme['primary'] }};color:#fff;}
    .btn-ghost{background:#fff;border:1px solid #e2e6e9;color:#1B2429;}
</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-6 gap-4" style="background:radial-gradient(120% 140% at 20% 0%, {{ $theme['primary_dark'] }} 0%, #0A1319 70%);">

    <div id="card" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full text-center overflow-hidden">
        <div class="pt-10 pb-8 px-8 relative" style="background:linear-gradient(160deg, {{ $theme['primary'] }} 0%, {{ $theme['primary_dark'] }} 100%);">
            <span class="absolute top-3 right-3 text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-full" style="background:{{ $theme['accent'] }}; color:{{ $theme['primary_dark'] }};">{{ ucfirst($pledge->card_type) }} Card</span>
            @if ($event->hasCardPhoto())
            <img src="{{ route('guest.rsvp.photo', $pledge->invite_token) }}" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg mx-auto mb-4" alt="">
            @endif
            <div class="text-xs uppercase tracking-widest font-semibold mb-2" style="color:{{ $theme['accent'] }};">You're invited to</div>
            <h1 class="text-2xl font-semibold text-white">{{ $event->name }}</h1>
            <div class="text-sm font-medium mt-1" style="color:{{ $theme['accent'] }};">{{ $event->event_type }}</div>
        </div>
        <div class="p-8">
            <p class="text-sm text-gray-500 mb-1">{{ $event->event_date->format('l, F j, Y') }}</p>
            @if ($event->place)<p class="text-sm text-gray-500 mb-4">{{ $event->place }}</p>@endif
            <p class="text-sm mt-4">Dear {{ $pledge->name }}, thank you for your contribution — we look forward to celebrating with you!</p>
            <div class="border-t mt-6 pt-5">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&margin=20&ecc=H&data={{ urlencode($pledge->inviteLink()) }}" alt="Entrance check-in QR code" class="mx-auto rounded-lg border" style="max-width:220px;width:100%;height:auto;">
                <p class="text-xs text-gray-400 mt-2">Show this at the entrance for check-in</p>
            </div>
        </div>
    </div>

    <div class="flex gap-2 w-full max-w-sm">
        <button onclick="shareCard()" class="btn btn-primary flex-1"><i class="fa-solid fa-share-nodes"></i> Share</button>
        <button onclick="saveCardAsImage()" class="btn btn-ghost flex-1"><i class="fa-solid fa-download"></i> Save</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        const cardFileName = {{ Js::from(Str::slug($event->name).'-invitation.png') }};
        const cardTitle = {{ Js::from("You're invited to ".$event->name) }};

        function renderCard() {
            return html2canvas(document.getElementById('card'), { backgroundColor: '#ffffff', scale: 2 });
        }

        function saveCardAsImage() {
            renderCard().then(function (canvas) {
                const link = document.createElement('a');
                link.download = cardFileName;
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }

        function shareCard() {
            renderCard().then(function (canvas) {
                canvas.toBlob(async function (blob) {
                    const file = new File([blob], cardFileName, { type: 'image/png' });

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        try {
                            await navigator.share({ files: [file], title: cardTitle });
                        } catch (e) {
                            // Person cancelled the share sheet — nothing to do.
                        }
                    } else {
                        // Browser can't share files directly (mostly desktop) — fall back to download.
                        saveCardAsImage();
                    }
                }, 'image/png');
            });
        }
    </script>
</body>
</html>
