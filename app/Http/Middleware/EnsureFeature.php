<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `feature:cards` — blocks a route when the current event's package does not include that feature. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $event = app('currentEvent');

        if (! $event || ! $event->hasFeature($feature)) {
            return response()->view('errors.not-in-package', ['feature' => $feature], 403);
        }

        return $next($request);
    }
}
