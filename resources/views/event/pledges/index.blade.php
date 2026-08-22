@extends('layouts.app')
@section('title', app(\App\Services\NavLabelService::class)->for($event ?? app('currentEvent'))['pledges'].' — '.config('app.name'))

@php($event = $event ?? app('currentEvent'))
@php($isFuneral = $event->isFuneral())
@php($label = app(\App\Services\NavLabelService::class)->for($event)['pledges'])

@section('content')
@if ($isAdmin && !$isFuneral)
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <a href="{{ route('pledges.index') }}" class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">All pledges</a>
    <a href="{{ route('pledges.index', ['tab' => 'remind']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">Reminder</a>
</div>
@endif

<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <h2 class="text-xl font-semibold">{{ $label }}</h2>
    <div class="flex gap-2 flex-wrap">
        @if ($pledges->count())
        <a href="{{ route('pledges.export.excel') }}" class="btn btn-ghost"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('pledges.export.pdf') }}" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        @endif
        @if ($isAdmin)
        <button onclick="document.getElementById('importPledgesModal').classList.remove('hidden')" class="btn btn-ghost">
            <i class="fa-solid fa-file-import"></i> Import
        </button>
        <button onclick="document.getElementById('addPledgeModal').classList.remove('hidden')" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add {{ $isFuneral ? 'condolence' : 'pledge' }}
        </button>
        @endif
    </div>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm sortable-table">
        <thead>
            <tr class="text-left text-xs uppercase text-gray-400 border-b">
                <th class="px-4 py-3" data-sort="text">Name</th>
                @if ($isFuneral)
                    <th class="px-4 py-3" data-sort="number">Contribution</th>
                @else
                    <th class="px-4 py-3" data-sort="number">Pledge amount</th>
                    <th class="px-4 py-3" data-sort="number">Paid</th>
                    <th class="px-4 py-3" data-sort="number">Remain</th>
                    <th class="px-4 py-3" data-sort="text">Phone</th>
                @endif
                @if ($isAdmin)<th class="px-4 py-3"></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($pledges as $p)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                @if ($isFuneral)
                    <td class="px-4 py-3">{{ number_format($p->paid) }}</td>
                @else
                    <td class="px-4 py-3">{{ number_format($p->amount) }}</td>
                    <td class="px-4 py-3">{{ number_format($p->paid) }}</td>
                    <td class="px-4 py-3">{{ number_format($p->remaining()) }}</td>
                    <td class="px-4 py-3">{{ $p->phone ?? '—' }}</td>
                @endif
                @if ($isAdmin)
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="document.getElementById('editPledge{{ $p->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i></button>
                    <form method="POST" action="{{ route('pledges.destroy', $p) }}" class="inline" data-confirm="Delete this pledge? This cannot be undone." data-confirm-title="Delete pledge?">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
                @endif
            </tr>

            @if ($isAdmin)
            <div id="editPledge{{ $p->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-4">Edit {{ $isFuneral ? 'condolence' : 'pledge' }}</h3>
                    <form method="POST" action="{{ route('pledges.update', $p) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="text-xs font-semibold">Name</label><input type="text" id="name-edit-{{ $p->id }}" name="name" value="{{ $p->name }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div>
                            <label class="text-xs font-semibold">Phone</label>
                            <div class="flex gap-1">
                                <input type="tel" id="phone-edit-{{ $p->id }}" name="phone" value="{{ $p->phone }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <button type="button" onclick="pickContact('phone-edit-{{ $p->id }}')" class="contact-pick-btn btn btn-ghost !px-2.5" title="Pick from contacts"><i class="fa-solid fa-address-book"></i></button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs font-semibold">Pledge amount</label><input type="number" name="amount" value="{{ $p->amount }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                            <div><label class="text-xs font-semibold">Paid</label><input type="number" name="paid" value="{{ $p->paid }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="document.getElementById('editPledge{{ $p->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
            @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">No {{ strtolower($label) }} yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($isAdmin)
<div id="addPledgeModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add {{ $isFuneral ? 'condolence' : 'pledge' }}</h3>
        <form method="POST" action="{{ route('pledges.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Name</label><input type="text" id="name-add" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div>
                <label class="text-xs font-semibold">Phone</label>
                <div class="flex gap-1">
                    <input type="tel" id="phone-add" name="phone" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <button type="button" onclick="pickContact('phone-add', 'name-add')" class="contact-pick-btn btn btn-ghost !px-2.5" title="Pick from contacts"><i class="fa-solid fa-address-book"></i></button>
                </div>
            </div>
            <div><label class="text-xs font-semibold">Pledge amount</label><input type="number" name="amount" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addPledgeModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add {{ $isFuneral ? 'condolence' : 'pledge' }}</button>
            </div>
        </form>
    </div>
</div>

<div id="importPledgesModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[85vh] overflow-y-auto">
        <h3 class="font-semibold mb-1">Import {{ $isFuneral ? 'condolences' : 'pledges' }}</h3>
        <p class="text-xs text-gray-500 mb-4">Add many at once — upload a CSV file, or paste rows directly. Either way, each row should be: <strong>Name, Phone, Amount</strong> (phone is optional).</p>
        <form method="POST" action="{{ route('pledges.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-semibold">Upload a CSV file</label>
                <input type="file" name="import_file" accept=".csv,.txt" class="w-full border rounded-lg px-3 py-2 text-sm">
                @error('import_file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="text-center text-xs text-gray-400">— or —</div>
            <div>
                <label class="text-xs font-semibold">Paste rows</label>
                <textarea name="import_text" rows="6" placeholder="Juma Ally, 0712345678, 100000&#10;Asha Said, 0765432198, 50000" class="w-full border rounded-lg px-3 py-2 text-sm font-mono"></textarea>
                <p class="text-xs text-gray-400 mt-1">One person per line — copy straight from WhatsApp or a spreadsheet.</p>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('importPledgesModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Import</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection