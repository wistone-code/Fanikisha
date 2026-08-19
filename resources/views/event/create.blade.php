@extends('layouts.app')
@section('title', 'Create your event — '.config('app.name'))

@section('content')
<div class="card max-w-md mx-auto mt-10 text-center p-10">
    <i class="fa-solid fa-champagne-glasses text-3xl mb-4" style="color:var(--primary);"></i>
    <h3 class="text-xl font-semibold mb-2">Create your first event</h3>
    <p class="text-sm text-gray-500 mb-6">You're not assigned to any event yet. Create one below — you'll automatically become its admin. Your account is limited to a single event.</p>

    <form method="POST" action="{{ route('event.store') }}" class="text-left space-y-3">
        @csrf
        <div><label class="text-xs font-semibold">Event name</label><input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Juju Gala"></div>
        <div>
            <label class="text-xs font-semibold">Event type</label>
            <select name="event_type" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">— Select type —</option>
                @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('event_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-xs font-semibold">Place</label><input type="text" name="place" value="{{ old('place') }}" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Morogoro"></div>
        <div><label class="text-xs font-semibold">Event date</label><input type="date" name="event_date" value="{{ old('event_date') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div><label class="text-xs font-semibold">Pledge deadline</label><input type="date" name="pledge_deadline" value="{{ old('pledge_deadline') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full justify-center mt-2"><i class="fa-solid fa-check"></i> Create event</button>
    </form>
</div>
@endsection
