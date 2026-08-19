<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Applies to every authenticated request. Accounts created by a System Admin or
     * event admin (temp password), and accounts that just completed the verified
     * forgot-password flow's OWN password step, get `must_change_password` cleared
     * immediately — this middleware only ever fires for someone still on their
     * original system-generated password.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('password.change.*', 'logout');

        if ($user && $user->must_change_password && ! $exempt) {
            return redirect()->route('password.change.show');
        }

        return $next($request);
    }
}
