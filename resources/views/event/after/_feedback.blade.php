<div class="card p-5 max-w-2xl mt-4 text-sm">
    <div class="font-semibold mb-1"><i class="fa-solid fa-star text-amber-500"></i> Guest feedback</div>
    @if (! $feedback['count'])
        <p class="text-gray-500">No ratings yet.@if (! $event->feedback_enabled) Switch on <b>Ask guests who came to rate the event</b> above and guests get the link in their thank-you SMS.@endif</p>
    @else
        <p class="mb-2"><span class="text-2xl font-semibold">{{ number_format($feedback['average'], 1) }}</span> <span class="text-gray-500">/ 5 from {{ $feedback['count'] }} guest(s)</span></p>
        <div class="space-y-1 mb-3">
            @foreach ($feedback['spread'] as $stars => $n)
            <div class="flex items-center gap-2 text-xs"><span class="w-10">{{ $stars }} ★</span><div class="flex-1 bg-gray-100 rounded h-2"><div class="bg-amber-400 h-2 rounded" style="width: {{ $feedback['count'] ? round($n / $feedback['count'] * 100) : 0 }}%"></div></div><span class="w-6 text-right text-gray-500">{{ $n }}</span></div>
            @endforeach
        </div>
        @if ($feedback['comments']->isNotEmpty())
        <div class="font-semibold text-xs uppercase text-gray-400 mb-1">Messages</div>
        <ul class="space-y-2">
            @foreach ($feedback['comments'] as $c)
            <li class="border-l-2 border-amber-300 pl-3"><span class="text-amber-500">{{ str_repeat('★', $c->feedback_rating) }}</span> {{ $c->feedback_message }} <span class="text-xs text-gray-400">— {{ $c->name }}</span></li>
            @endforeach
        </ul>
        @endif
    @endif
</div>
