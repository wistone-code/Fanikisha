@extends('layouts.app')
@section('title', 'Event Photos — '.config('app.name'))

@section('content')
<div class="flex justify-between items-start mb-4 flex-wrap gap-3">
    <div><h1 class="text-2xl font-semibold">Event Photos</h1><p class="text-sm text-gray-500">A shared photo wall: guests add their photos from their phones; you can hide anything you don't want shown.</p></div>
    @if ($photos->count())<a href="{{ route('photos.download') }}" class="btn btn-ghost"><i class="fa-solid fa-download"></i> Download all (ZIP)</a>@endif
</div>

<div class="grid lg:grid-cols-3 gap-5 items-start">
    <div class="card p-5">
        <form method="POST" action="{{ route('photos.update') }}" class="space-y-3 text-sm">
            @csrf @method('PATCH')
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" name="photo_wall_enabled" value="1" @checked($event->photo_wall_enabled)> Turn the photo wall on</label>
            <div><label class="text-xs font-semibold">Who can open it</label>
                <select name="photo_wall_access" class="w-full border rounded-lg px-3 py-2"><option value="link" @selected($event->photo_wall_access === 'link')>Anyone with the link</option><option value="guests" @selected($event->photo_wall_access === 'guests')>Only guests (from their card)</option></select></div>
            <div><label class="text-xs font-semibold">PIN <span class="text-gray-400 font-normal">(optional)</span></label><input type="text" name="photo_wall_pin" value="{{ $event->photo_wall_pin }}" maxlength="12" class="w-full border rounded-lg px-3 py-2"></div>
            <div><label class="text-xs font-semibold">Uploads open</label>
                <select name="photo_wall_open_mode" class="w-full border rounded-lg px-3 py-2"><option value="event_day" @selected($event->photo_wall_open_mode === 'event_day')>From the event day</option><option value="always" @selected($event->photo_wall_open_mode === 'always')>Right away</option></select></div>
            <div class="grid grid-cols-3 gap-2">
                <div><label class="text-xs font-semibold">Close after (days)</label><input type="number" name="photo_wall_close_days" min="0" max="90" value="{{ $event->photo_wall_close_days }}" class="w-full border rounded-lg px-2 py-2"></div>
                <div><label class="text-xs font-semibold">Per guest</label><input type="number" name="photo_wall_max_per_guest" min="1" max="50" value="{{ $event->photo_wall_max_per_guest }}" class="w-full border rounded-lg px-2 py-2"></div>
                <div><label class="text-xs font-semibold">Wall total</label><input type="number" name="photo_wall_max_total" min="10" max="1000" value="{{ $event->photo_wall_max_total }}" class="w-full border rounded-lg px-2 py-2"></div>
            </div>
            <label class="flex items-center gap-2"><input type="checkbox" name="photo_wall_uploads_blocked" value="1" @checked($event->photo_wall_uploads_blocked)> Pause new uploads</label>
            <button class="btn btn-primary w-full justify-center">Save</button>
        </form>
        @if ($wallUrl)
        <div class="mt-4 border-t pt-4 text-xs">
            <div class="font-semibold mb-1">Wall link</div>
            <input type="text" readonly value="{{ $wallUrl }}" onclick="this.select()" class="w-full border rounded-lg px-2 py-2 text-xs">
            <p class="text-gray-400 mt-1">It also appears as a button on every guest's card.</p>
            <form method="POST" action="{{ route('photos.new-link') }}" class="mt-2" data-confirm="Make a new link? The old link stops working. Guests can still reach the wall from their cards." data-confirm-title="New link?" data-confirm-button="Make new link">@csrf <button class="btn btn-ghost !py-1 !px-2 text-xs">Make a new link</button></form>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2">
        <div class="text-sm text-gray-500 mb-2">{{ $photos->count() }} photo(s) · {{ round($photos->sum('size') / 1048576, 1) }} MB</div>
        <div class="grid grid-cols-3 md:grid-cols-4 gap-2">
        @forelse ($photos as $p)
            <div class="card overflow-hidden {{ $p->hidden ? 'opacity-50' : '' }}">
                <img src="{{ route('photos.thumb', $p) }}" loading="lazy" class="w-full aspect-square object-cover" alt="">
                <div class="p-2 text-[11px]">
                    <div class="truncate text-gray-600">{{ $p->uploader_name ?? 'Guest' }}@if ($p->reports) · <span class="text-red-600">{{ $p->reports }} report(s)</span>@endif</div>
                    <div class="flex gap-1 mt-1">
                        <form method="POST" action="{{ route('photos.toggle-hidden', $p) }}">@csrf <button class="btn btn-ghost !py-0.5 !px-1.5 text-[11px]">{{ $p->hidden ? 'Show' : 'Hide' }}</button></form>
                        <form method="POST" action="{{ route('photos.destroy', $p) }}" data-confirm="Delete this photo for good?" data-confirm-title="Delete photo?">@csrf @method('DELETE') <button class="btn btn-danger !py-0.5 !px-1.5 text-[11px]"><i class="fa-solid fa-trash"></i></button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full card p-8 text-center text-gray-400 text-sm">No photos yet.</div>
        @endforelse
        </div>
    </div>
</div>
@endsection
