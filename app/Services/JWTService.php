<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class JWTService
{
    private string $key;
    private string $algorithm;
    private int $ttl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->key = config('jwt.secret');
        $this->algorithm = 'HS512';
        $this->ttl = config('jwt.ttl', 60);
        $this->refreshTtl = config('jwt.refresh_ttl', 20160);
    }

    public function generateTokenPair(User $user): array
    {
        $tokenId = Str::random(32);
        $fingerprint = $this->generateFingerprint();

        $accessToken = $this->createAccessToken($user, $tokenId, $fingerprint);
        $refreshToken = $this->createRefreshToken($user, $tokenId);

        $this->storeTokenMetadata($tokenId, $user->id, $fingerprint);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'fingerprint' => $fingerprint,
            'expires_in' => $this->ttl * 60,
        ];
    }

    private function createAccessToken(User $user, string $tokenId, string $fingerprint): string
    {
        $payload = [
            'iss' => config('app.url'),
            'aud' => config('app.url'),
            'iat' => time(),
            'exp' => time() + ($this->ttl * 60),
            'jti' => $tokenId,
            'sub' => $user->id,
            'prv' => hash('sha256', $fingerprint),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'nonce' => Str::random(32),
        ];

        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    private function createRefreshToken(User $user, string $tokenId): string
    {
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + ($this->refreshTtl * 60),
            'jti' => $tokenId . '-refresh',
            'nonce' => Str::random(32),
        ];

        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    private function generateFingerprint(): string
    {
        return hash('sha256', Str::random(32));
    }

    private function storeTokenMetadata(string $tokenId, int $userId, string $fingerprint): void
    {
        $metadata = [
            'user_id' => $userId,
            'fingerprint' => $fingerprint,
            'created_at' => time(),
        ];

        Redis::hmset("jwt_token:{$tokenId}", $metadata);
        Redis::expire("jwt_token:{$tokenId}", $this->ttl * 60);
        Redis::sadd("user_tokens:{$userId}", $tokenId);
    }

    public function validateToken(string $token, string $fingerprint = null): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));

            if ($this->isTokenBlacklisted($decoded->jti)) {
                return false;
            }

            if ($fingerprint && isset($decoded->prv)) {
                $storedMetadata = Redis::hgetall("jwt_token:{$decoded->jti}");
                if (!$storedMetadata || hash('sha256', $fingerprint) !== $decoded->prv) {
                    return false;
                }
            }

            return $decoded->exp > time();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function refreshToken(string $refreshToken): ?array
    {
        try {
            $decoded = JWT::decode($refreshToken, new Key($this->key, $this->algorithm));

            if (time() >= $decoded->exp) {
                return null;
            }

            $user = User::find($decoded->sub);
            if (!$user) {
                return null;
            }

            return $this->generateTokenPair($user);

        } catch (\Exception $e) {
            return null;
        }
    }

    private function isTokenBlacklisted(string $tokenId): bool
    {
        return Redis::exists("jwt_blacklist:{$tokenId}");
    }

    public function blacklistToken(string $token): void
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->algorithm));
            Redis::setex("jwt_blacklist:{$decoded->jti}", $this->ttl * 60, 1);
            Redis::del("jwt_token:{$decoded->jti}");
            Redis::srem("user_tokens:{$decoded->sub}", $decoded->jti);
        } catch (\Exception $e) {
            // Token is invalid
        }
    }
}
