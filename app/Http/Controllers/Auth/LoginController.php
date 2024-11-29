<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redis;

class LoginController extends Controller
{
    private JWTService $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }
    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The credentials you entered are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        // Generate Sanctum token
        $sanctumToken = $user->createToken('auth-token')->plainTextToken;

        // Generate JWT tokens
        $tokens = $this->jwtService->generateTokenPair($user);

        // Store device info in Redis
        Redis::hset(
            "user_devices:{$user->id}",
            $request->header('User-Agent', 'unknown'),
            json_encode([
                'ip' => $request->ip(),
                'last_active' => now()->toISOString(),
            ])
        );

        return response()->json([
            'access_token' => $sanctumToken,
            'jwt_tokens' => $tokens,
            'user' => $user,
        ]);
    }
}