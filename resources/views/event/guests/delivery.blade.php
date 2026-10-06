@extends('layouts.app')
@section('title', 'Card delivery — '.config('app.name'))

@section('content')
@include('event.guests._tabs', ['active' => 'delivery'])

@php($pct = fn ($n) => $counts['total'] > 0 ? round($n / $counts['total'] * 100) : 0)
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Card delivery</h2>
        <p class="text-sm text-gray-500">See who has received, opened and answered their card.</p>
    </div>
    @if ($isAdmin)
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('guests.export') }}" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <a href="{{ route('checkin.door-list') }}" target="_blank" class="btn btn-ghost"><i class="fa-solid fa-print"></i> Door list</a>
    </div>
    @endif
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    @foreach ([['not_sent', 'Not sent', $counts['not_sent'], 'fa-paper-plane'], ['sent', 'Sent', $counts['sent'], 'fa-check'], ['opened', 'Opened', $counts['opened'], 'fa-envelope-open'], ['responded', 'Responded', $counts['responded'], 'fa-reply'], ['arrived', 'Arrived', $counts['arrived'], 'fa-door-open']] as [$key, $label, $n, $icon])
    <a href="{{ route('delivery.index', ['stage' => $stage === $key ? null : $key]) }}" class="card p-4 block {{ $stage === $key ? 'ring-2' : '' }}" @if ($stage === $key) style="--tw-ring-color:var(--primary)" @endif>
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</div>
        <div class="text-2xl font-semibold mt-1">{{ $n }}<span class="text-xs text-gray-400 font-normal"> / {{ $counts['total'] }}</span></div>
        <div class="h-1.5 bg-gray-100 rounded mt-2"><div class="h-1.5 rounded" style="width:{{ $key === 'not_sent' ? $pct($n) : $pct($n) }}%;background:var(--primary)"></div></div>
    </a>
    @endforeach
</div>

@if ($isAdmin)
<div class="card p-4 mb-4">
    <div class="flex flex-wrap gap-2 items-center">
        <form method="POST" action="{{ route('delivery.send-all') }}" data-confirm="Send the card by SMS to the {{ $counts['not_sent'] }} guest(s) who have not been sent one? Each message uses SMS quota." data-confirm-title="Send all unsent cards?" data-confirm-button="Send">
            @csrf <button class="btn btn-primary" @disabled($counts['not_sent'] === 0)><i class="fa-solid fa-paper-plane"></i> Send to all not yet sent ({{ $counts['not_sent'] }})</button>
        </form>
        <form method="POST" action="{{ route('delivery.remind-unopened') }}" data-confirm="Remind the {{ $counts['unopened'] }} guest(s) who got a card but have not opened it? Each message uses SMS quota." data-confirm-title="Remind unopened?" data-confirm-button="Send">
            @csrf <button class="btn btn-ghost" @disabled($counts['unopened'] === 0)><i class="fa-solid fa-bell"></i> Remind unopened ({{ $counts['unopened'] }})</button>
        </form>
        <span class="text-xs text-gray-500">SMS left: <strong>{{ $event->smsRemaining() === null ? 'unlimited' : $event->smsRemaining() }}</strong></span>
    </div>
    <details class="mt-3">
        <summary class="text-xs font-semibold cursor-pointer">Automatic reminder for unopened cards</summary>
        <form method="POST" action="{{ route('delivery.auto') }}" class="mt-3 space-y-3">
            @csrf @method('PATCH')
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_remind_unopened" value="1" @checked($event->auto_remind_unopened)> Remind guests who have not opened their card</label>
            <div class="flex items-center gap-2 text-sm">Send it <input type="number" name="auto_remind_unopened_days" min="1" max="30" value="{{ $event->auto_remind_unopened_days }}" class="w-16 border rounded-lg px-2 py-1 text-sm"> day(s) before the event, once per guest.</div>
            <div><label class="text-xs font-semibold">Reminder message <span class="text-gray-400 font-normal">({name} {event} {date} {link})</span></label>
                <textarea name="unopened_reminder_message" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('unopened_reminder') }}</textarea></div>
            <button class="btn btn-primary">Save</button>
        </form>
    </details>
