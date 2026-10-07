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

        // A disabled team member (e.g. door staff after the event) is signed out straight away.
        if ($event) {
            $membership = $user->eventMemberships()->where('event_id', $event->id)->first();

            if ($membership?->disabled_at) {
                \App\Services\ActivityLogger::log('account.logout', "{$user->name} ({$user->username}) was signed out: disabled by the event organiser", $user, $event, actor: $user);
                \Illuminate\Support\Facades\Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['username' => 'This account has been disabled by the event organiser.']);
            }

            // Door staff ("scanner") only ever see the check-in screen.
            if ($membership?->role === 'scanner' && ! $request->routeIs('checkin.*', 'keep-alive', 'logout', 'password.*', 'dashboard')) {
                abort(403, 'Door staff accounts can only use check-in.');
            }
        }

        // E-card-only accounts only get the guest-card screens, check-in and a trimmed
        // Setting page. Everything else (pledges, finances, providers, team…) is hidden
        // from the menu AND blocked here, so typing the URL doesn't reach it either.
        // Block-list rather than allow-list: the pages that have no meaning without payments.
        if ($event?->isEcard() && $request->routeIs(
            'financial.*', 'pledges.*', 'providers.*', 'committees.*', 'schedule.*',
            'event.settings.auto-reminder', 'event.settings.payout', 'event.settings.couple-threshold',
        )) {
            abort(404);
        }

        return $next($request);
    }
}
