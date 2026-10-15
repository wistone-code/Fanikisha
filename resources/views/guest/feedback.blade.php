@php
    $sw = $event->sms_language === 'sw';
    $t = $sw ? [
        'title' => 'Tupe maoni yako', 'ask' => 'Hafla ilikuwaje, :name?', 'comment' => 'Ujumbe kwa wenyeji (si lazima)', 'send' => 'Tuma maoni',
        'thanks' => 'Asante kwa maoni yako!', 'thanks2' => 'Wenyeji wamepokea maoni yako.', 'change' => 'Badilisha jibu langu',
        'closed' => 'Kupokea maoni kumefungwa.', 'notyet' => 'Maoni yatafunguliwa siku ya hafla.', 'stars' => 'nyota',
    ] : [
        'title' => 'Share your feedback', 'ask' => 'How was the event, :name?', 'comment' => 'A message for the hosts (optional)', 'send' => 'Send feedback',
        'thanks' => 'Thank you for your feedback!', 'thanks2' => 'The hosts have received it.', 'change' => 'Change my answer',
        'closed' => 'Feedback is now closed.', 'notyet' => 'Feedback opens on the day of the event.', 'stars' => 'stars',
    ];
    $color = $event->theme_color ?: '#1F5F5B';
    $mine = (int) $pledge->feedback_rating;
@endphp
<!DOCTYPE html>
<html lang="{{ $sw ? 'sw' : 'en' }}">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>{{ $t['title'] }} — {{ $event->name }}</title>
<style>
body{font-family:system-ui,sans-serif;background:#0A1319;color:#1B2429;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:20px;margin:0}
.b{background:#fff;border-radius:18px;padding:28px 22px;max-width:380px;width:100%;text-align:center}
h1{font-size:19px;margin:0 0 4px}.sub{font-size:13px;color:#777;margin:0 0 18px}
.stars{display:flex;flex-direction:row-reverse;justify-content:center;gap:4px;margin:6px 0 16px}
.stars input{position:absolute;opacity:0;pointer-events:none}
.stars label{font-size:44px;line-height:1;color:#d5dadd;cursor:pointer;padding:2px}
.stars input:checked ~ label,.stars label:hover,.stars label:hover ~ label{color:#f5a623}
.stars input:focus-visible + label{outline:2px solid {{ $color }};border-radius:6px}
textarea{width:100%;box-sizing:border-box;border:1px solid #cfd6da;border-radius:10px;padding:10px;font:inherit;font-size:16px;min-height:80px}
button{margin-top:14px;width:100%;background:{{ $color }};color:#fff;border:0;border-radius:10px;padding:13px;font-size:16px;font-weight:600}
.err{color:#b42318;font-size:13px;margin:6px 0}
.done{font-size:40px;color:#f5a623}.link{font-size:13px;color:#555}
</style>
</head>
<body><div class="b">
@if (! $open)
    <h1>{{ $event->name }}</h1>
    <p class="sub">{{ $event->event_date->gt(now()->startOfDay()) ? $t['notyet'] : $t['closed'] }}</p>
@elseif ($done)
    <div class="done">★</div>
    <h1>{{ $t['thanks'] }}</h1>
    <p class="sub">{{ $t['thanks2'] }}</p>
    <p class="link"><a href="{{ route('guest.feedback', $pledge->invite_token) }}?edit=1" style="color:#555">{{ $t['change'] }}</a></p>
@elseif ($mine && ! request('edit'))
    <div class="done">{{ str_repeat('★', $mine) }}</div>
    <h1>{{ $t['thanks'] }}</h1>
    <p class="sub">{{ $t['thanks2'] }}</p>
    <p class="link"><a href="{{ route('guest.feedback', $pledge->invite_token) }}?edit=1" style="color:#555">{{ $t['change'] }}</a></p>
@else
    <h1>{{ $event->name }}</h1>
    <p class="sub">{{ str_replace(':name', trim(explode(' ', $pledge->name)[0]), $t['ask']) }}</p>
    <form method="POST" action="{{ route('guest.feedback.store', $pledge->invite_token) }}">
        @csrf
        <div class="stars" role="radiogroup" aria-label="{{ $t['title'] }}">
            @foreach ([5, 4, 3, 2, 1] as $n)
            <input type="radio" name="rating" id="r{{ $n }}" value="{{ $n }}" @checked(old('rating', $mine) == $n) required>
            <label for="r{{ $n }}" title="{{ $n }} {{ $t['stars'] }}">★</label>
            @endforeach
        </div>
        @error('rating')<div class="err">{{ $message }}</div>@enderror
        <textarea name="message" maxlength="500" placeholder="{{ $t['comment'] }}">{{ old('message', $pledge->feedback_message) }}</textarea>
        @error('message')<div class="err">{{ $message }}</div>@enderror
        <button type="submit">{{ $t['send'] }}</button>
    </form>
@endif
</div></body></html>
