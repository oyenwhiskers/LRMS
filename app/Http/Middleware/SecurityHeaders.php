<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // One-time wipe of leftover RecordPlus Cache API / service worker on this origin.
        if (! $request->cookies->has('lrms_origin_purged')) {
            $response->headers->set('Clear-Site-Data', '"cache", "storage"');
            $response->headers->setCookie(cookie(
                name: 'lrms_origin_purged',
                value: '1',
                minutes: 60 * 24 * 365,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: false,
                sameSite: 'lax',
            ));
        }

        return $response;
    }
}
