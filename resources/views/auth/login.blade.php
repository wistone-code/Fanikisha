@extends('layouts.guest')
@section('title', 'Sign in — '.config('app.name'))

@section('content')
@if (session('status'))
<div class="bg-green-50 text-green-700 text-sm rounded-lg px-3 py-2 mb-4">{{ session('status') }}</div>
@endif
@error('username')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-xs font-semibold">Username</label>
        <input type="text" name="username" value="{{ old('username') }}" autocomplete="username" required
               class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1" placeholder="Admin">
    </div>
    <div>
        <label class="text-xs font-semibold">Password</label>
        <input type="password" name="password" autocomplete="current-password" required
               class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1" placeholder="••••••••">
    </div>
    <label class="flex items-center gap-2 text-xs text-gray-500">
        <input type="checkbox" name="remember" value="1" class="rounded">
        Remember me on this device
    </label>
    <button class="btn btn-primary mt-2"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign in</button>
</form>

<div class="text-center mt-4">
    <a href="{{ route('password.forgot.identify') }}" class="text-sm font-semibold text-[#1F3A52]">Forgot password?</a>
</div>
@endsection
