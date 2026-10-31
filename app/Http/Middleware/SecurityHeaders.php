<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Browser safety headers on every page; hides the PHP version */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        header_remove('X-Powered-By');

        $response->headers->set('X-Content-Type-Options', 'nosniff'); // uploads are never run as a different type
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN'); // no clickjacking from other sites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(self)'); // mic: chat voice notes
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
