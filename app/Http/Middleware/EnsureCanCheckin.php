<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Check-in is open to event admins and to door staff ("scanner" role). */
class EnsureCanCheckin
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = app('currentEvent');
        $user = $request->user();

        if (! $event || ! $user || $user->is_super_user || ! in_array($user->roleOn($event), ['admin', 'scanner'], true)) {
            abort(403, "You don't have permission to do that.");
        }

        return $next($request);
    }
}
