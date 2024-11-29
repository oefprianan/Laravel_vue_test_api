<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class VerifyTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'No token provided',
                'status' => 'error',
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken || $accessToken->tokenable_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Invalid or expired token',
                'status' => 'error',
            ], 401);
        }

        return $next($request);
    }
}