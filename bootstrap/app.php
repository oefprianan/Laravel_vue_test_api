<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// สร้างฟังก์ชัน env ถ้ายังไม่มี
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        \App\Console\Commands\GenerateJWTSecret::class
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ตรวจสอบ environment
        $isProduction = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production';

        if ($isProduction) {
            $middleware->use([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\SecurityHeaders::class, // เพิ่ม Security Headers ใน production
            ]);
        }

        // Global middleware
        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\GlobalCors::class,
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \App\Http\Middleware\CheckRoute::class,
            \App\Http\Middleware\SecurityHeaders::class, // เพิ่ม Security Headers สำหรับทุก environment
            \App\Http\Middleware\RateLimitRequests::class, // เพิ่ม Rate Limiting
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        // API configurations
        $middleware->statefulApi();

        // API middleware group
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\RateLimitRequests::class,
        ]);

        // Web middleware group
        $middleware->group('web', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Register middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \App\Http\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'verify.token' => \App\Http\Middleware\VerifyTokenMiddleware::class,
            'check.route' => \App\Http\Middleware\CheckRoute::class,
            'global.cors' => \App\Http\Middleware\GlobalCors::class,
            'jwt.verify' => \App\Http\Middleware\EnhancedJWTVerification::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class, // เพิ่ม alias
            'rate.limit' => \App\Http\Middleware\RateLimitRequests::class, // เพิ่ม alias
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    });
return $app->create();