@extends('layouts.app')
@section('title', 'Event Setting — '.config('app.name'))

@section('content')
<div class="mb-4"><h2 class="text-xl font-semibold">Event Setting</h2><p class="text-sm text-gray-500">Only admins can update event details.</p></div>

<div class="card p-6 max-w-md">
    <form method="POST" action="{{ route('event.settings.update') }}" class="space-y-3">
        @csrf @method('PATCH')
        <div><label class="text-xs font-semibold">Event name</label><input type="text" name="name" value="{{ $event->name }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div>
            <label class="text-xs font-semibold">Event type</label>
            <select name="event_type" class="w-full border rounded-lg px-3 py-2 text-sm">
                @foreach ($types as $type)
                <option value="{{ $type }}" @selected($event->event_type === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-xs font-semibold">Place</label><input type="text" name="place" value="{{ $event->place }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="text-xs font-semibold">Event date</label><input type="date" name="event_date" value="{{ $event->event_date->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="text-xs font-semibold">Pledge deadline</label><input type="date" name="pledge_deadline" value="{{ $event->pledge_deadline->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-2"><i class="fa-solid fa-check"></i> Save changes</button>
    </form>
</div>
@endsection
