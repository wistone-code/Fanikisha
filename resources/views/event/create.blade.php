@extends('layouts.app')
@section('title', 'Create your event — '.config('app.name'))

@section('content')
<div class="card max-w-md mx-auto mt-10 text-center p-10">
    <i class="fa-solid fa-champagne-glasses text-3xl mb-4" style="color:var(--primary);"></i>
    <h3 class="text-xl font-semibold mb-2">Create your first event</h3>
    <p class="text-sm text-gray-500 mb-6">You're not assigned to any event yet. Create one below — you'll automatically become its admin. Your account is limited to a single event.</p>

    <form method="POST" action="{{ route('event.store') }}" class="text-left space-y-3">
        @csrf
        @if ($package === 'full')
        <div>
            <label class="text-xs font-semibold">What do you need?</label>
            <div class="space-y-2 mt-1">
                <label class="flex items-start gap-2 border rounded-lg px-3 py-2 text-sm cursor-pointer">
                    <input type="radio" name="mode" value="contributions" class="mt-1" @checked(old('mode', 'contributions') === 'contributions')>
                    <span><span class="font-semibold">Full event management</span><br><span class="text-xs text-gray-500">Pledges, payments, providers, guests, e-cards and check-in.</span></span>
                </label>
                <label class="flex items-start gap-2 border rounded-lg px-3 py-2 text-sm cursor-pointer">
                    <input type="radio" name="mode" value="ecard" class="mt-1" @checked(old('mode') === 'ecard')>
                    <span><span class="font-semibold">E-cards only</span><br><span class="text-xs text-gray-500">Guest list, e-cards by SMS/WhatsApp, RSVP and check-in. No money handling.</span></span>
                </label>
            </div>
        </div>
        @else
        <input type="hidden" name="mode" value="{{ $package === 'ecard' ? 'ecard' : 'contributions' }}">
        <p class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2"><i class="fa-solid fa-box"></i> {{ config('packages.packages.'.$package.'.label') }} — {{ config('packages.packages.'.$package.'.description') }}</p>
        @endif
        <div><label class="text-xs font-semibold">Event name</label><input type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Juju Gala"></div>
        <div>
            <label class="text-xs font-semibold">Event type</label>
            <select name="event_type" required class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">— Select type —</option>
                @foreach ($types as $type)
                <option value="{{ $type }}" data-ecard="{{ in_array($type, $ecardTypes, true) ? 1 : 0 }}" @selected(old('event_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="text-xs font-semibold">Place</label><input type="text" name="place" value="{{ old('place') }}" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="e.g. Morogoro"></div>
        <div><label class="text-xs font-semibold">Event date</label><input type="date" name="event_date" value="{{ old('event_date') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        <div id="deadlineField"><label class="text-xs font-semibold">Pledge deadline</label><input type="date" name="pledge_deadline" value="{{ old('pledge_deadline') }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
        @error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('event_type')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        @error('pledge_deadline')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full justify-center mt-2"><i class="fa-solid fa-check"></i> Create event</button>
    </form>
</div>

<script>
// E-card-only events have no pledges, so hide the deadline (and Funeral, which uses announcements instead of cards).
(function () {
    const radios = document.querySelectorAll('input[name="mode"]');
    const modeValue = function () { const c = document.querySelector('input[name="mode"]:checked'); return c ? c.value : document.querySelector('input[name="mode"]').value; };
    const deadline = document.getElementById('deadlineField');
    const deadlineInput = deadline.querySelector('input');
    const typeSelect = document.querySelector('select[name="event_type"]');

    function apply() {
        const ecard = modeValue() === 'ecard';
        deadline.classList.toggle('hidden', ecard);
        deadlineInput.required = !ecard;
        Array.from(typeSelect.options).forEach(function (opt) {
            if (opt.value === '') return;
            const blocked = ecard && opt.dataset.ecard === '0';
            opt.hidden = blocked;
            opt.disabled = blocked;
            if (blocked && opt.selected) typeSelect.value = '';
        });
    }

    radios.forEach(function (r) { r.addEventListener('change', apply); });
    apply();
})();
</script>
@endsection
