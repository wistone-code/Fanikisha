<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Applies baseline protective headers to every response. A strict
     * Content-Security-Policy is deliberately NOT included here: the current
     * Blade views rely on inline `onclick="..."` handlers and inline <style>
     * blocks throughout (for the modal show/hide toggles and the per-event
     * theme color variables), and a CSP tight enough to matter would either
     * break those or — with 'unsafe-inline' — provide no real protection. Adding
     * a real CSP is a legitimate follow-up, but it requires first moving every
     * onclick handler to addEventListener-based JS, which is a frontend
     * refactor beyond this pass. Track it separately rather than ship a CSP
     * that gives a false sense of protection.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
