<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\AccountMailer;
use App\Services\BeemSmsService;
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

    private const CHANNEL_KEY = 'password_reset_channel';

    private const MAX_ATTEMPTS = 5;

    private const CODE_LIFETIME_MINUTES = 15;

    // ---- Step 1: identify the account ----------------------------------------------

    public function showIdentify(): View
    {
        return view('auth.forgot-password.identify');
    }

    public function identify(Request $request, PasswordGeneratorService $passwords, AccountMailer $mailer, BeemSmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
            'channel' => ['required', 'in:email,sms'],
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

        if ($data['channel'] === 'sms' && blank($user->phone)) {
            return back()->withErrors([
                'username' => "No phone number is on file for this account, so a code can't be sent by SMS. Choose email instead.",
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
        Session::put(self::CHANNEL_KEY, $data['channel']);

        if ($data['channel'] === 'sms') {
            $result = $sms->sendSingle(
                'Your '.config('app.name')." password reset code is {$code}. It expires in ".self::CODE_LIFETIME_MINUTES." minutes. If you didn't request this, ignore this message.",
                $user->phone
            );
            $sent = (bool) ($result['successful'] ?? false);
        } else {
            $sent = $mailer->sendResetCode($user, $code, self::CODE_LIFETIME_MINUTES);
        }

        if (! $sent) {
            return back()->withErrors([
                'username' => "We couldn't send the code by ".($data['channel'] === 'sms' ? 'SMS' : 'email').'. Please try again shortly, try the other option, or contact your administrator.',
            ])->withInput();
        }

        return redirect()->route('password.forgot.verify');
    }

    // ---- Step 2: verify the code ----------------------------------------------------

    public function showVerify(): View|RedirectResponse
    {
        if (! Session::has(self::SESSION_KEY)) {
            return redirect()->route('password.forgot.identify');
        }

        $user = User::findOrFail(Session::get(self::SESSION_KEY));

        $reset = PasswordResetCode::where('user_id', $user->id)->whereNull('consumed_at')->latest()->first();

        return view('auth.forgot-password.verify', [
            'user' => $user,
            'channel' => Session::get(self::CHANNEL_KEY, 'email'),
            'maskedEmail' => $this->maskEmail($user->email),
            'maskedPhone' => $this->maskPhone($user->phone),
            'expiresAt' => $reset?->expires_at,
        ]);
    }

    private function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? '' : '•••'.substr($digits, -4);
    }

    private function maskEmail(?string $email): string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 2).'•••@'.$domain;
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

        Session::forget([self::SESSION_KEY, self::CHANNEL_KEY, 'password_reset_verified']);

        return redirect()->route('login')->with('status', 'Password reset — you can now sign in.');
    }
}
