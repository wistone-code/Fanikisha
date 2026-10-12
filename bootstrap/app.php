<?php

use App\Http\Middleware\BlockTeamManagementForFuneral;
use App\Http\Middleware\EnsureEventAdmin;
use App\Http\Middleware\EnsureNotSuspended;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSuperUser;
use App\Http\Middleware\ResolveCurrentEvent;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        // Meta's WhatsApp reports are server-to-server calls with no session; they are checked by signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp']);
        $middleware->web(append: [\App\Http\Middleware\NoteSignedInDevice::class]);

        // IMPORTANT if deployed behind a load balancer / reverse proxy (nginx,
        // Cloudflare, a PaaS router, etc.): without this, $request->ip() returns
        // the PROXY's IP for every single visitor, which silently breaks the
        // per-IP login/password-reset rate limiters in AppServiceProvider —
        // every user would share one rate-limit bucket, either locking everyone
        // out together or (if the proxy IP is allow-listed) disabling the limit
        // entirely. Set trusted proxies to your actual load balancer/CDN IP
        // range(s) in production — '*' here is a permissive placeholder that
        // trusts the immediate upstream unconditionally and MUST be tightened
        // before going live.
        $middleware->trustProxies(at: ($proxies = env('TRUSTED_PROXIES', '*')) === '*' ? '*' : array_map('trim', explode(',', $proxies)), headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO);

        // Deliberately NOT appended to the global 'web' group: these both need
        // $request->user() to already be resolved, which only happens once the
        // route-level 'auth' middleware has run. They're applied explicitly, after
        // 'auth', on the authenticated route group in routes/web.php instead.
        $middleware->alias([
            'super_user' => EnsureSuperUser::class,
            'event_admin' => EnsureEventAdmin::class,
            'can_checkin' => \App\Http\Middleware\EnsureCanCheckin::class,
            'no_funeral_team' => BlockTeamManagementForFuneral::class,
            'resolve_event' => ResolveCurrentEvent::class,
            'feature' => \App\Http\Middleware\EnsureFeature::class,
            'password_changed' => EnsurePasswordChanged::class,
            'not_suspended' => EnsureNotSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A sign-in or sign-out form that was left open too long (or restored from the phone's memory) carries an
        // old security token. Instead of a dead-end "session expired" page, show the sign-in form again with a
        // fresh token and the username still filled in, so one more tap signs in.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || ! $request->routeIs('login.attempt', 'logout')) {
                return null;
            }

            return redirect()->route('login')
                ->withInput($request->only('username'))
                ->with('status', $request->routeIs('logout')
                    ? 'You are signed out.'
                    : 'The sign-in page had been open for a while, so it was refreshed. Please tap Sign in again.');
        });
    })->create();
