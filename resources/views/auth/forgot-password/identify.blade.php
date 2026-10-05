@extends('layouts.guest')
@section('title', 'Reset your password — '.config('app.name'))

@section('content')
@include('auth.forgot-password._steps', ['step' => 1])

<p class="text-sm text-gray-500 mb-4">Enter your username and the email on your account. Choose how you'd like to receive your reset code.</p>

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
    <div>
        <label class="text-xs font-semibold">Send my code by</label>
        <div class="grid grid-cols-2 gap-2 mt-1">
            <label class="border rounded-lg px-3 py-2.5 text-sm flex items-center gap-2 cursor-pointer">
                <input type="radio" name="channel" value="email" {{ old('channel', 'email') === 'email' ? 'checked' : '' }}> <i class="fa-solid fa-envelope text-gray-400"></i> Email
            </label>
            <label class="border rounded-lg px-3 py-2.5 text-sm flex items-center gap-2 cursor-pointer">
                <input type="radio" name="channel" value="sms" {{ old('channel') === 'sms' ? 'checked' : '' }}> <i class="fa-solid fa-comment-sms text-gray-400"></i> SMS
            </label>
        </div>
    </div>
    <button class="btn btn-primary mt-2">Continue</button>
</form>

<div class="text-center mt-4">
    <a href="{{ route('login') }}" class="text-sm text-gray-500">Back to sign in</a>
</div>
@endsection
