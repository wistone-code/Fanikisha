@extends('layouts.guest')
@section('title', 'Set a new password — '.config('app.name'))

@section('content')
<div class="text-center mb-2"><i class="fa-solid fa-key text-2xl" style="color:#1F3A52;"></i></div>
<h3 class="text-center font-semibold mb-1">Set a new password</h3>
<p class="text-sm text-gray-500 text-center mb-4">For security, choose a new password before continuing.</p>

@error('password')
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4">{{ $message }}</div>
@enderror

<form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
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
    <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save password &amp; continue</button>
</form>
@endsection
