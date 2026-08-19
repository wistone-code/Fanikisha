<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    private const SESSION_KEY = 'password_reset_user_id';

    private const MAX_ATTEMPTS = 5;

    private const CODE_LIFETIME_MINUTES = 15;

    // ---- Step 1: identify the account ----------------------------------------------

    public function showIdentify(): View
    {
        return view('auth.forgot-password.identify');
    }

    public function identify(Request $request, PasswordGeneratorService $passwords): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        // Deliberately generic error either way — confirming *which* field was wrong
        // would let someone enumerate valid usernames, which a real reset flow must
        // never allow.
        $user = User::whereRaw('LOWER(username) = ?', [strtolower($data['username'])])
            ->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])
            ->first();

        if (! $user) {
            return back()->withErrors([
                'username' => "We couldn't verify those details. Check your username and email and try again.",
            ])->withInput();
        }

        $code = $passwords->generateSixDigitCode();

        PasswordResetCode::where('user_id', $user->id)->whereNull('consumed_at')->delete();

        PasswordResetCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
        ]);

        Session::put(self::SESSION_KEY, $user->id);

        // This prototype has no real email/SMS server, so the code is flashed to the
        // session and shown directly on the next screen instead of being delivered.
        // In production, dispatch a Mail/Notification here and remove this flash.
        Session::flash('demo_code', $code);

        return redirect()->route('password.forgot.verify');
    }

    // ---- Step 2: verify the code ----------------------------------------------------

    public function showVerify(): View|RedirectResponse
    {
        if (! Session::has(self::SESSION_KEY)) {
            return redirect()->route('password.forgot.identify');
        }

        $user = User::findOrFail(Session::get(self::SESSION_KEY));

        return view('auth.forgot-password.verify', [
            'user' => $user,
            'demoCode' => Session::get('demo_code'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = Session::get(self::SESSION_KEY);
        abort_unless($userId, 419);

        $data = $request->validate(['code' => ['required', 'string']]);

        $reset = PasswordResetCode::where('user_id', $userId)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $reset || $reset->isExpired()) {
            Session::forget(self::SESSION_KEY);

            return redirect()->route('password.forgot.identify')
                ->withErrors(['username' => 'That reset session expired. Please start again.']);
        }

        if (! hash_equals($reset->code, $data['code'])) {
            $reset->increment('attempts');

            if ($reset->attempts >= self::MAX_ATTEMPTS) {
                $reset->update(['consumed_at' => now()]);
                Session::forget(self::SESSION_KEY);

                return redirect()->route('password.forgot.identify')
                    ->withErrors(['username' => 'Too many incorrect attempts. Please start again.']);
            }

            return back()->withErrors(['code' => 'Incorrect code. Please try again.']);
        }

        $reset->update(['consumed_at' => now()]);
        Session::put('password_reset_verified', true);

        return redirect()->route('password.forgot.reset');
    }

    // ---- Step 3: choose a new password ----------------------------------------------

    public function showReset(): View|RedirectResponse
    {
        if (! Session::get('password_reset_verified') || ! Session::has(self::SESSION_KEY)) {
            return redirect()->route('password.forgot.identify');
        }

        return view('auth.forgot-password.reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless(Session::get('password_reset_verified'), 419);
        $userId = Session::get(self::SESSION_KEY);
        abort_unless($userId, 419);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = User::findOrFail($userId);
        $user->forceFill([
            'password' => Hash::make($data['password']),
            // Identity was already verified through steps 1-2, and they chose this
            // password themselves, so there's no need to force another change.
            'must_change_password' => false,
        ])->save();

        Session::forget([self::SESSION_KEY, 'password_reset_verified']);

        return redirect()->route('login')->with('status', 'Password reset — you can now sign in.');
    }
}
