<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * A precomputed bcrypt hash with no corresponding real password. Used to force
     * Hash::check() to do real work even when no matching user was found — see the
     * comment in authenticate() for why this matters. Generated once via:
     *   php -r 'echo password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ["cost"=>12]);'
     * A malformed/too-short placeholder here would silently defeat the whole fix,
     * since PHP's password_verify() returns false almost instantly for a
     * syntactically invalid hash instead of doing the full bcrypt computation.
     */
    private const DUMMY_HASH = '$2y$12$.dxO7BjARktrSSf7F2x88O2LSirU18g9hJgqcb0SwfWUnLcesnniO';

    public function show(): \Illuminate\View\View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::whereRaw('LOWER(username) = ?', [strtolower($credentials['username'])])->first();

        // Laravel's Auth::attempt() returns near-instantly when the username doesn't
        // exist (no user found = no hash comparison performed), but takes the full
        // bcrypt verification time (~100ms+) when the username IS valid and only the
        // password was wrong. Even though the error message is identical either way,
        // that response-time gap alone lets an attacker enumerate valid usernames by
        // timing many attempts. Always running Hash::check() — against a real hash
        // when the user exists, or a fixed dummy hash when they don't — makes both
        // paths take the same amount of time, closing that side channel.
        $passwordMatches = Hash::check($credentials['password'], $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'username' => 'Incorrect username or password.',
            ]);
        }

        // "Remember me" is opt-in, not automatic — defaulting every login to a
        // persistent cookie is a poor security default on shared/public devices.
        Auth::login($user, remember: $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
