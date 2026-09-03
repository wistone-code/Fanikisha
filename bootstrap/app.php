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
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR |
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
            'no_funeral_team' => BlockTeamManagementForFuneral::class,
            'resolve_event' => ResolveCurrentEvent::class,
            'password_changed' => EnsurePasswordChanged::class,
            'not_suspended' => EnsureNotSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
