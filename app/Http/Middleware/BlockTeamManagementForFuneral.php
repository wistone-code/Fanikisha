<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockTeamManagementForFuneral
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = app('currentEvent');

        if ($event?->isFuneral()) {
            abort(404);
        }

        return $next($request);
    }
}
