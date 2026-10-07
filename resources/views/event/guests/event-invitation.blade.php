@extends('layouts.app')
@section('title', 'Guest Management — '.config('app.name'))

@section('content')
@php($optOuts = app(\App\Services\OptOutService::class))
@php($optSet = $optOuts->suppressedAmong($pledges->pluck('phone')->all()))
@include('event.guests._tabs', ['active' => 'event'])

@if ($errors->any())
<div class="mb-4 rounded-lg px-4 py-3 text-sm" style="background:#fde8e8;color:#b42318;">{{ $errors->first() }}</div>
@endif

@if ($isAdmin)
<div class="mb-5">
    <button type="button" id="addGuestBtn" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add guest <span class="opacity-70 font-normal">· Ongeza mgeni</span></button>
    <div id="addGuestPanel" class="card p-5 mt-3 max-w-md {{ $errors->has('name') || $errors->has('phone') ? '' : 'hidden' }}">
        <p class="text-sm text-gray-500 mb-3">For family, VIPs or anyone who is not a contributor. Their link is ready at once and they never appear in pledges or finance.</p>
        <form method="POST" action="{{ route('guests.invite.new') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Name · Jina" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input name="phone" value="{{ old('phone') }}" maxlength="32" inputmode="tel" placeholder="Phone · Simu (0712 345 678)" class="w-full border rounded-lg px-3 py-2 text-sm">
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
                    <th class="px-4 py-3">Invitation link</th><th class="px-4 py-3"></th>
                </tr></thead>
                <tbody>
                @forelse ($pledges as $p)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $p->name }}@if ($optOuts->inSet($optSet, $p->phone))<div class="mt-1"><span class="badge" style="background:#fde8e8;color:#b42318;" title="This person asked not to receive messages. Fanikisha will not send to this number."><i class="fa-solid fa-ban text-[9px]"></i> Opted out</span></div>@endif</td>
                        <td class="px-4 py-3">
                            @if (!$p->invite_token)
                                <span class="text-gray-400 text-xs">Not generated yet</span>
                            @else
                                <span class="badge badge-admin"><i class="fa-solid fa-circle-check text-[9px]"></i> Active</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ Str::limit($p->inviteLink(), 28) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if (!$p->invite_token && $isAdmin)
                                <form method="POST" action="{{ route('guests.send-invite', $p) }}" class="inline">@csrf
                                    <button class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-solid fa-paper-plane"></i> Send invite</button>
                                </form>
                            @elseif ($p->invite_token && $optOuts->inSet($optSet, $p->phone))
                                <span class="text-xs text-gray-400">Will not be messaged</span>
                            @elseif ($p->invite_token && $p->phone)
                                <form method="POST" action="{{ route('guests.sms', $p) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                                </form>
                                <a href="{{ route('guests.whatsapp', $p) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                            @elseif ($p->invite_token)
                                <span class="text-xs text-gray-400">No phone number</span>
                            @endif
                            @if ($isAdmin && $p->guest_only)
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
                <textarea name="invitation_message" rows="6" class="w-full border rounded-lg px-3 py-2 text-sm">{{ $event->messageOrDefault('invitation') }}</textarea>
                <button class="btn btn-primary mt-3"><i class="fa-solid fa-check"></i> Save message</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
