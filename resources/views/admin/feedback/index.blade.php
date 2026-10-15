@extends('layouts.app')
@section('title', 'Feedback — '.config('app.name'))

@section('content')
<div class="mb-4">
    <h2 class="text-xl font-semibold">Feedback</h2>
    <p class="text-sm text-gray-500">From hosts inside the app and visitors on the website. Replying to a host shows a banner on their event.</p>
</div>
@if (session('status'))<div class="rounded-xl bg-green-50 text-green-800 p-3 text-sm mb-4">{{ session('status') }}</div>@endif
@if (session('error'))<div class="rounded-xl bg-red-50 text-red-700 p-3 text-sm mb-4">{{ session('error') }}</div>@endif
<div class="flex gap-2 mb-4 text-xs font-semibold">
    @foreach ([null => 'All', 'new' => 'New ('.$newCount.')', 'seen' => 'Seen', 'done' => 'Done'] as $k => $l)
        <a href="{{ route('admin.feedback', $k ? ['status' => $k] : []) }}" class="px-3 py-1.5 rounded-full {{ $filter === $k ? 'bg-[var(--primary)] text-white' : 'bg-gray-100 text-gray-600' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="space-y-3">
@forelse ($items as $i)
    <div class="card p-4">
        <div class="flex flex-wrap items-center gap-2 text-xs mb-2">
            <span class="px-2 py-0.5 rounded-full bg-gray-100 font-semibold">{{ $i->source === 'host' ? 'Host' : 'Website' }}</span>
            <span class="px-2 py-0.5 rounded-full bg-gray-100">{{ \App\Models\FeedbackItem::TYPES[$i->type] ?? $i->type }}</span>
            @if ($i->rating)<span>{{ str_repeat('★', $i->rating) }}{{ str_repeat('☆', 5 - $i->rating) }}</span>@endif
            <span class="px-2 py-0.5 rounded-full {{ $i->status === 'new' ? 'bg-red-50 text-red-700' : 'bg-gray-100' }}">{{ $i->status }}</span>
            <span class="text-gray-500 ml-auto">{{ $i->created_at->format('j M Y H:i') }}</span>
        </div>
        <p class="text-sm whitespace-pre-line">{{ $i->message }}</p>
        <p class="text-xs text-gray-500 mt-2">
            {{ $i->name ?: 'Anonymous' }}@if ($i->contact) · {{ $i->contact }}@endif
            @if ($i->event) · Event: {{ $i->event->name }}@endif
            @if ($i->page) · Page: /{{ ltrim($i->page, '/') }}@endif
        </p>
        <div class="flex flex-wrap gap-2 mt-3 items-start">
            @foreach (['seen', 'done'] as $s)
                @if ($i->status !== $s)
                <form method="POST" action="{{ route('admin.feedback.update', $i) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $s }}"><button class="px-3 py-1 rounded-lg border text-xs">Mark {{ $s }}</button></form>
                @endif
            @endforeach
            @if ($i->source === 'host' && $i->event_id)
            <form method="POST" action="{{ route('admin.feedback.reply', $i) }}" class="flex gap-2 flex-1 min-w-[16rem]">@csrf
                <input name="body" maxlength="300" required placeholder="{{ $i->replied_at ? 'Replied '.$i->replied_at->format('j M').'. Reply again' : 'Reply as a banner on their event' }}" class="flex-1 border rounded-lg px-3 py-1 text-sm">
                <button class="px-3 py-1 rounded-lg bg-[var(--primary)] text-white text-xs font-semibold">Reply</button>
            </form>
            @endif
        </div>
    </div>
@empty
    <div class="card p-6 text-sm text-gray-500 text-center">No feedback yet.</div>
@endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
