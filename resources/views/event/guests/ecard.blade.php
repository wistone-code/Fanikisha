@extends('layouts.app')
@section('title', 'Guest E-cards — '.config('app.name'))

@section('content')
@php($optOuts = app(\App\Services\OptOutService::class))
@php($optSet = $optOuts->suppressedAmong($guests->pluck('phone')->all()))
@include('event.guests._tabs', ['active' => 'guests'])

<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Guest e-cards</h2>
        <p class="text-sm text-gray-500">{{ $guests->count() }} guest(s). Every card link is active as soon as the guest is added — send it by SMS, WhatsApp, or copy the link.</p>
    </div>
    @if ($isAdmin)
    <div class="flex gap-2 flex-wrap">
        <button onclick="document.getElementById('importGuestsModal').classList.remove('hidden')" class="btn btn-ghost"><i class="fa-solid fa-file-import"></i> Import</button>
        <button onclick="document.getElementById('addGuestModal').classList.remove('hidden')" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add guest</button>
    </div>
    @endif
</div>

<div class="grid grid-cols-1 {{ $isAdmin ? 'lg:grid-cols-3' : '' }} gap-5 items-start">
    <div class="{{ $isAdmin ? 'lg:col-span-2' : '' }}">
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Card</th>
                    <th class="px-4 py-3">RSVP</th>
                    @if ($isAdmin)<th class="px-4 py-3"></th>@endif
                </tr></thead>
                <tbody>
                @forelse ($guests as $g)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $g->name }}@if ($optOuts->inSet($optSet, $g->phone))<div class="mt-1"><span class="badge" style="background:#fde8e8;color:#b42318;" title="This person asked not to receive messages. Fanikisha will not send to this number."><i class="fa-solid fa-ban text-[9px]"></i> Opted out</span></div>@endif</td>
                        <td class="px-4 py-3 text-gray-600">{{ $g->phone ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="badge badge-viewer">{{ ucfirst($g->card_type) }}</span></td>
                        <td class="px-4 py-3">
                            @if ($g->rsvp_status === 'attending')
                                <span class="badge badge-admin"><i class="fa-solid fa-check text-[9px]"></i>&nbsp;Attending</span>
                            @elseif ($g->rsvp_status === 'not_attending')
                                <span class="text-xs text-gray-500">Not attending</span>
                            @else
                                <span class="text-xs text-gray-400">Awaiting</span>
                            @endif
                        </td>
                        @if ($isAdmin)
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" data-copy="{{ $g->inviteLink() }}" class="btn btn-ghost !py-1.5 !px-2.5 copy-link-btn" title="Copy card link"><i class="fa-solid fa-link"></i></button>
                            @if ($optOuts->inSet($optSet, $g->phone))
                            <span class="text-xs text-gray-400">Will not be messaged</span>
                            @else
                            @if ($g->phone)
                            <form method="POST" action="{{ route('guests.sms', $g) }}" class="inline">
                                @csrf
                                <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                            </form>
                            @endif
                            <a href="{{ route('guests.whatsapp', $g) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i></a>
                            @endif
                            <button type="button" onclick="document.getElementById('editGuest{{ $g->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" action="{{ route('guests.destroy', $g) }}" class="inline" data-confirm="Remove this guest? Their card link will stop working." data-confirm-title="Remove guest?">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No guests yet. Add one or import a list.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($isAdmin)
    <div>
        <div class="mb-3"><h2 class="text-xl font-semibold">Card message</h2><p class="text-sm text-gray-500">The text sent with each card link.</p></div>
        <div class="card p-5">
            <form method="POST" action="{{ route('guests.message.invitation') }}">
                @csrf @method('PATCH')
                <textarea name="invitation_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('invitation', false) }}</textarea>
                <p class="text-[11px] text-gray-400 mt-1">You can use {name} {event_name} {event_type} {date} {place} {link}</p>
                @error('invitation_message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
            </form>
        </div>
    </div>
    @endif
</div>

@if ($isAdmin)
{{-- Add guest --}}
<div id="addGuestModal" class="{{ $errors->hasAny(['name', 'phone', 'card_type']) ? '' : 'hidden' }} fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add guest</h3>
        <form method="POST" action="{{ route('guests.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Phone</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm"><p class="text-xs text-gray-400 mt-1">Optional — without a phone you can still copy the card link.</p></div>
            <div>
                <label class="text-xs font-semibold">Card</label>
                <select name="card_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="single">Single</option>
                    <option value="double">Double / Couple</option>
                </select>
            </div>
            @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addGuestModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add guest</button>
            </div>
        </form>
    </div>
</div>

{{-- Import --}}
<div id="importGuestsModal" class="{{ $errors->has('import_file') ? '' : 'hidden' }} fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[85vh] overflow-y-auto">
        <h3 class="font-semibold mb-1">Import guests</h3>
        <p class="text-xs text-gray-500 mb-4">Upload a CSV or text file, or paste rows. Each row: <strong>Name, Phone, Card</strong> — phone and card are optional (card is <em>single</em> or <em>double</em>). Up to 500 at a time.</p>
        @error('import_file')
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-3">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('guests.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div><label class="text-xs font-semibold">Upload a file</label><input type="file" name="import_file" accept=".csv,.txt" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div class="text-center text-xs text-gray-400">— or —</div>
            <div>
                <label class="text-xs font-semibold">Paste rows</label>
                <textarea name="import_text" rows="6" placeholder="Juma Ally, 0712345678&#10;Asha & Said, 0765432198, double" class="w-full border rounded-lg px-3 py-2 text-sm font-mono">{{ old('import_text') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">One guest per line — copy straight from WhatsApp or a spreadsheet.</p>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('importGuestsModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Import</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit guest (one per row) --}}
@foreach ($guests as $g)
<div id="editGuest{{ $g->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Edit guest</h3>
        <form method="POST" action="{{ route('guests.update', $g) }}" class="space-y-3">
            @csrf @method('PATCH')
            <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" value="{{ $g->name }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Phone</label><input type="tel" name="phone" value="{{ $g->phone }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div>
                <label class="text-xs font-semibold">Card</label>
                <select name="card_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="single" @selected($g->card_type === 'single')>Single</option>
                    <option value="double" @selected($g->card_type === 'double')>Double / Couple</option>
                </select>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editGuest{{ $g->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Save changes</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<script>
document.querySelectorAll('.copy-link-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const link = btn.dataset.copy;
        const done = function () {
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(function () { btn.innerHTML = original; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(done, function () { window.prompt('Copy this link:', link); });
        } else {
            window.prompt('Copy this link:', link);
        }
    });
});
</script>
@endif
@endsection
