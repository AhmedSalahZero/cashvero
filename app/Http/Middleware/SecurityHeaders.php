<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds the standard browser protections to every web response (Security SEC-16).
 *
 *  - X-Content-Type-Options   : the browser must not guess file types
 *  - X-Frame-Options          : the site cannot be shown inside another site's frame (clickjacking)
 *  - Referrer-Policy          : do not leak full addresses to other sites
 *  - Strict-Transport-Security: only over HTTPS, and only when the request itself was HTTPS
 *  - Content-Security-Policy-Report-Only: describes what the pages load. REPORT-ONLY on purpose:
 *    the browser only logs what it would block, so no page can break. Tighten and switch to
 *    "Content-Security-Policy" once the browser console shows no violations.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy-Report-Only' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https: ws: wss:; frame-ancestors 'self'",
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
