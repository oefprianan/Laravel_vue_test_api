<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JWTService;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    private JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function __invoke(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'message' => 'Refresh token is required',
            ], 400);
        }

        $tokens = $this->jwtService->refreshToken($refreshToken);

        if (!$tokens) {
            return response()->json([
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        return response()->json([
            'message' => 'Tokens refreshed successfully',
            'data' => $tokens,
        ]);
    }
}
