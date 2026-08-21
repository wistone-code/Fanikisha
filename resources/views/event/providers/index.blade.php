@extends('layouts.app')
@section('title', 'Service Providers — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div>
        <h2 class="text-xl font-semibold">Service providers</h2>
        @if ($isAdmin)<p class="text-sm text-gray-500">Ceremony budget auto-calculates from provider costs.</p>@endif
    </div>
    <div class="flex gap-2 flex-wrap">
        @if ($providers->count())
        <a href="{{ route('providers.export.excel') }}" class="btn btn-ghost"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('providers.export.pdf') }}" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        @endif
        @if ($isAdmin)
        <button onclick="document.getElementById('addProviderModal').classList.remove('hidden')" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add provider</button>
        @endif
    </div>
</div>

<div class="card p-4 mb-4 max-w-xs">
    <div class="text-xs uppercase text-gray-400 font-semibold">Ceremony budget</div>
    <div class="text-2xl font-semibold mt-1">{{ number_format($total) }}</div>
    <div class="text-xs text-gray-400 mt-1">{{ $providers->count() }} provider{{ $providers->count() === 1 ? '' : 's' }}</div>
</div>

<div class="card overflow-x-auto mb-4">
    <table class="w-full text-sm sortable-table">
        <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
            <th class="px-4 py-3" data-sort="text">Name</th><th class="px-4 py-3" data-sort="text">Service</th><th class="px-4 py-3" data-sort="number">Budget</th><th class="px-4 py-3" data-sort="number">Paid</th><th class="px-4 py-3" data-sort="number">Balance</th>
            @if ($isAdmin)<th class="px-4 py-3">Contact</th><th class="px-4 py-3"></th>@endif
        </tr></thead>
        <tbody>
        @forelse ($providers as $p)
            <tr class="border-b last:border-0">
                <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                <td class="px-4 py-3">{{ $p->service }}</td>
                <td class="px-4 py-3">{{ number_format($p->budget) }}</td>
                <td class="px-4 py-3">{{ number_format($p->paid) }}</td>
                <td class="px-4 py-3">{{ number_format($p->remaining()) }}</td>
                @if ($isAdmin)
                <td class="px-4 py-3">
                    @if ($p->phone)
                    <form method="POST" action="{{ route('providers.sms', $p) }}" class="inline">
                        @csrf
                        <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                    </form>
                    <a href="{{ route('providers.whatsapp', $p) }}" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                    <form method="POST" action="{{ route('providers.confirm-payment.sms', $p) }}" class="inline" onsubmit="return confirm('Send payment confirmation SMS to {{ $p->name }}?')">
                        @csrf
                        <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-circle-check"></i> Payment Notification</button>
                    </form>
                    @else
                    <span class="text-gray-400 text-xs">No phone</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button onclick="document.getElementById('editProvider{{ $p->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-pen"></i> Edit</button>
                    <form method="POST" action="{{ route('providers.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete this provider?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger !py-1.5 !px-2.5"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                @endif
            </tr>
            @if ($isAdmin)
            <div id="editProvider{{ $p->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-sm w-full p-6">
                    <h3 class="font-semibold mb-4">Edit provider</h3>
                    <form method="POST" action="{{ route('providers.update', $p) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" value="{{ $p->name }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-semibold">Service</label><input type="text" name="service" value="{{ $p->service }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-semibold">Budget</label><input type="number" name="budget" value="{{ $p->budget }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-semibold">Paid</label><input type="number" name="paid" value="{{ $p->paid }}" step="0.01" min="0" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
                        <div><label class="text-xs font-semibold">Contact (phone)</label>
                            <div class="flex gap-1">
                                <input type="tel" id="phone-edit-{{ $p->id }}" name="phone" value="{{ $p->phone }}" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <button type="button" onclick="pickContact('phone-edit-{{ $p->id }}')" class="contact-pick-btn btn btn-ghost !px-2.5" title="Pick from contacts"><i class="fa-solid fa-address-book"></i></button>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-2">
                            <button type="button" onclick="document.getElementById('editProvider{{ $p->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                            <button class="btn btn-primary flex-1 justify-center">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No providers added yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if ($isAdmin)
<div id="addProviderModal" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <h3 class="font-semibold mb-4">Add provider</h3>
        <form method="POST" action="{{ route('providers.store') }}" class="space-y-3">
            @csrf
            <div><label class="text-xs font-semibold">Name</label><input type="text" id="name-add" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Service</label><input type="text" name="service" required placeholder="e.g. Catering, Photography" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Budget</label><input type="number" name="budget" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Contact (phone)</label>
                <div class="flex gap-1">
                    <input type="tel" id="phone-add" name="phone" placeholder="0712 345 678" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <button type="button" onclick="pickContact('phone-add', 'name-add')" class="contact-pick-btn btn btn-ghost !px-2.5" title="Pick from contacts"><i class="fa-solid fa-address-book"></i></button>
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addProviderModal').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Add provider</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection