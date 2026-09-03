<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotSuspended
{
    /**
     * Applies to every authenticated request. Blocking at login (LoginController)
     * isn't enough on its own — an account suspended by System Admin while already
     * logged in elsewhere would otherwise keep working until they happened to log
     * out. This catches that case on their very next request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_suspended && ! $request->routeIs('logout')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'This account has been suspended. Contact your administrator.',
            ]);
        }

        return $next($request);
    }
}
