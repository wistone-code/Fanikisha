<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use App\Support\DeviceLabel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * People who stay signed in (a phone's Home-Screen app keeps its session for hours or days) never pass through the
 * sign-in form, so they would never appear in the logs. The first time a signed-in session is seen without a sign-in
 * entry, one "opened the app" line is recorded for it. The sign-in paths mark the session themselves, so nobody is logged twice.
 */
class NoteSignedInDevice
{
    public const FLAG = 'activity_noted';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $request->isMethod('GET') && ! $request->session()->get(self::FLAG)) {
            $request->session()->put(self::FLAG, true);
            ActivityLogger::log('account.session', "{$user->name} ({$user->username}) opened Fanikisha, still signed in · ".DeviceLabel::current(), $user, actor: $user);
        }

        return $next($request);
    }
}
