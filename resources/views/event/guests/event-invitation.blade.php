@extends('layouts.app')
@section('title', 'Guest Management — '.config('app.name'))

@section('content')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold">
    <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">Event invitation</span>
    <a href="{{ route('guests.index', ['tab' => 'meeting']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">Meeting invitation</a>
    <a href="{{ route('guests.index', ['tab' => 'rsvp']) }}" class="pb-3 border-b-2 border-transparent text-gray-400">RSVP</a>
    @if ($isAdmin)<a href="{{ route('checkin.index') }}" class="pb-3 border-b-2 border-transparent text-gray-400">Check-in</a>@endif
</div>

<div class="grid grid-cols-1 {{ $isAdmin ? 'lg:grid-cols-2' : '' }} gap-5 items-start">
    <div>
        <div class="mb-3">
            <h2 class="text-xl font-semibold">Event invitation</h2>
            @if ($isAdmin)<p class="text-sm text-gray-500">Links activate once a pledge is paid in full.</p>@endif
        </div>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400 border-b">
                    <th class="px-4 py-3">Name</th>
                    @if ($isAdmin)<th class="px-4 py-3">Status</th>@endif
                    <th class="px-4 py-3">Invitation link</th><th class="px-4 py-3"></th>
                </tr></thead>
                <tbody>
                @forelse ($pledges as $p)
                    <tr class="border-b last:border-0">
                        <td class="px-4 py-3 font-semibold">{{ $p->name }}</td>
                        @if ($isAdmin)
                        <td class="px-4 py-3"><span class="badge {{ $p->isPaidInFull() ? 'badge-admin' : 'badge-viewer' }}">{{ $p->isPaidInFull() ? 'Paid in full' : 'Balance due' }}</span></td>
                        @endif
                        <td class="px-4 py-3">
                            @if (!$p->isPaidInFull())
                                <span class="badge badge-viewer"><i class="fa-solid fa-lock text-[9px]"></i> Locked</span>
                            @elseif (!$p->invite_token)
                                <span class="text-gray-400 text-xs">Not generated yet</span>
                            @else
                                <span class="badge badge-admin"><i class="fa-solid fa-circle-check text-[9px]"></i> Active</span>
                                <span class="text-[11px] text-gray-400 font-mono">{{ Str::limit($p->inviteLink(), 28) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($p->isPaidInFull() && !$p->invite_token && $isAdmin)
                                <form method="POST" action="{{ route('guests.send-invite', $p) }}">@csrf
                                    <button class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-solid fa-paper-plane"></i> Send invite</button>
                                </form>
                            @elseif ($p->invite_token)
                                <form method="POST" action="{{ route('guests.sms', $p) }}" class="inline">
                                    @csrf
                                    <button class="btn btn-ghost !py-1.5 !px-2.5"><i class="fa-solid fa-comment-sms"></i> SMS</button>
                                </form>
                                <a href="{{ route('guests.whatsapp', $p) }}" class="btn btn-primary !py-1.5 !px-2.5"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">{{ $isAdmin ? 'No pledges yet.' : 'No fully paid pledges yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($isAdmin)
    <div>
        <div class="mb-3"><h2 class="text-xl font-semibold">Invitation message</h2><p class="text-sm text-gray-500">Use <code>{name}</code>, <code>{place}</code>, <code>{link}</code></p></div>
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
