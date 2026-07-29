<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class JWTService
{
    private string $secret;
    private string $algorithm;
    private int $ttl;
    private int $refreshTtl;

    public function __construct()
    {
        $this->secret = Env::get('APP_KEY', '');
        if ($this->secret === '') {
            throw new RuntimeException('APP_KEY is not set');
        }
        $this->algorithm = 'HS256';
        $this->ttl = (int) Env::get('JWT_TTL', 3600);
        $this->refreshTtl = (int) Env::get('JWT_REFRESH_TTL', 604800);
    }

    public function generateToken(array $payload): string
    {
        $now = time();
        $token = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    public function generateRefreshToken(int $userId): string
    {
        $now = time();
        $payload = [
            'sub' => $userId,
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'jti' => bin2hex(random_bytes(16)),
        ];
        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (\Throwable) {
            return null;
        }
    }

    public function getPayload(string $token): ?array
    {
        $decoded = $this->validateToken($token);
        return $decoded !== null ? (array) $decoded : null;
    }
}
