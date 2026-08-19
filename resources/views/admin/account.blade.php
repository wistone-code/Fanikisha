@extends('layouts.app')
@section('title', 'Account Settings — '.config('app.name'))

@section('content')
<div class="mb-4"><h2 class="text-xl font-semibold">Account Settings</h2><p class="text-sm text-gray-500">Manage your own System Admin login.</p></div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-3xl">
    <div class="card p-6">
        <h3 class="font-semibold mb-1">Email</h3>
        <p class="text-sm text-gray-500 mb-4">Used only for account recovery — no notifications are sent here.</p>
        <form method="POST" action="{{ route('admin.account.email') }}" class="space-y-3">
            @csrf @method('PATCH')
            <div><label class="text-xs font-semibold">Email</label><input type="email" name="email" value="{{ $account->email }}" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            @error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save email</button>
        </form>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold mb-1">Password</h3>
        <p class="text-sm text-gray-500 mb-4">Requires your current password to confirm it's you.</p>
        <form method="POST" action="{{ route('password.own.update') }}" class="space-y-3">
            @csrf @method('PATCH')
            <div><label class="text-xs font-semibold">Current password</label><input type="password" name="current_password" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">New password</label><input type="password" name="password" required placeholder="Upper&lowercase letters + a number" class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            <div><label class="text-xs font-semibold">Confirm new password</label><input type="password" name="password_confirmation" required class="w-full border rounded-lg px-3 py-2 text-sm"></div>
            @error('current_password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            @error('password')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Change password</button>
        </form>
    </div>
</div>
@endsection
