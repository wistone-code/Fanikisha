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
        <div class="relative inline-block">
            <button onclick="document.getElementById('exportMenu').classList.toggle('hidden')" class="btn btn-ghost"><i class="fa-solid fa-download"></i> Export <i class="fa-solid fa-chevron-down text-xs"></i></button>
            <div id="exportMenu" class="hidden absolute right-0 mt-1 w-36 bg-white text-[#1B2429] rounded-xl shadow-xl p-1 z-40">
                <a href="{{ route('pledges.export.excel') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50"><i class="fa-solid fa-file-excel"></i> Excel</a>
                <a href="{{ route('pledges.export.pdf') }}" class="block px-3 py-2 rounded-lg text-sm hover:bg-gray-50"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            </div>
        </div>
        @endif
        @if ($isAdmin)
        <button onclick="document.getElementById('importPledgesModal').classList.remove('hidden')" class="btn btn-ghost">
            <i class="fa-solid fa-file-import"></i> Import
        </button>
        @if (config('services.gemini.api_key'))
        <button onclick="document.getElementById('importPledgePhotoModal').classList.remove('hidden')" class="btn btn-ghost"><i class="fa-solid fa-camera"></i> Import from photo</button>
        @endif
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
                <th class="px-4 py-3" data-sort="text">RSVP</th>
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
                <td class="px-4 py-3">
                    @if (! $p->invite_token)
                    <span class="text-gray-400 text-xs">—</span>
                    @elseif ($p->rsvp_status === 'attending')
                    <span class="badge badge-admin"><i class="fa-solid fa-check text-[9px]"></i> Attending</span>
                    @elseif ($p->rsvp_status === 'not_attending')
                    <span class="text-xs text-gray-500">Not attending</span>
                    @else
                    <span class="text-xs text-gray-400">Awaiting response</span>
                    @endif
                </td>
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
                            <div><label class="text-xs font-semibold">Add payment</label><input type="number" name="add_payment" placeholder="0" min="0" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        </div>
                        <div class="text-xs text-gray-400">
                            Already paid: {{ number_format($p->paid) }}.
                            <button type="button" onclick="document.getElementById('correctPaid{{ $p->id }}').classList.toggle('hidden')" class="underline">Made a mistake? Correct total instead</button>
                        </div>
                        <div id="correctPaid{{ $p->id }}" class="hidden">
                            <label class="text-xs font-semibold">Correct total paid</label>
                            <input type="number" name="paid_correction" value="{{ $p->paid }}" min="0" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm">
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
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No {{ strtolower($label) }} yet.</td></tr>
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

