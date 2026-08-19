<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventAdmin
{
    /**
     * Defense-in-depth: the UI already hides admin-only controls from viewers, but
     * every mutating route re-checks the role server-side too, exactly like the
     * prototype's requireEventAdmin() guard.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = app('currentEvent');
        $user = $request->user();

        if (! $event || ! $user || $user->is_super_user || ! $user->isAdminOn($event)) {
            abort(403, "You don't have permission to do that.");
        }

        return $next($request);
    }
}
