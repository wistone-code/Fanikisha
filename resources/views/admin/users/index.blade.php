@extends('layouts.app')
@section('title', 'User Management — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">All accounts</h2>
        <p class="text-sm text-gray-500">Create accounts. The admin account cannot see event data.</p>
    </div>
    <button onclick="document.getElementById('newAccountModal').classList.remove('hidden')" class="btn btn-primary">
        <i class="fa-solid fa-user-plus"></i> New account
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div class="card p-5"><div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-users"></i> Total accounts</div><div class="text-xl font-semibold mt-1">{{ $totalAccounts }}</div></div>
    <a href="{{ route('admin.users.index', ['status' => 'attention', 'q' => $search ?: null]) }}" class="card p-5 block {{ $atQuotaCount > 0 ? 'ring-1 ring-red-200' : '' }}">
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-triangle-exclamation"></i> Needs attention</div>
        <div class="text-xl font-semibold mt-1 {{ $atQuotaCount > 0 ? 'text-red-600' : '' }}">{{ $atQuotaCount }}</div>
        <div class="text-xs text-gray-400 mt-1">at/over SMS quota</div>
    </a>
    <a href="{{ route('admin.users.index', ['status' => 'no_event', 'q' => $search ?: null]) }}" class="card p-5 block">
        <div class="text-xs uppercase text-gray-400 font-semibold"><i class="fa-solid fa-hourglass-half"></i> No event yet</div>
        <div class="text-xl font-semibold mt-1">{{ $noEventCount }}</div>
    </a>
</div>

<div class="card p-4 mb-4">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search by name or username…" class="flex-1 min-w-[200px] border rounded-lg px-3 py-2 text-sm">
        <div class="flex gap-1 text-xs font-semibold">
            <a href="{{ route('admin.users.index', ['q' => $search ?: null]) }}" class="px-3 py-1.5 rounded-full {{ $status === 'all' ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500' }}">All</a>
            <a href="{{ route('admin.users.index', ['status' => 'attention', 'q' => $search ?: null]) }}" class="px-3 py-1.5 rounded-full {{ $status === 'attention' ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500' }}">Needs attention</a>
            <a href="{{ route('admin.users.index', ['status' => 'no_event', 'q' => $search ?: null]) }}" class="px-3 py-1.5 rounded-full {{ $status === 'no_event' ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-500' }}">No event yet</a>
        </div>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm sortable-table">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3" data-sort="text">Username</th>
                <th class="px-4 py-3" data-sort="text">Email</th>
                <th class="px-4 py-3" data-sort="text">Role</th>
                <th class="px-4 py-3">Event</th>
                <th class="px-4 py-3" data-sort="number">SMS Quota</th>
                <th class="px-4 py-3" data-sort="text">Created by</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
            <tr class="border-b last:border-0 {{ $account->at_quota ? 'bg-red-50' : '' }}">
                <td class="px-4 py-3 font-semibold">{{ $account->username }}</td>
                <td class="px-4 py-3">{{ $account->email }}</td>
                <td class="px-4 py-3"><span class="badge {{ $account->role_label === 'Admin' ? 'badge-admin' : 'badge-viewer' }}">{{ $account->role_label }}</span></td>
                <td class="px-4 py-3">
                    @if ($account->event_name)
                    <div class="font-medium">{{ $account->event_name }}</div>
                    <div class="text-xs text-gray-400">{{ $account->event_type }} @if($account->event_date) &middot; {{ $account->event_date->format('M j, Y') }} @endif</div>
                    @else
                    <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if (! $account->event_id)
                    <span class="text-gray-400 text-xs">No event yet</span>
                    @else
                    <span class="text-xs {{ $account->at_quota ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                        {{ $account->sms_sent_count }} / {{ $account->sms_quota ?? '∞' }}
                        @if ($account->at_quota)<i class="fa-solid fa-triangle-exclamation ml-1"></i>@endif
                    </span>
                    <button onclick="document.getElementById('editQuota{{ $account->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1 !px-2 ml-1"><i class="fa-solid fa-pen text-xs"></i></button>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-500">{{ $account->created_by_label }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="document.getElementById('editEmail{{ $account->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-envelope"></i> Edit email</button>
                    <form method="POST" action="{{ route('admin.users.reset-password', $account) }}" class="inline" onsubmit="return confirm('Reset {{ $account->name }}\'s password? A new temporary password will be generated.')">
                        @csrf
                        <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-key"></i> Reset password</button>
                    </form>
                    <form method="POST" action="{{ route('admin.users.destroy', $account) }}" class="inline" data-confirm="Delete {{ $account->name }}? This removes their account and all event memberships." data-confirm-title="Delete account?">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>

            <div id="editEmail{{ $account->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-1">Edit email</h3>
                    <p class="text-sm text-gray-500 mb-4">Update the email on file for <strong>{{ $account->name }}</strong> ({{ $account->username }}).</p>
                    <form method="POST" action="{{ route('admin.users.email', $account) }}">
                        @csrf @method('PATCH')
                        <input type="email" name="email" value="{{ $account->email }}" required class="w-full border rounded-lg px-3 py-2 text-sm mb-4">
                        <div class="flex gap-2">
                            <button type="button" onclick="document.getElementById('editEmail{{ $account->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save email</button>
                        </div>
                    </form>
                </div>
            </div>

            @if ($account->event_id)
            <div id="editQuota{{ $account->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-1">Edit SMS quota</h3>
                    <p class="text-sm text-gray-500 mb-4">Cap for <strong>{{ $account->name }}</strong>. Currently used: {{ $account->sms_sent_count }}. Leave blank for unlimited.</p>
                    <form method="POST" action="{{ route('admin.users.sms-quota', $account) }}">
                        @csrf @method('PATCH')
                        <input type="number" name="sms_quota" value="{{ $account->sms_quota }}" min="0" placeholder="Unlimited" class="w-full border rounded-lg px-3 py-2 text-sm mb-4">
                        <div class="flex gap-2">
                            <button type="button" onclick="document.getElementById('editQuota{{ $account->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save quota</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">
                @if ($status !== 'all' || $search)
                No accounts match this filter.
                @else
                No accounts yet. Create the first one to get started.
                @endif
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($accounts->hasPages())
<div class="flex justify-between items-center mt-4 text-sm">
    <div class="text-xs text-gray-400">Showing {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} of {{ $accounts->total() }}</div>
    <div class="flex gap-2">
        @if ($accounts->onFirstPage())
        <span class="btn btn-ghost opacity-40 cursor-not-allowed">Previous</span>
        @else
        <a href="{{ $accounts->previousPageUrl() }}" class="btn btn-ghost">Previous</a>
        @endif
        @if ($accounts->hasMorePages())
        <a href="{{ $accounts->nextPageUrl() }}" class="btn btn-ghost">Next</a>
        @else
        <span class="btn btn-ghost opacity-40 cursor-not-allowed">Next</span>
        @endif
    </div>
</div>
@endif

<div id="newAccountModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Create new account</h3>
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Username</label><input type="text" name="username" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Email</label><input type="email" name="email" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <p class="text-xs text-gray-400"><i class="fa-solid fa-wand-magic-sparkles"></i> A temporary password will be generated automatically.</p>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('newAccountModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Create account</button>
            </div>
        </form>
    </div>
</div>
@endsection