<div id="importPledgesModal" class="{{ $errors->has('import_file') ? '' : 'hidden' }} fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[85vh] overflow-y-auto">
        <h3 class="font-semibold mb-1">Import {{ $isFuneral ? 'condolences' : 'pledges' }}</h3>
        <p class="text-xs text-gray-500 mb-4">Add many at once — upload a CSV, plain text, or Word (.docx) file, or paste rows directly. Either way, each row should be: <strong>Name, Phone, Amount</strong> (phone is optional).</p>
        @error('import_file')
        <div class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg px-3 py-2 mb-3">{{ $message }}</div>
        @enderror
        <form method="POST" action="{{ route('pledges.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-semibold">Upload a file</label>
                <input type="file" name="import_file" accept=".csv,.txt,.docx" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="text-center text-xs text-gray-400">— or —</div>
            <div>
                <label class="text-xs font-semibold">Paste rows</label>
                <textarea name="import_text" rows="6" placeholder="Juma Ally, 0712345678, 100000&#10;Asha Said, 0765432198, 50000" class="w-full border rounded-lg px-3 py-2 text-sm font-mono">{{ old('import_text') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">One person per line — copy straight from WhatsApp or a spreadsheet.</p>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('importPledgesModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Import</button>
            </div>
        </form>
    </div>
</div>

@if (config('services.gemini.api_key'))
<div id="importPledgePhotoModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-1">Import from photo</h3>
        <p class="text-xs text-gray-500 mb-4">Upload a photo of a pledge list — handwritten, printed, or a screenshot — and it'll be read automatically. Up to 4 photos, 10MB each.</p>

        <div id="importPledgePhotoStep1">
            <input type="file" id="importPledgePhotoInput" accept="image/*" multiple class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
            <div id="importPledgePhotoError" class="hidden text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-3"></div>
            <div class="flex gap-2 pt-1">
                <button type="button" onclick="closeImportPledgePhotoModal()" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button type="button" onclick="extractPledgePhoto()" class="btn btn-primary flex-1 justify-center"><i class="fa-solid fa-wand-magic-sparkles"></i> Extract</button>
            </div>
        </div>

        <div id="importPledgePhotoLoading" class="hidden text-center py-6">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-400"></i>
            <p class="text-sm text-gray-500 mt-2">Reading the pledge list…</p>
        </div>

        <!-- Temporary raw preview — editable review + confirm/save lands in the next phase, matching the Schedule page's photo import. -->
        <div id="importPledgePhotoResults" class="hidden">
            <p class="text-xs text-gray-500 mb-2">Found these pledgers (review &amp; edit coming next):</p>
            <div id="importPledgePhotoResultsList" class="space-y-1 text-sm max-h-64 overflow-y-auto mb-3"></div>
            <button type="button" onclick="closeImportPledgePhotoModal()" class="btn btn-ghost w-full justify-center">Close</button>
        </div>
    </div>
</div>

<script>
    const importPledgePhotoUrl = {{ Js::from(route('pledges.import-photo')) }};

    function closeImportPledgePhotoModal() {
        document.getElementById('importPledgePhotoModal').classList.add('hidden');
        document.getElementById('importPledgePhotoInput').value = '';
        document.getElementById('importPledgePhotoError').classList.add('hidden');
        document.getElementById('importPledgePhotoStep1').classList.remove('hidden');
        document.getElementById('importPledgePhotoLoading').classList.add('hidden');
        document.getElementById('importPledgePhotoResults').classList.add('hidden');
    }

    async function extractPledgePhoto() {
        const input = document.getElementById('importPledgePhotoInput');
        const errorEl = document.getElementById('importPledgePhotoError');
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

        document.getElementById('importPledgePhotoStep1').classList.add('hidden');
        document.getElementById('importPledgePhotoLoading').classList.remove('hidden');

        try {
            const res = await fetch(importPledgePhotoUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': {{ Js::from(csrf_token()) }}, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();

            document.getElementById('importPledgePhotoLoading').classList.add('hidden');

            if (!res.ok) {
                document.getElementById('importPledgePhotoStep1').classList.remove('hidden');
                errorEl.textContent = data.error || 'Something went wrong reading that photo. Try again.';
                errorEl.classList.remove('hidden');
                return;
            }

            const list = document.getElementById('importPledgePhotoResultsList');
            list.innerHTML = '';
            data.items.forEach(function (item) {
                const row = document.createElement('div');
                row.className = 'border rounded-lg px-3 py-2';

                const name = document.createElement('div');
                name.className = 'font-medium';
                name.textContent = item.name;

                const meta = document.createElement('div');
                meta.className = 'text-xs text-gray-500';
                meta.textContent = Number(item.amount).toLocaleString() + (item.phone ? ' — ' + item.phone : '');

                row.appendChild(name);
                row.appendChild(meta);
                list.appendChild(row);
            });
            document.getElementById('importPledgePhotoResults').classList.remove('hidden');
        } catch (e) {
            document.getElementById('importPledgePhotoLoading').classList.add('hidden');
            document.getElementById('importPledgePhotoStep1').classList.remove('hidden');
            errorEl.textContent = 'Could not reach the server. Check your connection and try again.';
            errorEl.classList.remove('hidden');
        }
    }
</script>
@endif{{-- gemini api key configured --}}
@endif
@endsection