</div>
@endif

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
            <th class="px-4 py-3">Guest</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last opened</th>@if ($isAdmin)<th class="px-4 py-3"></th>@endif
        </tr></thead>
        <tbody>
        @forelse ($rows as $p)
            @php($stageKey = $p->funnelStage())
            <tr class="border-b last:border-0">
                <td class="px-4 py-3"><div class="font-semibold">{{ $p->name }}</div><div class="text-xs text-gray-400">{{ $p->phone ?? 'no phone' }} · code {{ $p->card_code }}</div></td>
                <td class="px-4 py-3">
                    @switch($stageKey)
                        @case('arrived') <span class="badge badge-admin">Arrived</span> @break
                        @case('responded') <span class="badge badge-admin">{{ $p->rsvpLabel() }}</span> @break
                        @case('opened') <span class="badge badge-viewer">Opened ×{{ $p->open_count }}</span> @break
                        @case('sent') <span class="text-xs text-amber-600">Sent, not opened</span> @break
                        @default <span class="text-xs text-gray-400">Not sent</span>
                    @endswitch
                    @if (isset($shared[$p->id]))<div class="text-[11px] text-amber-700 mt-1" title="Opened on {{ $shared[$p->id] }} different phones"><i class="fa-solid fa-share-nodes"></i> Possibly forwarded ({{ $shared[$p->id] }} devices)</div>@endif
                    @if ($p->scan_attempts > 0)<div class="text-[11px] text-red-600 mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Scanned again {{ $p->scan_attempts }}× after check-in</div>@endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ $p->last_opened_at?->format('M j, g:i A') ?? '—' }}</td>
                @if ($isAdmin)
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($p->invite_sent_at === null)
                    <form method="POST" action="{{ route('delivery.mark-sent', $p) }}" class="inline">@csrf <button class="btn btn-ghost !py-1.5 !px-2.5 text-xs" title="Mark as sent (you sent it yourself)"><i class="fa-solid fa-check"></i> Mark sent</button></form>
                    @elseif ($p->first_opened_at === null && $p->phone)
                    <a href="{{ route('delivery.remind-wa', $p) }}" class="btn btn-ghost !py-1.5 !px-2.5 text-xs" title="Remind on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    @endif
                    <form method="POST" action="{{ route('delivery.reissue', $p) }}" class="inline" data-confirm="Create a new card link for {{ $p->name }}? The old link stops working, and you will need to send the new one." data-confirm-title="Reissue card?" data-confirm-button="Reissue">@csrf <button class="btn btn-ghost !py-1.5 !px-2.5 text-xs" title="New link (old one stops working)"><i class="fa-solid fa-arrows-rotate"></i></button></form>
                    <form method="POST" action="{{ route('delivery.revoke', $p) }}" class="inline" data-confirm="Cancel {{ $p->name }}'s card? The link and QR code stop working straight away." data-confirm-title="Cancel card?" data-confirm-button="Cancel card">@csrf <button class="btn btn-danger !py-1.5 !px-2.5 text-xs" title="Cancel this card"><i class="fa-solid fa-ban"></i></button></form>
                </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No guests in this view.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if ($isAdmin && $revoked->count())
<div class="card p-4 mt-4">
    <div class="text-sm font-semibold mb-2">Cancelled cards</div>
    @foreach ($revoked as $p)
    <div class="flex justify-between items-center border rounded-lg px-3 py-2 mb-2 text-sm"><span>{{ $p->name }}</span>
        <form method="POST" action="{{ route('delivery.reissue', $p) }}">@csrf <button class="btn btn-ghost !py-1 !px-2 text-xs">Issue a new card</button></form></div>
    @endforeach
</div>
@endif
@endsection
