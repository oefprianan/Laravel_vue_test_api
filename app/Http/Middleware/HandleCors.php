<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class HandleCors
{
    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->settings = config('cors');

        if (!$this->isCorsRequest($request)) {
            return $next($request);
        }

        if (!$this->isOriginAllowed($request)) {
            return $this->forbiddenResponse();
        }

        if ($this->isPreflightRequest($request)) {
            return $this->handlePreflight($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Determine if request is a CORS request.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function isCorsRequest(Request $request): bool
    {
        return $request->headers->has('Origin');
    }

    /**
     * Determine if request is preflight.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->getMethod() === 'OPTIONS' && $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Determine if the origin is allowed.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function isOriginAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin', '');

        if (in_array('*', $this->settings['allowed_origins'] ?? [], true)) {
            return true;
        }

        if (in_array($origin, $this->settings['allowed_origins'] ?? [], true)) {
            return true;
        }

        foreach ($this->settings['allowed_origins_patterns'] ?? [] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle preflight request.
     *
     * @param  Request  $request
     * @return Response
     */
    protected function handlePreflight(Request $request): Response
    {
        $response = response('', 204);
        $this->addPreflightHeaders($request, $response);
        return $response;
    }

    /**
     * Add CORS headers to the response.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins');

        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        if ($this->settings['supports_credentials'] ?? false) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // ตั้งค่า exposed headers
        if (!empty($this->settings['exposed_headers'])) {
            $exposedHeaders = implode(', ', $this->settings['exposed_headers']);
            $response->headers->set('Access-Control-Expose-Headers', $exposedHeaders);
        }

        // ตั้งค่า max-age
        $maxAge = $this->settings['max_age'] ?? 0;
        if ($maxAge > 0) {
            $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
        }

        return $response;
    }

    /**
     * Add preflight headers to the response.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return void
     */
    protected function addPreflightHeaders(Request $request, Response $response): void
    {
        if ($this->isOriginAllowed($request)) {
            $origin = $request->headers->get('Origin');
            if ($origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
            }
        }

        if ($this->settings['supports_credentials'] ?? false) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $allowedMethods = is_array($this->settings['allowed_methods'] ?? [])
        ? implode(', ', $this->settings['allowed_methods'])
        : (string) ($this->settings['allowed_methods'] ?? '');
        $response->headers->set('Access-Control-Allow-Methods', $allowedMethods);

        $allowedHeaders = is_array($this->settings['allowed_headers'] ?? [])
        ? implode(', ', $this->settings['allowed_headers'])
        : (string) ($this->settings['allowed_headers'] ?? '');
        $response->headers->set('Access-Control-Allow-Headers', $allowedHeaders);

        $maxAge = $this->settings['max_age'] ?? 0;
        if ($maxAge > 0) {
            $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
        }
    }

    /**
     * Create forbidden response.
     *
     * @return Response
     */
    protected function forbiddenResponse(): Response
    {
        return new Response('Forbidden (CORS)', 403);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->settings['logging'] ?? false) {
            $corsHeaders = array_filter(
                $response->headers->all(),
                fn(string $key): bool => str_starts_with($key, 'access-control-'),
                ARRAY_FILTER_USE_KEY
            );

            Log::debug('CORS Response', [
                'path' => $request->path(),
                'origin' => $request->header('Origin'),
                'status' => $response->getStatusCode(),
                'cors_headers' => $corsHeaders,
            ]);
        }
    }
}
