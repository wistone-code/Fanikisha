@extends('layouts.guest')
@section('title', 'Verify your identity — '.config('app.name'))

@section('content')
@include('auth.forgot-password._steps', ['step' => 2])

<p class="text-sm text-gray-500 mb-3">We've sent a 6-digit verification code to <strong>{{ $user->email }}</strong>.</p>

<div class="bg-gray-50 rounded-lg p-4 mb-4">
    <div class="text-xs text-gray-500 leading-relaxed">This prototype has no real email/SMS server, so your code is shown here directly instead of being delivered:</div>
    <div class="text-center font-mono text-2xl font-bold tracking-[6px] mt-2">{{ $demoCode }}</div>
</div>

@error('code')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.forgot.verify.submit') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-xs font-semibold">Verification code</label>
        <input type="text" name="code" maxlength="6" inputmode="numeric" placeholder="000000" required
               class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1 text-center tracking-widest font-mono">
    </div>
    <div class="flex gap-2">
        <a href="{{ route('password.forgot.identify') }}" class="btn btn-ghost">Back</a>
        <button class="btn btn-primary">Verify code</button>
    </div>
</form>
@endsection
