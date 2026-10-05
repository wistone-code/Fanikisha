<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentEvent
{
    /**
     * Every non-super-user account belongs to at most one event (self-service created,
     * or invited as a team member). This resolves it once per request and binds it as
     * 'currentEvent' so controllers, policies, and the layout view composer can all
     * share the same instance without re-querying.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_super_user) {
            app()->instance('currentEvent', null);

            // System Admin has zero visibility into event data by design — if they
            // land on an event-scoped route (e.g. an old bookmark, a typed URL),
            // send them to their own screen instead of letting every controller
            // downstream deal with a null $event.
            if ($user?->is_super_user && ! $request->routeIs('admin.*', 'logout')) {
                return redirect()->route('admin.users.index');
            }

            return $next($request);
        }

        $event = $user->currentEvent();
        app()->instance('currentEvent', $event);

        if (! $event && ! $request->routeIs('event.create', 'event.store', 'logout', 'password.change.*')) {
            return redirect()->route('event.create');
        }

        // E-card-only accounts only get the guest-card screens, check-in and a trimmed
        // Setting page. Everything else (pledges, finances, providers, team…) is hidden
        // from the menu AND blocked here, so typing the URL doesn't reach it either.
        if ($event?->isEcard() && ! $request->routeIs(
            'dashboard', 'guests.*', 'checkin.*', 'team.*', 'account.*', 'keep-alive', 'logout', 'password.*',
            'event.settings', 'event.settings.update', 'event.settings.card-photo.*', 'event.settings.sms-language', 'event.settings.theme-color',
        )) {
            abort(404);
        }

        return $next($request);
    }
}
