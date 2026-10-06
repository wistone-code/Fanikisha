@extends('layouts.app')
@section('title', 'Privacy & opt-outs — '.config('app.name'))

@section('content')
@php
    $typeLabels = ['access' => 'See my data', 'correct' => 'Correct my data', 'delete' => 'Delete my data', 'stop' => 'Stop messages', 'other' => 'Other'];
    $sourceLabels = ['card' => 'Guest card', 'request' => 'Data-request form', 'organiser' => 'Organiser', 'admin' => 'Added by you'];
@endphp

<div class="mb-4">
    <h2 class="text-xl font-semibold">Privacy &amp; opt-outs</h2>
    <p class="text-sm text-gray-500">Requests from the public page <span class="font-mono">/data-request</span> and the list of numbers that must never be messaged. Targets: acknowledge within 2 days, finish within 30 days.</p>
</div>

<div class="flex gap-2 mb-4">
    <a href="{{ route('admin.compliance', ['tab' => 'requests']) }}" class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $tab === 'requests' ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
        Data requests @if ($openCount)<span class="ml-1">({{ $openCount }} open)</span>@endif
    </a>
    <a href="{{ route('admin.compliance', ['tab' => 'blocked']) }}" class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $tab === 'blocked' ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
        Do-not-message list ({{ number_format($blockedCount) }})
    </a>
</div>

@if ($tab === 'requests')
    <div class="card p-4 mb-4 space-y-3">
        <div class="flex flex-wrap gap-2">
            @foreach (['open' => 'Open', 'new' => 'New', 'acknowledged' => 'Acknowledged', 'closed' => 'Closed'] as $key => $label)
                <a href="{{ route('admin.compliance', array_filter(['tab' => 'requests', 'status' => $key, 'q' => $search ?: null])) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $status === $key ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="requests"><input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search name, phone or email…" class="flex-1 min-w-[200px] border rounded-lg px-3 py-2 text-sm">
            <button class="btn btn-ghost">Search</button>
        </form>
    </div>

    <div class="space-y-3">
        @forelse ($requests as $r)
            @php $u = $r->urgency(); @endphp
            <div class="card p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">#{{ $r->id }} · {{ $typeLabels[$r->type] ?? $r->type }}
                            <span class="badge ml-1 {{ $r->status === 'closed' ? 'badge-viewer' : 'badge-admin' }}">{{ ucfirst($r->status) }}</span>
                            @if ($u === 'overdue')<span class="badge" style="background:#fde8e8;color:#b42318;">Over 30 days</span>@endif
                            @if ($u === 'late-ack')<span class="badge" style="background:#fff4e0;color:#9a5b00;">Not acknowledged in 2 days</span>@endif
                        </div>
                        <div class="text-sm text-gray-500 mt-0.5">
                            {{ $r->created_at->timezone('Africa/Dar_es_Salaam')->format('M j, Y g:i A') }}
                            @if ($r->name) · {{ $r->name }}@endif
                            @if ($r->phone) · <span class="font-mono">{{ $r->phone }}</span>@endif
                            @if ($r->email) · {{ $r->email }}@endif
                        </div>
                    </div>
                </div>
                @if ($r->details)<p class="text-sm mt-2 whitespace-pre-line">{{ $r->details }}</p>@endif
                @if ($r->type === 'stop' && $r->phone)
                    @php $isBlocked = isset($blockedKeys[$optOuts->key($r->phone)]); @endphp
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if ($isBlocked)
                            <span class="text-xs text-green-700"><i class="fa-solid fa-ban"></i> Blocked — this number gets no messages.</span>
                            <form method="POST" action="{{ route('admin.compliance.request', $r) }}" data-confirm="Unblock this number? Only do this if the person asked to receive messages again.">
                                @csrf @method('PATCH')
                                <button name="action" value="unblock" class="btn btn-ghost !w-auto !py-1.5 !px-2.5 text-xs">Unblock</button>
                            </form>
                        @else
                            <span class="text-xs text-amber-700"><i class="fa-solid fa-hourglass-half"></i> Waiting for your approval — not blocked yet.</span>
                            <form method="POST" action="{{ route('admin.compliance.request', $r) }}">
                                @csrf @method('PATCH')
                                <button name="action" value="block" class="btn btn-primary !w-auto !py-1.5 !px-2.5 text-xs">Approve &amp; block</button>
                            </form>
                        @endif
                    </div>
                @endif
                @if ($r->note)<p class="text-sm mt-2 bg-gray-50 rounded-lg p-2"><span class="text-xs font-semibold text-gray-500">What we did:</span> {{ $r->note }}</p>@endif

                <form method="POST" action="{{ route('admin.compliance.request', $r) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    @if ($r->status !== 'closed')
                        <input type="text" name="note" maxlength="2000" placeholder="What was done (sent to the person if they gave an email)" class="flex-1 min-w-[220px] border rounded-lg px-3 py-2 text-sm">
                        @if ($r->status === 'new')
                            <button name="action" value="acknowledge" class="btn btn-ghost !w-auto">Acknowledge</button>
                        @endif
                        <button name="action" value="close" class="btn btn-primary !w-auto">Close request</button>
                    @else
                        <button name="action" value="reopen" class="btn btn-ghost !w-auto">Reopen</button>
                    @endif
                </form>
            </div>
        @empty
            <div class="card p-8 text-center text-gray-400">No requests here.</div>
        @endforelse
    </div>
@else
    <div class="card p-4 mb-4">
        <form method="POST" action="{{ route('admin.compliance.blocked.add') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs font-semibold">Add a number that must never be messaged</label>
                <input type="text" name="phone" inputmode="tel" value="{{ old('phone') }}" placeholder="07XX XXX XXX or +255…" class="w-full border rounded-lg px-3 py-2 text-base mt-1" required>
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary !w-auto">Add to list</button>
        </form>
        <form method="GET" class="flex flex-wrap items-center gap-3 mt-4">
            <input type="hidden" name="tab" value="blocked">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search a number…" class="flex-1 min-w-[200px] border rounded-lg px-3 py-2 text-sm">
            <button class="btn btn-ghost !w-auto">Search</button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm" data-no-search>
            <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3">Number</th><th class="px-4 py-3">How it got here</th><th class="px-4 py-3 hidden sm:table-cell">Date</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody>
            @forelse ($blocked as $b)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-3 font-mono">+{{ $b->phone }}</td>
                    <td class="px-4 py-3">{{ $sourceLabels[$b->source] ?? $b->source }}</td>
                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap hidden sm:table-cell">{{ $b->created_at->timezone('Africa/Dar_es_Salaam')->format('M j, Y') }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('admin.compliance.blocked.remove', $b) }}" data-confirm="Unblock +{{ $b->phone }}? Only do this if the person themselves asked to receive messages again.">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost !w-auto !py-1.5 !px-2.5 text-xs">Unblock</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No numbers on the list yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($blocked->hasPages())
    <div class="mt-3 flex items-center justify-between text-sm">
        @if ($blocked->onFirstPage())<span></span>@else<a href="{{ $blocked->previousPageUrl() }}" class="btn btn-ghost !w-auto">&larr; Newer</a>@endif
        <span class="text-gray-400">Page {{ $blocked->currentPage() }} of {{ $blocked->lastPage() }}</span>
        @if ($blocked->hasMorePages())<a href="{{ $blocked->nextPageUrl() }}" class="btn btn-ghost !w-auto">Older &rarr;</a>@else<span></span>@endif
    </div>
    @endif
@endif
@endsection
