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
    <div class="flex gap-2 flex-wrap">
        <button type="button" id="addGuestBtn" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add guest</button>
        <button type="button" id="importGuestBtn" class="btn btn-ghost"><i class="fa-solid fa-file-import"></i> Import list</button>
    </div>
    <div id="addGuestPanel" class="card p-5 mt-3 max-w-md {{ $errors->has('name') || $errors->has('phone') || $errors->has('card_type') ? '' : 'hidden' }}">
        <p class="text-sm text-gray-500 mb-3">For family, VIPs or anyone who is not a contributor. Their link is ready at once and they never appear in pledges or finance.</p>
        <form method="POST" action="{{ route('guests.invite.new') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Name" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input name="phone" required value="{{ old('phone') }}" maxlength="32" inputmode="tel" placeholder="Phone (0712 345 678)" class="w-full border rounded-lg px-3 py-2 text-sm">
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
    <div id="importGuestPanel" class="card p-5 mt-3 max-w-md {{ $errors->has('import_file') ? '' : 'hidden' }}">
        <p class="text-sm text-gray-500 mb-3">Add many guests at once. Like guests added one by one, they join the invitation list only and never appear in pledges or finance.</p>
        @include('event.guests._import-guests', ['cancelId' => 'importGuestPanel'])
    </div>
</div>
<script>
(function () {
    var importPanel = document.getElementById('importGuestPanel');
    document.getElementById('importGuestBtn').addEventListener('click', function () {
        importPanel.classList.toggle('hidden');
        document.getElementById('addGuestPanel').classList.add('hidden');
        var t = document.getElementById('importGuestsText'); if (t && !importPanel.classList.contains('hidden')) t.focus();
    });
})();
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

<div class="space-y-8">
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
                            @elseif (($p->invite_token || !$cards) && $p->invitationAlreadySent())
                                <span class="badge badge-admin" title="Each guest gets the invitation once. To send again, reset the link (↺)."><i class="fa-solid fa-check text-[9px]"></i> {{ $p->invitationSentLabel() }}</span>
                            @if ($p->phone && $p->smsInvitationAllowed())
                            <form method="POST" action="{{ route('guests.sms', $p) }}" class="inline" data-confirm="Also send {{ $p->name }}'s card by normal SMS? Use this for a guest on a basic phone without WhatsApp. It can be sent once." data-confirm-title="Send by SMS?" data-confirm-button="Send SMS">@csrf
                                <button class="btn btn-ghost !py-1.5 !px-2.5" title="Also send by normal SMS (for a phone without WhatsApp)"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                            </form>
                            @endif
                            @elseif (($p->invite_token || !$cards) && $p->phone)
                                <form method="POST" action="{{ route('guests.sms', $p) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                                </form>
                                @if ($cards)
                                @if (app(\App\Services\WhatsAppCloudService::class)->isConfigured())
                                @if ($event->hasWhatsappCapacity())
                                <form method="POST" action="{{ route('guests.whatsapp-send', $p) }}" class="inline">@csrf
                                    <button class="btn btn-primary !py-1.5 !px-2.5" title="Send the invitation on WhatsApp"><i class="fa-brands fa-whatsapp"></i> WhatsApp</button>
                                </form>
                                @else
                                <button type="button" disabled class="btn btn-primary !py-1.5 !px-2.5 opacity-40 cursor-not-allowed" title="WhatsApp invitation quota used up — send by SMS"><i class="fa-brands fa-whatsapp"></i> WhatsApp</button>
                                @endif
                                @else
                                <a href="{{ route('guests.whatsapp', $p) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                                @endif
                                @endif
                            @elseif ($p->invite_token && $cards)
                                <span class="text-xs text-gray-400">No phone number</span>
                            @endif
                            @if ($cards && $isAdmin && $p->invite_token)
                                <form method="POST" action="{{ route('rsvp.reset', $p) }}" class="inline" data-confirm="Reset {{ $p->name }}'s invitation link? The old link stops working and any RSVP answer is cleared. You will need to send a new invite." data-confirm-title="Reset link?" data-confirm-button="Reset">@csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5" title="Reset link"><i class="fa-solid fa-rotate-left"></i></button>
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
    <div class="max-w-3xl">
        <div class="mb-3"><h2 class="text-xl font-semibold">Invitation message</h2></div>
        <div class="card p-5">
            <form method="POST" action="{{ route('guests.message.invitation') }}">
                @csrf @method('PATCH')
                <textarea name="invitation_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('invitation', false) }}</textarea>
                @php($sms = app(\App\Services\MessageTemplateService::class)->smsSample($event))
                <p class="text-xs {{ $sms['parts'] > 1 ? 'text-amber-700' : 'text-gray-500' }} mt-1">SMS sample (saved wording, guest "Amina Juma"): {{ $sms['length'] }} characters = <b>{{ $sms['parts'] }} SMS</b>@if ($sms['parts'] > 1). Shorten it to fit one SMS (160 characters)@endif.<br><span class="text-gray-400">{{ $sms['text'] }}</span></p>
                <p class="text-[11px] text-gray-400 mt-1">You can use {name} {event_name} {event_type} {date} {place} {link} {code} {wall_link}</p>
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
            <div><label class="text-xs font-semibold">Phone</label><input type="tel" name="phone" required value="{{ $p->phone }}" placeholder="0718 083 235" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
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
