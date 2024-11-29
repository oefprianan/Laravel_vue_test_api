<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class EnhancedJWTVerification
{
    private JWTService $jwtService;
    private const MAX_ATTEMPTS = 60;
    private const DECAY_MINUTES = 1;
    private const MAX_FAILED_ATTEMPTS = 10;
    private const BLOCK_DURATION = 3600; // 1 hour

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $ip = $request->ip();
            $userAgent = $request->header('User-Agent');

            // ตรวจสอบว่า IP ถูกบล็อกหรือไม่
            if ($this->isIpBlocked($ip)) {
                return $this->buildBlockedResponse();
            }

            // ตรวจสอบ Rate Limit
            $rateLimitKey = $this->buildRateLimitKey($ip, $userAgent);
            if (!$this->checkRateLimit($rateLimitKey)) {
                return $this->buildTooManyAttemptsResponse($rateLimitKey);
            }

            // ตรวจสอบ Required Headers
            $token = $request->header('X-JWT-Token');
            $fingerprint = $request->header('X-Fingerprint');
            $sanctum_token = $request->header('sanctum_token');

            if (!$token && !$fingerprint && $sanctum_token) {
                return $this->buildMissingHeadersResponse();
            }

            // ตรวจสอบ Token Validation
            if (!$this->validateTokenAndFingerprint($token, $fingerprint, $ip)) {
                return $this->handleFailedValidation($ip);
            }

            // Reset failed attempts หลังจาก validation สำเร็จ
            $this->resetFailedAttempts($ip);

            $response = $next($request);

            // เพิ่ม Security Headers
            return $this->addSecurityHeaders($response);

        } catch (\Exception $e) {
            Log::error('JWT Verification Error:', [
                'error' => $e->getMessage(),
                'ip' => $ip ?? 'unknown',
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Security verification error',
                'status' => 'error',
            ], 500);
        }
    }

    private function isIpBlocked(string $ip): bool
    {
        return (bool) Redis::exists("jwt_blocked:{$ip}");
    }

    private function buildRateLimitKey(string $ip, ?string $userAgent): string
    {
        return "jwt_ratelimit:" . sha1($ip . '|' . ($userAgent ?? 'unknown'));
    }

    private function checkRateLimit(string $key): bool
    {
        $attempts = Redis::get($key) ?? 0;

        if ($attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        Redis::incr($key);
        Redis::expire($key, self::DECAY_MINUTES * 60);

        return true;
    }

    private function validateTokenAndFingerprint(string $token, string $fingerprint, string $ip): bool
    {
        $isValid = $this->jwtService->validateToken($token, $fingerprint);

        if (!$isValid) {
            $this->incrementFailedAttempts($ip);
        }

        return $isValid;
    }

    private function incrementFailedAttempts(string $ip): void
    {
        $failedKey = "jwt_failed:{$ip}";
        Redis::incr($failedKey);
        Redis::expire($failedKey, self::BLOCK_DURATION);

        // Log เมื่อมีการพยายามเข้าถึงที่ไม่ถูกต้องมากกว่า 5 ครั้ง
        if ((int) Redis::get($failedKey) > 5) {
            Log::warning('Multiple failed JWT validations detected', [
                'ip' => $ip,
                'attempts' => Redis::get($failedKey),
            ]);
        }
    }

    private function handleFailedValidation(string $ip): Response
    {
        $failedAttempts = (int) Redis::get("jwt_failed:{$ip}");

        if ($failedAttempts > self::MAX_FAILED_ATTEMPTS) {
            Redis::setex("jwt_blocked:{$ip}", self::BLOCK_DURATION, 1);

            Log::alert('IP blocked due to multiple failed attempts', [
                'ip' => $ip,
                'failed_attempts' => $failedAttempts,
            ]);

            return $this->buildBlockedResponse();
        }

        return response()->json([
            'message' => 'Invalid security credentials',
            'status' => 'error',
            'remaining_attempts' => self::MAX_FAILED_ATTEMPTS - $failedAttempts,
        ], 401);
    }

    private function resetFailedAttempts(string $ip): void
    {
        Redis::del("jwt_failed:{$ip}");
    }

    private function buildTooManyAttemptsResponse(string $key): Response
    {
        $retryAfter = Redis::ttl($key);
        $resetTime = now()->setTimezone('Asia/Bangkok')->addSeconds($retryAfter);

        return response()->json([
            'message' => 'Too many requests. Please try again later.',
            'status' => 'error',
            'retry_after' => $retryAfter,
            'retry_at' => $resetTime->format('Y-m-d H:i:s'), // เวลาไทย
            'timezone' => 'Asia/Bangkok',
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Reset' => $resetTime->timestamp,
        ]);
    }

    private function buildBlockedResponse(): Response
    {
        $hours = intdiv(self::BLOCK_DURATION, 3600); // คำนวณชั่วโมง
        $minutes = (self::BLOCK_DURATION % 3600) / 60; // คำนวณนาที

        return response()->json([
            'message' => 'Access blocked due to suspicious activity',
            'status' => 'error',
            'block_duration' => "{$hours} hours {$minutes} minutes", // แสดงผลแบบชั่วโมงและนาที
        ], 403);
    }

    private function buildMissingHeadersResponse(): Response
    {
        return response()->json([
            'message' => 'Missing required security headers',
            'status' => 'error',
            'required_headers' => ['X-JWT-Token', 'X-Fingerprint', 'sanctum_token'],
        ], 401);
    }

    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
