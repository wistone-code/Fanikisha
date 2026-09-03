@extends('layouts.app')
@section('title', 'Logs — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Account &amp; event logs</h2>
        <p class="text-sm text-gray-500">
            @if ($filteredUser)
                Showing activity for <strong>{{ $filteredUser->name }}</strong> ({{ $filteredUser->username }}).
                <a href="{{ route('admin.logs.index') }}" class="underline">Clear filter</a>
            @else
                Every account creation, edit, password reset, and event creation across all accounts.
            @endif
        </p>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back to accounts</a>
</div>

<div class="card p-4 mb-4 space-y-3">
    <div class="flex flex-wrap gap-2">
        @php
            $periods = ['all' => 'All time', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month'];
        @endphp
        @foreach ($periods as $key => $label)
        <a href="{{ route('admin.logs.index', array_filter(['user' => $filteredUser?->id, 'q' => $search ?: null, 'period' => $key === 'all' ? null : $key])) }}"
           class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $period === $key ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-3">
        @if ($filteredUser)<input type="hidden" name="user" value="{{ $filteredUser->id }}">@endif
        @if ($period !== 'all')<input type="hidden" name="period" value="{{ $period }}">@endif
        <input type="text" name="q" value="{{ $search }}" placeholder="Search log descriptions…" class="flex-1 min-w-[200px] border rounded-lg px-3 py-2 text-sm">
        <button class="btn btn-ghost">Search</button>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Actor</th>
                <th class="px-4 py-3">Action</th>
                <th class="px-4 py-3">Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
            <tr class="border-b last:border-0 align-top">
                <td class="px-4 py-3 whitespace-nowrap text-gray-500 log-timestamp" data-utc="{{ $log->created_at->clone()->timezone('UTC')->toIso8601String() }}">{{ $log->created_at->timezone('Africa/Dar_es_Salaam')->format('M j, Y g:i A') }} <span class="text-xs text-gray-400">GMT+3</span></td>
                <td class="px-4 py-3 whitespace-nowrap">
                    @if ($log->actor)
                        {{ $log->actor->name }}
                        @if ($log->actor->is_super_user)<span class="badge badge-admin ml-1">System</span>@endif
                    @else
                        <span class="text-gray-400">System</span>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <span class="badge {{ str_starts_with($log->action, 'account.') ? 'badge-admin' : 'badge-viewer' }}">{{ $log->action }}</span>
                </td>
                <td class="px-4 py-3 text-gray-700">{{ $log->description }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">
                @if ($search || $filteredUser)
                No log entries match this filter.
                @else
                No activity recorded yet.
                @endif
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($logs->hasPages())
<div class="flex justify-between items-center mt-4 text-sm">
    <div class="text-xs text-gray-400">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}</div>
    <div class="flex gap-2">
        @if ($logs->onFirstPage())
        <span class="btn btn-ghost opacity-40 cursor-not-allowed">Previous</span>
        @else
        <a href="{{ $logs->previousPageUrl() }}" class="btn btn-ghost">Previous</a>
        @endif
        @if ($logs->hasMorePages())
        <a href="{{ $logs->nextPageUrl() }}" class="btn btn-ghost">Next</a>
        @else
        <span class="btn btn-ghost opacity-40 cursor-not-allowed">Next</span>
        @endif
    </div>
</div>
@endif

<script>
// Converts each log timestamp from the server-rendered GMT+3 fallback to
// whoever is actually viewing this page's own local timezone, using the raw
// UTC instant stashed in data-utc. Runs once on load — safe to leave the
// GMT+3 text in place as a no-JS fallback (matches Fanikisha's home base,
// Tanzania) if this script doesn't run for any reason.
document.querySelectorAll('.log-timestamp').forEach(function (cell) {
    const iso = cell.dataset.utc;
    if (!iso) return;

    const date = new Date(iso);
    if (isNaN(date)) return;

    const formatted = date.toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit',
    });
    const tzAbbr = Intl.DateTimeFormat(undefined, { timeZoneName: 'short' })
        .formatToParts(date)
        .find(function (p) { return p.type === 'timeZoneName'; })?.value || '';

    cell.innerHTML = formatted + ' <span class="text-xs text-gray-400">' + tzAbbr + '</span>';
});
</script>
@endsection
