@php
    // Contact details come from the COMPANY_* Railway variables, with Fanikisha's main number as the fallback.
    $c = config('company');
    $phone = $c['phone'] ?: '0675 536 715';
    $telDigits = preg_replace('/\D+/', '', $phone);
    $wa = preg_replace('/\D+/', '', (string) ($c['whatsapp'] ?: $telDigits));
    if (str_starts_with($wa, '0')) {
        $wa = '255'.substr($wa, 1);
    }
    $email = $c['email'] ?: 'info@fanikisha.app';
    $waText = rawurlencode('Hello Fanikisha, my invitation card link is not working. / Habari Fanikisha, kiungo cha kadi yangu ya mwaliko hakifanyi kazi.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Card unavailable — Fanikisha</title>
<style>
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#1F3A52;color:#1B2429;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px;text-align:center}
    .b{background:#fff;border-radius:20px;padding:32px 26px 26px;max-width:360px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.25)}
    .logo{width:52px;height:52px;border-radius:14px;background:#1F3A52;color:#fff;display:flex;align-items:center;justify-content:center;font:700 28px Georgia,serif;margin:0 auto 18px}
    h1{font-size:21px;margin:0 0 4px}
    .sw{font-size:15px;color:#555;margin:0 0 14px;font-weight:600}
    p.msg{font-size:14px;color:#666;line-height:1.5;margin:0 0 20px}
    .btn{display:flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:13px 16px;font-size:15px;font-weight:600;text-decoration:none;margin-top:10px}
    .wa{background:#1F8A4C;color:#fff}
    .call{background:#1F3A52;color:#fff}
    .mail{background:#fff;color:#1F3A52;border:1.5px solid #cfd8df}
    .foot{font-size:12px;color:#999;margin-top:18px}
</style>
</head>
<body><div class="b">
    <div class="logo">F</div>
    <h1>Card unavailable</h1>
    <p class="sw">Kadi haipatikani</p>
    <p class="msg">This invitation card is not available. Please contact Fanikisha and we will help you.<br>
        Kadi hii ya mwaliko haipatikani. Tafadhali wasiliana na Fanikisha tukusaidie.</p>

    <a class="btn wa" href="https://wa.me/{{ $wa }}?text={{ $waText }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.1.3.6 1.1 1.4 1.8 1 .9 1.8 1.1 2.1 1.3.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l2 .9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>
        WhatsApp {{ $phone }}
    </a>
    <a class="btn call" href="tel:{{ $telDigits }}">Call / Piga simu</a>
    <a class="btn mail" href="mailto:{{ $email }}?subject={{ rawurlencode('Invitation card unavailable') }}">{{ $email }}</a>

    <div class="foot">fanikisha.app</div>
</div></body></html>
