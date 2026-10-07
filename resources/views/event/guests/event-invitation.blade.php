@extends('layouts.app')
@section('title', 'Guest Management — '.config('app.name'))

@section('content')
@php($optOuts = app(\App\Services\OptOutService::class))
@php($cards = $event->hasFeature('cards'))
@php($optSet = $optOuts->suppressedAmong($pledges->pluck('phone')->all()))
@include('event.guests._tabs', ['active' => 'event'])

@if ($errors->any())
<div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#fde8e8;color:#b42318;">{{ $errors->first() }}</div>
@endif

@if ($isAdmin)
<div class="mb-5">
    <button type="button" id="addGuestBtn" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add guest</button>
    <div id="addGuestPanel" class="card p-5 mt-3 max-w-md {{ $errors->has('name') || $errors->has('phone') || $errors->has('card_type') ? '' : 'hidden' }}">
        <p class="text-sm text-gray-500 mb-3">For family, VIPs or anyone who is not a contributor. Their link is ready at once and they never appear in pledges or finance.</p>
        <form method="POST" action="{{ route('guests.invite.new') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Name" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input name="phone" value="{{ old('phone') }}" maxlength="32" inputmode="tel" placeholder="Phone (0712 345 678)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <div><label class="text-xs font-semibold">Card type</label>
                <select name="card_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="single" @selected(old('card_type', 'single') === 'single')>Single card (one person)</option>
                    <option value="double" @selected(old('card_type') === 'double')>Double card (couple, two people)</option>
                </select></div>
            <div class="flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save guest</button>
                <button type="button" id="addGuestCancel" class="btn btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var panel = document.getElementById('addGuestPanel');
    document.getElementById('addGuestBtn').addEventListener('click', function () {
        panel.classList.toggle('hidden');
        var n = panel.querySelector('input[name=name]'); if (n && !panel.classList.contains('hidden')) n.focus();
    });
    document.getElementById('addGuestCancel').addEventListener('click', function () { panel.classList.add('hidden'); });
})();
</script>
@endif

<div class="grid grid-cols-1 {{ $isAdmin ? 'lg:grid-cols-2' : '' }} gap-5 items-start">
    <div>
        <div class="mb-3">
            <h2 class="text-xl font-semibold">Invitation list</h2>
            @if ($isAdmin)<p class="text-sm text-gray-500">Everyone on the pledge list is here automatically. Payment is not required.</p>@endif
        </div>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                    <th class="px-4 py-3">Name</th>
                    @if ($cards)<th class="px-4 py-3">Invitation link</th>@else<th class="px-4 py-3">Phone</th>@endif<th class="px-4 py-3"></th>
                </tr></thead>
                <tbody>
                @forelse ($pledges as $p)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $p->name }}@if ($optOuts->inSet($optSet, $p->phone))<div class="mt-1"><span class="badge" style="background:#fde8e8;color:#b42318;" title="This person asked not to receive messages. Fanikisha will not send to this number."><i class="fa-solid fa-ban text-[9px]"></i> Opted out</span></div>@endif</td>
                        <td class="px-4 py-3">
                            @if (!$cards)
                                <span class="text-xs text-gray-500">{{ $p->phone ?: 'No phone number' }}</span>
                            @elseif (!$p->invite_token && $p->invite_revoked_at)
                                <span class="badge" style="background:#fde8e8;color:#b42318;"><i class="fa-solid fa-link-slash text-[9px]"></i> Deactivated</span>
                            @elseif (!$p->invite_token)
                                <span class="text-gray-400 text-xs">Not generated yet</span>
                            @else
                                <span class="badge badge-admin"><i class="fa-solid fa-circle-check text-[9px]"></i> Active</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ Str::limit($p->inviteLink(), 28) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($cards && !$p->invite_token && $isAdmin && $p->invite_revoked_at)
                                <form method="POST" action="{{ route('delivery.reissue', $p) }}" class="inline">@csrf
                                    <button class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-solid fa-rotate-right"></i> Reactivate</button>
                                </form>
                            @elseif ($cards && !$p->invite_token && $isAdmin)
                                <form method="POST" action="{{ route('guests.send-invite', $p) }}" class="inline">@csrf
                                    <button class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-solid fa-paper-plane"></i> Send invite</button>
                                </form>
                            @elseif (($p->invite_token || !$cards) && $optOuts->inSet($optSet, $p->phone))
                                <span class="text-xs text-gray-400">Will not be messaged</span>
                            @elseif (($p->invite_token || !$cards) && $p->phone)
                                <form method="POST" action="{{ route('guests.sms', $p) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                                </form>
                                <a href="{{ route('guests.whatsapp', $p) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                            @elseif ($p->invite_token && $cards)
                                <span class="text-xs text-gray-400">No phone number</span>
                            @endif
                            @if ($cards && $isAdmin && $p->invite_token)
                                <form method="POST" action="{{ route('delivery.revoke', $p) }}" class="inline" data-confirm="Deactivate {{ $p->name }}'s invitation link? It will stop working straight away. You can reactivate it later with a new link." data-confirm-title="Deactivate link?" data-confirm-button="Deactivate">@csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5" title="Deactivate link"><i class="fa-solid fa-link-slash"></i></button>
                                </form>
                            @endif
                            @if ($isAdmin && $p->guest_only)
                                <button type="button" onclick="document.getElementById('editGuest{{ $p->id }}').classList.remove('hidden')" class="btn btn-ghost !py-1.5 !px-2.5" title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <form method="POST" action="{{ route('guests.destroy', $p) }}" class="inline" data-confirm="Remove {{ $p->name }} from the invitation list?">@csrf @method('DELETE')
                                    <button class="btn btn-ghost !py-1.5 !px-2.5" title="Remove"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">{{ $isAdmin ? 'Nobody to invite yet. Add a pledge or add a guest.' : 'No invitations are ready yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($isAdmin)
    <div>
        <div class="mb-3"><h2 class="text-xl font-semibold">Invitation message</h2></div>
        <div class="card p-5">
            <form method="POST" action="{{ route('guests.message.invitation') }}">
                @csrf @method('PATCH')
                <textarea name="invitation_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('invitation', false) }}</textarea>
                <p class="text-[11px] text-gray-400 mt-1">You can use {name} {event_name} {event_type} {date} {place} {link}</p>
                <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
            </form>
        </div>
    </div>
    @endif
</div>
{{-- Edit an invited guest (pledgers are edited on the pledges page) --}}
@if ($isAdmin)
@foreach ($pledges->where('guest_only', true) as $p)
<div id="editGuest{{ $p->id }}" class="hidden fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="font-semibold mb-4">Edit guest</h3>
        <form method="POST" action="{{ route('guests.update', $p) }}" class="space-y-3">
            @csrf @method('PATCH')
            <div><label class="text-xs font-semibold">Name</label><input type="text" name="name" value="{{ $p->name }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Phone</label><input type="tel" name="phone" value="{{ $p->phone }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Card type</label>
                <select name="card_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="single" @selected(($p->card_type ?: 'single') === 'single')>Single card (one person)</option>
                    <option value="double" @selected($p->card_type === 'double')>Double card (couple, two people)</option>
                </select></div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editGuest{{ $p->id }}').classList.add('hidden')" class="btn btn-ghost flex-1 justify-center">Cancel</button>
                <button class="btn btn-primary flex-1 justify-center">Save changes</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endif

@endsection
