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
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start mb-5">
    <div class="card p-5">
        <h2 class="text-lg font-semibold">Add new guest <span class="text-xs font-normal text-gray-400">· Mgeni mpya</span></h2>
        <p class="text-sm text-gray-500 mb-3">For family, VIPs or anyone who is not a contributor. Their link is ready at once and they never appear in pledges or finance.</p>
        <form method="POST" action="{{ route('guests.invite.new') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Name · Jina" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input name="phone" value="{{ old('phone') }}" maxlength="32" inputmode="tel" placeholder="Phone · Simu (0712 345 678)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <select name="card_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="single">Single card · Kadi moja</option>
                <option value="double">Double card · Kadi ya wawili</option>
            </select>
            <button class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add guest</button>
        </form>
    </div>

    <div class="card p-5">
        <h2 class="text-lg font-semibold">Choose from pledge list <span class="text-xs font-normal text-gray-400">· Chagua kutoka orodha ya ahadi</span></h2>
        @if ($pickable->isEmpty())
            <p class="text-sm text-gray-400 mt-2">Everyone on the pledge list is already on the invitation list.</p>
        @else
        <form method="POST" action="{{ route('guests.invite.select') }}" id="pickForm" class="mt-3">
            @csrf
            <input type="search" id="pickSearch" placeholder="Search name · Tafuta jina" class="w-full border rounded-lg px-3 py-2 text-sm mb-2">
            <label class="flex items-center gap-2 text-xs text-gray-500 mb-1"><input type="checkbox" id="pickAll"> Select all shown · Chagua wote</label>
            <div class="border rounded-lg divide-y overflow-y-auto" style="max-height:15rem;">
                @foreach ($pickable as $c)
                <label class="pick-row flex items-center gap-3 px-3 py-2 text-sm" data-name="{{ mb_strtolower($c->name) }}">
                    <input type="checkbox" name="ids[]" value="{{ $c->id }}">
                    <span class="flex-1">{{ $c->name }}</span>
                </label>
                @endforeach
            </div>
            <button class="btn btn-primary mt-3"><i class="fa-solid fa-list-check"></i> Add selected</button>
        </form>
        <script>
        (function () {
            var rows = document.querySelectorAll('#pickForm .pick-row');
            var q = document.getElementById('pickSearch');
            function shown() { return Array.prototype.filter.call(rows, function (r) { return r.style.display !== 'none'; }); }
            q.addEventListener('input', function () {
                var t = q.value.trim().toLowerCase();
                rows.forEach(function (r) { r.style.display = r.getAttribute('data-name').indexOf(t) === -1 ? 'none' : ''; });
            });
            document.getElementById('pickAll').addEventListener('change', function (e) {
                shown().forEach(function (r) { r.querySelector('input').checked = e.target.checked; });
            });
        })();
        </script>
        @endif
    </div>
</div>
@endif

<div class="grid grid-cols-1 {{ $isAdmin ? 'lg:grid-cols-2' : '' }} gap-5 items-start">
    <div>
        <div class="mb-3">
            <h2 class="text-xl font-semibold">Invitation list</h2>
            @if ($isAdmin)<p class="text-sm text-gray-500">Send an invitation to anyone on the list — payment is not required.</p>@endif
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
                            @elseif ($isAdmin && !$p->invite_sent_at)
                                <form method="POST" action="{{ route('guests.invite.unlist', $p) }}" class="inline">@csrf @method('DELETE')
                                    <button class="btn btn-ghost !py-1.5 !px-2.5" title="Take off the list"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">{{ $isAdmin ? 'Nobody on the invitation list yet. Add a new guest or choose from the pledge list.' : 'No invitations are ready yet.' }}</td></tr>
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
