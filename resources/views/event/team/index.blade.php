@extends('layouts.app')
@section('title', 'Team Management — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Team</h2>
        <p class="text-sm text-gray-500">Members with access to {{ $event->name }}.</p>
    </div>
    <button onclick="document.getElementById('addMemberModal').classList.remove('hidden')" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add member</button>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3">Name</th><th class="px-4 py-3">Username</th><th class="px-4 py-3">Role</th><th class="px-4 py-3"></th></tr></thead>
        <tbody>
        @forelse ($members as $member)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $member->user->name }}</td>
                <td class="px-4 py-3">{{ $member->user->username }}</td>
                <td class="px-4 py-3">
                    <span class="badge {{ $member->role === 'admin' ? 'badge-admin' : 'badge-viewer' }}">{{ ucfirst($member->role) }}</span>
                    @if ($member->isOwner())<span class="badge badge-viewer ml-1"><i class="fa-solid fa-star text-[9px]"></i> Owner</span>@endif
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if ($member->user_id === auth()->id())
                        <button onclick="document.getElementById('accountSettingsModal').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-key"></i> Change password</button>
                    @else
                        <form method="POST" action="{{ route('team.reset-password', $member) }}" class="inline" onsubmit="return confirm('Reset this member\'s password? A new temporary password will be generated.')">
                            @csrf
                            <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-key"></i> Reset password</button>
                        </form>
                    @endif
                    @unless ($member->isOwner())
                    <form method="POST" action="{{ route('team.destroy', $member) }}" class="inline" data-confirm="Remove this member from the event?" data-confirm-title="Remove member?">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-user-minus"></i> Remove</button>
                    </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No team members yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div id="addMemberModal" class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add team member</h3>
        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-3">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('team.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Username</label><input type="text" name="username" value="{{ old('username') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Email <span id="emailHint" class="text-gray-400 font-normal">(required for Admin role)</span></label><input type="email" name="email" id="memberEmail" value="{{ old('email') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <p class="text-xs text-gray-400">A temporary password will be generated automatically and shown once after adding.</p>
            <div>
                <label class="text-xs font-semibold">Role</label>
                <select name="role" id="memberRole" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="admin" {{ old('role', 'admin') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="viewer" {{ old('role') === 'viewer' ? 'selected' : '' }}>Viewer</option>
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addMemberModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add member</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('memberRole').addEventListener('change', function(){
    const hint = document.getElementById('emailHint');
    hint.textContent = this.value === 'admin' ? '(required for Admin role)' : '(optional, for contact only)';
});
document.getElementById('memberRole').dispatchEvent(new Event('change'));
</script>
@endsection
