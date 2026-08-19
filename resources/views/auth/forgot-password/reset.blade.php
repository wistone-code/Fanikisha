@extends('layouts.guest')
@section('title', 'Choose a new password — '.config('app.name'))

@section('content')
@include('auth.forgot-password._steps', ['step' => 3])

<p class="text-sm text-gray-500 mb-4">Identity verified. Choose a new password for your account.</p>

@error('password')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.forgot.reset.submit') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-xs font-semibold">New password</label>
        <input type="password" name="password" required placeholder="At least 8 characters, with upper&lowercase letters and a number"
               class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1">
    </div>
    <div>
        <label class="text-xs font-semibold">Confirm new password</label>
        <input type="password" name="password_confirmation" required class="w-full border rounded-lg px-3 py-2.5 text-sm mt-1">
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Reset password</button>
</form>
@endsection
