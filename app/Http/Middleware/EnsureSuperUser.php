<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_super_user) {
            abort(403, "You don't have permission to do that.");
        }

        return $next($request);
    }
}
