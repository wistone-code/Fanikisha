@extends('layouts.app')
@section('title', 'Send feedback — '.config('app.name'))

@section('content')
<div class="max-w-xl mx-auto">
    <h2 class="text-xl font-semibold mb-1">Send feedback</h2>
    <p class="text-sm text-gray-500 mb-4">Tell the Fanikisha team about a problem, an idea, a question or praise. We read every message and may answer with a banner on your event.</p>
    @if (session('status'))<div class="rounded-xl bg-green-50 text-green-800 p-3 text-sm mb-4">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('feedback.host.store') }}" class="card p-4 space-y-4">
        @csrf
        <input type="hidden" name="page" value="{{ $from }}">
        @include('feedback._fields', ['sw' => false, 'withRating' => false])
        <button class="btn-primary px-4 py-2 rounded-lg font-semibold">Send feedback</button>
    </form>
</div>
@endsection
