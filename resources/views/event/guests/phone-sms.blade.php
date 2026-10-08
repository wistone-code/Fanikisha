@extends('layouts.app')
@section('title', 'Send by phone SMS — '.config('app.name'))

@section('content')
<div class="max-w-md mx-auto card p-6 text-center">
    <h1 class="text-lg font-semibold mb-2">Opening your Messages app…</h1>
    <p class="text-sm text-gray-500 mb-4">The card for {{ $pledge->name }} is ready in a new message. Press send there, then come back.</p>
    <a href="{{ $smsUrl }}" id="smsGo" class="btn btn-primary"><i class="fa-solid fa-mobile-screen"></i> Open Messages</a>
    <div class="mt-4"><a href="{{ route('guests.index') }}" class="text-sm text-gray-500"><i class="fa-solid fa-arrow-left"></i> Back to guests</a></div>
</div>
<script>setTimeout(function () { location.href = document.getElementById('smsGo').getAttribute('href'); }, 300);</script>
@endsection
