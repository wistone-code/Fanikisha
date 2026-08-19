@extends('layouts.app')
@section('title', 'Event Management — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Event Management</h2>
        @if ($isAdmin)<p class="text-sm text-gray-500">Organize pledgers into working groups.</p>@endif
    </div>
    @if ($isAdmin)
    <button onclick="document.getElementById('addCommitteeModal').classList.remove('hidden')" class="btn btn-primary" {{ $pledges->isEmpty() ? 'disabled title=Add pledges first' : '' }}>
        <i class="fa-solid fa-plus"></i> New committee
    </button>
    @endif
</div>

@if ($isAdmin)
<div class="card p-5 mb-4">
    <div class="text-xs font-semibold mb-2">Notification message <span class="text-gray-400 font-normal">— use {name}, {role}, {committee}</span></div>
    <form method="POST" action="{{ route('committees.message') }}">
        @csrf @method('PATCH')
        <textarea name="committee_message" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('committee') }}</textarea>
        <button class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Save message</button>
    </form>
</div>
@endif

@if ($committees->isEmpty())
<div class="card text-center py-12 text-gray-400">
    <i class="fa-solid fa-people-roof text-3xl mb-3"></i>
    <h3 class="font-serif text-lg text-gray-700 mb-1">No committees yet</h3>
    <p class="text-sm">Create a committee and assign titles to pledgers.</p>
</div>
@else
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <tbody>
        @foreach ($committees as $committee)
            <tr><td colspan="5" class="bg-gray-50 text-center text-[11px] uppercase tracking-wide text-gray-400 font-semibold px-4 py-2">Committee</td></tr>
            <tr class="border-b">
                <td class="px-4 py-3"><strong class="underline">{{ $committee->name }}</strong></td>
                <td colspan="2"></td>
                @if ($isAdmin)
                <td class="px-4 py-3 text-right"><button onclick="document.getElementById('editCommittee{{ $committee->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i> Edit</button></td>
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="{{ route('committees.destroy', $committee) }}" onsubmit="return confirm('Delete this committee?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                @endif
            </tr>

            <tr><td colspan="5" class="bg-gray-50 text-center text-[11px] uppercase tracking-wide text-gray-400 font-semibold px-4 py-2">Member</td></tr>
            @forelse ($committee->members as $member)
            <tr class="border-b">
                <td class="px-4 py-3">{{ $member->pledge->name ?? 'Removed pledger' }}</td>
                <td class="px-4 py-3">{{ $member->title }}</td>
                <td class="px-4 py-3">
                    <a href="{{ route('committees.members.sms', $member) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</a>
                    <a href="{{ route('committees.members.whatsapp', $member) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                </td>
                @if ($isAdmin)
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="{{ route('committees.members.destroy', $member) }}" onsubmit="return confirm('Remove this member from the committee?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                <td></td>
                @endif
            </tr>
            @empty
            <tr class="border-b"><td colspan="5" class="px-4 py-4 text-center text-gray-400 text-sm">No members yet</td></tr>
            @endforelse

            @if ($isAdmin)
            <div id="editCommittee{{ $committee->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[85vh] overflow-y-auto">
                    <h3 class="font-semibold mb-4">Edit committee</h3>
                    <form method="POST" action="{{ route('committees.update', $committee) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="text-xs font-semibold">Committee name</label><input type="text" name="name" value="{{ $committee->name }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <label class="text-xs font-semibold">Members</label>
                        @foreach ($committee->members as $i => $member)
                        <div class="flex gap-2">
                            <select name="members[{{ $i }}][pledge_id]" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                                @foreach ($pledges as $pl)<option value="{{ $pl->id }}" @selected($member->pledge_id === $pl->id)>{{ $pl->name }}</option>@endforeach
                            </select>
                            <input type="text" name="members[{{ $i }}][title]" value="{{ $member->title }}" placeholder="Title" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                        </div>
                        @endforeach
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="document.getElementById('editCommittee{{ $committee->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if ($isAdmin)
<div id="addCommitteeModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6">
        <h3 class="font-semibold mb-4">New committee</h3>
        <form method="POST" action="{{ route('committees.store') }}" class="space-y-3" id="newCommitteeForm">
            @csrf
            <div><label class="text-xs font-semibold">Committee name</label><input type="text" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <label class="text-xs font-semibold">Members</label>
            <div id="newCommitteeRows">
                <div class="flex gap-2 mb-2">
                    <select name="members[0][pledge_id]" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                        <option value="">Select pledger…</option>
                        @foreach ($pledges as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
                    </select>
                    <input type="text" name="members[0][title]" placeholder="Title e.g. Chairperson" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <button type="button" id="addCommitteeRowBtn" class="text-xs font-semibold" style="color:var(--primary);"><i class="fa-solid fa-plus"></i> Add member</button>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addCommitteeModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Create committee</button>
            </div>
        </form>
    </div>
</div>
<script>
let committeeRowCount = 1;
document.getElementById('addCommitteeRowBtn').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'flex gap-2 mb-2';
    row.innerHTML = `
        <select name="members[${committeeRowCount}][pledge_id]" class="flex-1 border rounded-lg px-3 py-2 text-sm">
            <option value="">Select pledger…</option>
            @foreach ($pledges as $pl)<option value="{{ $pl->id }}">{{ $pl->name }}</option>@endforeach
        </select>
        <input type="text" name="members[${committeeRowCount}][title]" placeholder="Title e.g. Chairperson" class="flex-1 border rounded-lg px-3 py-2 text-sm">
    `;
    document.getElementById('newCommitteeRows').appendChild(row);
    committeeRowCount++;
});
</script>
@endif
@endsection
