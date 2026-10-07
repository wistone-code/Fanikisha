<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    /** Shown automatically (via EnsurePasswordChanged) right after first login or an admin-triggered reset. */
    public function show(): View
    {
        return view('auth.password-change-forced');
    }

    public function update(Request $request): RedirectResponse
    {
        // This screen is only for the first sign-in / admin-reset password. Changing it later needs the current password (updateOwn).
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();
        \App\Support\SessionCleaner::endOthers($request->user(), $request->session()->getId());

        return redirect()->route('dashboard');
    }

    /** Voluntary change from the account dropdown — requires the current password. */
    public function updateOwn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();
        \App\Support\SessionCleaner::endOthers($user, $request->session()->getId());

        return back()->with('status', 'Password changed.');
    }
}
