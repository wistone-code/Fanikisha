@extends('layouts.app')
@section('title', 'Reminder — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('pledges.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">All pledges</a>
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Reminder</span>
</div>

<div class="flex justify-between items-center mb-3">
    <div>
        <h2 class="text-xl font-semibold">Remind pledgers</h2>
        <p class="text-sm text-gray-500">{{ $outstanding->count() }} pledge{{ $outstanding->count() === 1 ? '' : 's' }} with an outstanding balance.</p>
    </div>
    @if ($outstanding->count())
    <a href="{{ route('pledges.remind-all.sms') }}" class="btn btn-ghost"><i class="fa-solid fa-comment-sms"></i> SMS all</a>
    @endif
</div>

<div class="card overflow-x-auto mb-4">
    <table class="w-full text-sm sortable-table">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3" data-sort="text">Name</th><th class="px-4 py-3" data-sort="text">Phone</th><th class="px-4 py-3" data-sort="number">Remain</th><th class="px-4 py-3"></th></tr></thead>
        <tbody>
        @forelse ($outstanding as $p)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                <td class="px-4 py-3">{{ $p->phone ?? '—' }}</td>
                <td class="px-4 py-3">{{ number_format($p->remaining()) }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <a href="{{ route('pledges.remind.sms', $p) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</a>
                    <a href="{{ route('pledges.remind.whatsapp', $p) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">Everyone is settled up. 🎉</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card p-5 mb-4">
    <div class="text-xs font-semibold mb-2">Broadcast message <span class="text-gray-400 font-normal">— sent by "SMS all". Write your own — there's no starter text.</span></div>
    <form method="POST" action="{{ route('pledges.message.broadcast') }}">
        @csrf @method('PATCH')
        <textarea name="broadcast_message" rows="7" placeholder="Write the message that will be sent to everyone with an outstanding balance…" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('broadcast') }}</textarea>
        @error('broadcast_message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Save broadcast message</button>
    </form>
</div>

<div class="card p-5 mb-4">
    <div class="text-xs font-semibold mb-2">Individual message <span class="text-gray-400 font-normal">— use {name}, {event}, {pledged}, {paid}, {remain}</span></div>
    <form method="POST" action="{{ route('pledges.message.reminder') }}">
        @csrf @method('PATCH')
        <textarea name="reminder_message" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('reminder') }}</textarea>
        <button class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Save individual message</button>
    </form>
</div>
@endsection