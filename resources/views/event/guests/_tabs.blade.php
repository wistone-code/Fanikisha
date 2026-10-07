@php
    $tabEvent = app('currentEvent');
    $tabAdmin = auth()->user()?->isAdminOn($tabEvent);
    $tabs = [];
    if ($tabEvent->isEcard()) {
        $tabs[] = ['guests', 'E-cards', route('guests.index')];
    } else {
        $tabs[] = ['event', 'Event invitation', route('guests.index')];
        $tabs[] = ['meeting', 'Meeting invitation', route('guests.index', ['tab' => 'meeting'])];
    }
    if ($tabEvent->hasFeature('cards')) {
        $tabs[] = ['delivery', 'Delivery', route('delivery.index')];
        $tabs[] = ['rsvp', 'RSVP', route('guests.index', ['tab' => 'rsvp'])];
        if ($tabAdmin) {
            $tabs[] = ['seating', 'Seating', route('seating.index')];
            $tabs[] = ['checkin', 'Check-in', route('checkin.index')];
            $tabs[] = ['photos', 'Photos', route('photos.index')];
            $tabs[] = ['design', 'Card design', route('design.index')];
            $tabs[] = ['after', 'After event', route('after.index')];
        }
    }
@endphp
@if (auth()->user()->roleOn($tabEvent) !== 'scanner')
<div class="flex gap-6 border-b mb-5 text-sm font-semibold overflow-x-auto whitespace-nowrap">
    @foreach ($tabs as [$id, $label, $url])
        @if ($id === $active)
        <span class="pb-3 border-b-2" style="border-color:var(--primary);color:var(--primary);">{{ $label }}</span>
        @else
        <a href="{{ $url }}" class="pb-3 border-b-2 border-transparent text-gray-400">{{ $label }}</a>
        @endif
    @endforeach
</div>
@endif
