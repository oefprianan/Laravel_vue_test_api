<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JWTService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;
use Exception;

class LogoutController extends Controller
{
    private JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function __invoke(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'No authenticated user found',
                    'status' => 'error',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // 1. Revoke current Sanctum token
            if ($token = $request->bearerToken()) {
                $personalAccessToken = PersonalAccessToken::findToken($token);
                if ($personalAccessToken) {
                    $personalAccessToken->delete();
                }
            }

            // 2. Revoke all tokens for the user
            $user->tokens()->delete();

            // 3. Blacklist JWT token and clean up Redis
            $jwtToken = $request->header('X-JWT-Token');
            if ($jwtToken) {
                $this->jwtService->blacklistToken($jwtToken);
            }

            // 4. Clean up Redis data
            $userAgent = $request->header('User-Agent', 'unknown');
            Redis::hdel("user_devices:{$user->id}", $userAgent);
            Redis::del("user_tokens:{$user->id}");

            // 5. Clear session if exists
            if ($request->hasSession()) {
                $request->session()->flush();
                $request->session()->regenerate(true);
            }

            // 6. Clear all relevant cookies
            $cookiesToDelete = [
                'XSRF-TOKEN',
                'laravel_session',
                'remember_web_token',
                'token',
                'jwt_token',
                'refresh_token',
                'fingerprint'
            ];

            $response = response()->json([
                'message' => 'Successfully logged out',
                'status' => 'success',
            ]);

            // 7. Remove cookies
            foreach ($cookiesToDelete as $cookieName) {
                $response->withCookie(Cookie::forget($cookieName));
            }

            // 8. Set security headers
            return $response
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Clear-Site-Data', '"cache", "cookies", "storage"');

        } catch (Exception $e) {
            // Log error details if in development
            if (config('app.debug')) {
                Log::error('Logout error:', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return response()->json([
                'message' => 'An error occurred during logout',
                'error' => $e->getMessage(),
                'status' => 'error',
                'details' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
