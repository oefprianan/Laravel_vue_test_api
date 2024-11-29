<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    protected int $maxAttempts = 60;
    protected int $decayMinutes = 1;

    protected function getRateLimitByMethod(string $method): array
    {
        // กำหนด rate limit ตาม HTTP method
        return match (strtoupper($method)) {
            'GET' => ['attempts' => 60, 'decay' => 1],
            'POST' => ['attempts' => 30, 'decay' => 1],
            'PUT', 'PATCH' => ['attempts' => 30, 'decay' => 1],
            'DELETE' => ['attempts' => 20, 'decay' => 1],
            default => ['attempts' => 60, 'decay' => 1]
        };
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $route = Route::current();

            if (!$route) {
                return $next($request);
            }

            // ดึงค่า rate limit ตาม HTTP method
            $limits = $this->getRateLimitByMethod($request->method());

            $this->maxAttempts = $limits['attempts'];
            $this->decayMinutes = $limits['decay'];

            $key = $this->resolveRequestSignature($request);

            if ($this->tooManyAttempts($key)) {
                return $this->buildTooManyAttemptsResponse($key);
            }

            $this->incrementAttempts($key);

            $response = $next($request);

            // เพิ่ม Security Headers
            return $this->addSecurityHeaders(
                $this->addRateLimitHeaders($response, $key)
            );

        } catch (\Exception $e) {
            Log::error('Rate limit error:', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'message' => 'Rate limit error occurred',
                'status' => 'error',
            ], 500);
        }
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $route = Route::current();

        return sha1(implode('|', [
            $request->method(),
            $request->ip(),
            $route ? $route->uri() : $request->path(),
            $request->user()?->id ?? 'guest',
            $request->header('User-Agent') ?? 'unknown',
        ]));
    }

    protected function tooManyAttempts(string $key): bool
    {
        $attempts = Redis::get("ratelimit:{$key}") ?? 0;
        return $attempts >= $this->maxAttempts;
    }

    protected function incrementAttempts(string $key): void
    {
        Redis::incr("ratelimit:{$key}");
        Redis::expire("ratelimit:{$key}", $this->decayMinutes * 60);

        if ((Redis::get("ratelimit:{$key}") ?? 0) > ($this->maxAttempts / 2)) {
            Log::warning('High API usage detected', [
                'key' => $key,
                'attempts' => Redis::get("ratelimit:{$key}"),
                'max_attempts' => $this->maxAttempts,
            ]);
        }
    }

    protected function calculateRemainingAttempts(string $key): int
    {
        $attempts = Redis::get("ratelimit:{$key}") ?? 0;
        return max($this->maxAttempts - $attempts, 0);
    }

    protected function buildTooManyAttemptsResponse(string $key): Response
    {
        $retryAfter = Redis::ttl("ratelimit:{$key}");
        $resetTime = now()->addSeconds($retryAfter);

        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'status' => 'error',
            'retry_after' => $retryAfter,
            'retry_at' => $resetTime->format('Y-m-d H:i:s'),
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Reset' => $resetTime->getTimestamp(),
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    protected function addRateLimitHeaders(Response $response, string $key): Response
    {
        $remaining = $this->calculateRemainingAttempts($key);
        $resetTime = time() + Redis::ttl("ratelimit:{$key}");

        $response->headers->set('X-RateLimit-Limit', $this->maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', $resetTime);

        return $response;
    }

    protected function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
