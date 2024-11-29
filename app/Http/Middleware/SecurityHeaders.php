<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    protected array $securityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'",
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ];

    protected array $headersToRemove = [
        'Server',
        'X-Powered-By',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove unwanted headers
        foreach ($this->headersToRemove as $header) {
            $response->headers->remove($header);
        }

        // Add security headers
        foreach ($this->securityHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
