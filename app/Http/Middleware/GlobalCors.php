<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GlobalCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins');

        if (method_exists($response, 'header') &&
            (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins))) {

            // CORS Headers
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', $this->getAllowedHeaders());
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
            $response->headers->set('Access-Control-Expose-Headers',
                'Content-Disposition, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, X-Request-Id'
            );
        }

        // Preflight Handling
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200)
                ->withHeaders([
                    'Access-Control-Max-Age' => '86400',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Origin' => $origin ?: '*',
                    'Content-Length' => '0',
                    'Content-Type' => 'text/plain',
                ]);
        }

        return $response;
    }

    protected function getAllowedHeaders(): string
    {
        return implode(', ', [
            'Accept',
            'Authorization',
            'Content-Type',
            'X-Requested-With',
            'X-CSRF-TOKEN',
            'X-Socket-Id',
            'Origin',
            'Accept-Language',
            'Access-Control-Request-Method',
            'Access-Control-Request-Headers',
        ]);
    }
}
