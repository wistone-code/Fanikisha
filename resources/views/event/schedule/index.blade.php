@extends('layouts.app')
@section('title', 'Ceremony Schedule — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Ceremony schedule</h2>
        @if ($isAdmin)<p class="text-sm text-gray-500">Plan out the run of show — event and time for each item.</p>@endif
    </div>
    <div class="flex gap-2 flex-wrap">
        @if ($isAdmin && trim($broadcastMessage) !== '' && $pledgers->isNotEmpty())
        <form method="POST" action="{{ route('schedule.broadcast') }}" class="inline" onsubmit="return confirm('Send this schedule via SMS to all pledgers now?')">
            @csrf
            <button class="btn btn-primary">Share schedule</button>
        </form>
        @endif
        @if ($items->count())
        <a href="{{ route('schedule.export.excel') }}" class="btn btn-ghost"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('schedule.export.pdf') }}" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        @endif
        @if ($isAdmin)
        <button onclick="document.getElementById('importPhotoModal').classList.remove('hidden')" class="btn btn-ghost"><i class="fa-solid fa-camera"></i> Import from photo</button>
        <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add item</button>
        @endif
    </div>
</div>

<div class="card overflow-x-auto mb-4">
    <table class="w-full text-sm sortable-table">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b"><th class="px-4 py-3" data-sort="text">Event</th><th class="px-4 py-3" data-sort="text">Date</th><th class="px-4 py-3" data-sort="text">Time</th>@if($isAdmin)<th class="px-4 py-3"></th>@endif</tr></thead>
        <tbody>
        @forelse ($items as $item)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $item->title }}</td>
                <td class="px-4 py-3">{{ $item->date->format('M j, Y') }}</td>
                <td class="px-4 py-3">{{ $item->time ? \Carbon\Carbon::parse($item->time)->format('g:i A') : '—' }}</td>
                @if ($isAdmin)
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="document.getElementById('editItem{{ $item->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i> Edit</button>
                    <form method="POST" action="{{ route('schedule.destroy', $item) }}" class="inline" data-confirm="Delete this schedule item?" data-confirm-title="Delete item?">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                @endif
            </tr>
            @if ($isAdmin)
            <div id="editItem{{ $item->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-4">Edit schedule item</h3>
                    <form method="POST" action="{{ route('schedule.update', $item) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="text-xs font-semibold">Event</label><input type="text" name="title" value="{{ $item->title }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs font-semibold">Date</label><input type="date" name="date" value="{{ $item->date->format('Y-m-d') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                            <div><label class="text-xs font-semibold">Time</label><input type="time" name="time" value="{{ $item->time }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="document.getElementById('editItem{{ $item->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        @empty
            <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No schedule items yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if ($isAdmin)
<div class="card p-5 mb-4 max-w-xl">
    <div class="text-xs font-semibold mb-2">Broadcast message <span class="text-gray-400 font-normal">— use {event}, {place}, {date}. Write your own — there's no starter text.</span></div>
    <form method="POST" action="{{ route('schedule.message') }}" class="mb-4">
        @csrf @method('PATCH')
        <textarea name="schedule_message" rows="5" placeholder="Write the schedule announcement…" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('schedule') }}</textarea>
        @error('schedule_message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-sm mt-2"><i class="fa-solid fa-check"></i> Save message</button>
    </form>
    @if (trim($broadcastMessage) === '')
    <p class="text-xs text-gray-400 border-t pt-4">Save a message above, then use "Share schedule" at the top of the page to text all pledgers.</p>
    @elseif ($pledgers->isEmpty())
    <p class="text-xs text-gray-400 border-t pt-4">No contacts with a phone number to message.</p>
    @endif
</div>
@endif

@if ($isAdmin)
<div id="addScheduleModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add schedule item</h3>
        <form method="POST" action="{{ route('schedule.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Event</label><input type="text" name="title" required placeholder="e.g. Vows, Reception" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="text-xs font-semibold">Date</label><input type="date" name="date" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="text-xs font-semibold">Time</label><input type="time" name="time" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add item</button>
            </div>
        </form>
    </div>
</div>

<div id="importPhotoModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-1">Import from photo</h3>
        <p class="text-xs text-gray-500 mb-4">Upload a photo of a schedule — handwritten, printed, or a screenshot — and it'll be read automatically. Up to 4 photos, 10MB each.</p>

        <div id="importPhotoStep1">
            <input type="file" id="importPhotoInput" accept="image/*" multiple class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
            <div id="importPhotoError" class="hidden text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3"></div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeImportPhotoModal()" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button type="button" id="importPhotoExtractBtn" onclick="extractSchedulePhoto()" class="btn btn-primary flex-1 justify-center"><i class="fa-solid fa-wand-magic-sparkles"></i> Extract</button>
            </div>
        </div>

        <div id="importPhotoLoading" class="hidden text-center py-6">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-400"></i>
            <p class="text-sm text-gray-500 mt-2">Reading the schedule…</p>
        </div>

        <!-- Temporary raw preview — editable review + confirm/save lands in the next phase. -->
        <div id="importPhotoResults" class="hidden">
            <p class="text-xs text-gray-500 mb-2">Found these items (review &amp; edit coming next):</p>
            <div id="importPhotoResultsList" class="space-y-1 text-sm max-h-64 overflow-y-auto mb-3"></div>
            <button type="button" onclick="closeImportPhotoModal()" class="btn btn-ghost w-full justify-center">Close</button>
        </div>
    </div>
</div>

<script>
    const importPhotoUrl = {{ Js::from(route('schedule.import-photo')) }};

    function closeImportPhotoModal() {
        document.getElementById('importPhotoModal').classList.add('hidden');
        document.getElementById('importPhotoInput').value = '';
        document.getElementById('importPhotoError').classList.add('hidden');
        document.getElementById('importPhotoStep1').classList.remove('hidden');
        document.getElementById('importPhotoLoading').classList.add('hidden');
        document.getElementById('importPhotoResults').classList.add('hidden');
    }

    async function extractSchedulePhoto() {
        const input = document.getElementById('importPhotoInput');
        const errorEl = document.getElementById('importPhotoError');
        errorEl.classList.add('hidden');

        if (!input.files || input.files.length === 0) {
            errorEl.textContent = 'Choose at least one photo first.';
            errorEl.classList.remove('hidden');
            return;
        }
        if (input.files.length > 4) {
            errorEl.textContent = 'Up to 4 photos at a time.';
            errorEl.classList.remove('hidden');
            return;
        }

        const formData = new FormData();
        for (const file of input.files) formData.append('photos[]', file);

        document.getElementById('importPhotoStep1').classList.add('hidden');
        document.getElementById('importPhotoLoading').classList.remove('hidden');

        try {
            const res = await fetch(importPhotoUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }}, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();

            document.getElementById('importPhotoLoading').classList.add('hidden');

            if (!res.ok) {
                document.getElementById('importPhotoStep1').classList.remove('hidden');
                errorEl.textContent = data.error || 'Something went wrong reading that photo. Try again.';
                errorEl.classList.remove('hidden');
                return;
            }

            const list = document.getElementById('importPhotoResultsList');
            list.innerHTML = '';
            data.items.forEach(function (item) {
                const row = document.createElement('div');
                row.className = 'border rounded-lg px-3 py-2';

                const title = document.createElement('div');
                title.className = 'font-medium';
                title.textContent = item.title;

                const meta = document.createElement('div');
                meta.className = 'text-xs text-gray-500';
                meta.textContent = item.date + (item.time ? ' at ' + item.time : '');

                row.appendChild(title);
                row.appendChild(meta);
                list.appendChild(row);
            });
            document.getElementById('importPhotoResults').classList.remove('hidden');
        } catch (e) {
            document.getElementById('importPhotoLoading').classList.add('hidden');
            document.getElementById('importPhotoStep1').classList.remove('hidden');
            errorEl.textContent = 'Could not reach the server. Check your connection and try again.';
            errorEl.classList.remove('hidden');
        }
    }
</script>
@endif
@endsection