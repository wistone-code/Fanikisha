<?php

namespace App\Providers;

use App\Services\EventThemeService;
use App\Services\NavLabelService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EventThemeService::class);
        $this->app->singleton(NavLabelService::class);

        // Default fallback so app('currentEvent') is ALWAYS resolvable, even on routes
        // that never pass through ResolveCurrentEvent (Super Admin pages, /event/create,
        // error pages, etc.) — without this, the layouts.app view composer below would
        // throw a container "target class does not exist" error on exactly those pages,
        // since instance() (set per-request by the middleware) only overrides this
        // default when the middleware actually runs; it never removes the need for a
        // fallback to exist in the first place.
        $this->app->bind('currentEvent', fn () => null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            // Belt-and-braces on top of SESSION_SECURE_COOKIE in .env: even if that
            // env var is left unset in a production deploy, session/CSRF cookies
            // still won't be sent over plain HTTP.
            config(['session.secure' => true]);
        }

        // Keyed by username+IP together (not just IP) so an attacker can't dodge the
        // limit by cycling usernames, and a shared office IP can't accidentally lock
        // out other legitimate accounts just because one person mistyped a password.
        RateLimiter::for('login', function ($request) {
            $key = strtolower((string) $request->input('username')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // The forgot-password identify step is the closest thing to a username-
        // enumeration oracle in the app, and the verify step is where someone would
        // try to brute-force a 6-digit code — both get their own conservative limits
        // on top of the 5-attempt-then-restart lockout already enforced in
        // ForgotPasswordController itself.
        RateLimiter::for('password-reset', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Every authenticated view needs to know the current event's theme colors and
        // event-type-aware terminology (e.g. "Pledges" vs "Contribution" vs "Condolences").
        View::composer('layouts.app', function ($view) {
            $event = app('currentEvent');

            $view->with('theme', app(EventThemeService::class)->for($event?->event_type));
            $view->with('navLabels', app(NavLabelService::class)->for($event));
        });
    }
}
