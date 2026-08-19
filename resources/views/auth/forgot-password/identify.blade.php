@extends('layouts.guest')
@section('title', 'Reset your password — '.config('app.name'))

@section('content')
@include('auth.forgot-password._steps', ['step' => 1])

<p class="text-sm text-gray-500 mb-4">Enter your username and the email on file to verify it's you.</p>

@error('username')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.forgot.identify.submit') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-xs font-semibold">Username</label>
        <input type="text" name="username" value="{{ old('username') }}" required class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1">
    </div>
    <div>
        <label class="text-xs font-semibold">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1">
    </div>
    <button class="btn btn-primary mt-2">Continue</button>
</form>

<div class="text-center mt-4">
    <a href="{{ route('login') }}" class="text-sm text-gray-500">Back to sign in</a>
</div>
@endsection
