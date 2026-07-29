<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\User;
use App\Services\ApiService;
use App\Services\JWTService;
use PDO;

class AuthController
{
    private ApiService $api;
    private JWTService $jwt;

    public function __construct()
    {
        $this->api = new ApiService();
        $this->jwt = new JWTService();
    }

    public function login(Request $request, Response $response): void
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (empty($email) || empty($password)) {
            $this->api->errorResponse('Email and password are required.', 422, 'VALIDATION_ERROR', [
                'email' => empty($email) ? ['The email field is required.'] : [],
                'password' => empty($password) ? ['The password field is required.'] : [],
            ])->send();
            return;
        }

        $user = User::findByEmail($email);

        if ($user === null || !password_verify($password, $user->password_hash)) {
            $this->api->errorResponse('Invalid credentials.', 401, 'INVALID_CREDENTIALS')->send();
            return;
        }

        $token = $this->jwt->generateToken([
            'sub' => $user->id,
            'email' => $user->email,
        ]);

        $refreshToken = $this->jwt->generateRefreshToken($user->id);

        $this->storeRefreshToken($user->id, $refreshToken);

        $this->api->successResponse([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => $user->toArray(),
        ])->send();
    }

    public function register(Request $request, Response $response): void
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);

        $validation = $this->api->validateRequest($data, [
            'name' => 'required|min:2|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validation !== null) {
            $validation->send();
            return;
        }

        if (isset($data['password_confirmation']) && $data['password'] !== $data['password_confirmation']) {
            $this->api->validationErrorResponse([
                'password_confirmation' => ['The password confirmation does not match.'],
            ])->send();
            return;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = $this->jwt->generateToken([
            'sub' => $user->id,
            'email' => $user->email,
        ]);

        $this->api->createdResponse([
            'token' => $token,
            'user' => $user->toArray(),
        ])->send();
    }

    public function refresh(Request $request, Response $response): void
    {
        $refreshToken = $request->input('refresh_token');

        if (empty($refreshToken)) {
            $this->api->errorResponse('Refresh token is required.', 422, 'VALIDATION_ERROR')->send();
            return;
        }

        $payload = $this->jwt->validateToken($refreshToken);

        if ($payload === null || !isset($payload->type) || $payload->type !== 'refresh') {
            $this->api->errorResponse('Invalid or expired refresh token.', 401, 'INVALID_REFRESH_TOKEN')->send();
            return;
        }

        $stored = $this->findRefreshToken($payload->sub, $refreshToken);
        if ($stored === null) {
            $this->api->errorResponse('Refresh token has been revoked.', 401, 'TOKEN_REVOKED')->send();
            return;
        }

        $user = User::findById((int) $payload->sub);
        if ($user === null) {
            $this->api->errorResponse('User not found.', 404, 'USER_NOT_FOUND')->send();
            return;
        }

        $this->revokeRefreshToken($refreshToken);

        $newToken = $this->jwt->generateToken([
            'sub' => $user->id,
            'email' => $user->email,
        ]);

        $newRefreshToken = $this->jwt->generateRefreshToken($user->id);
        $this->storeRefreshToken($user->id, $newRefreshToken);

        $this->api->successResponse([
            'token' => $newToken,
            'refresh_token' => $newRefreshToken,
        ])->send();
    }

    public function me(Request $request, Response $response): void
    {
        $userId = $_SERVER['auth_user_id'] ?? null;

        if ($userId === null) {
            $this->api->errorResponse('Unauthenticated.', 401, 'UNAUTHENTICATED')->send();
            return;
        }

        $user = User::findById((int) $userId);

        if ($user === null) {
            $this->api->errorResponse('User not found.', 404, 'USER_NOT_FOUND')->send();
            return;
        }

        $this->api->successResponse($user->toArray())->send();
    }

    public function logout(Request $request, Response $response): void
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $this->blacklistToken($token);
        }

        $refreshToken = $request->input('refresh_token');
        if ($refreshToken !== null) {
            $this->revokeRefreshToken($refreshToken);
        }

        $this->api->successResponse(['message' => 'Successfully logged out.'])->send();
    }

    private function storeRefreshToken(int $userId, string $token): void
    {
        $db = Database::connection();
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 604800);

        $stmt = $db->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :hash, :expires, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':hash' => $hash,
            ':expires' => $expiresAt,
        ]);
    }

    private function findRefreshToken(int $userId, string $token): ?array
    {
        $db = Database::connection();
        $hash = hash('sha256', $token);

        $stmt = $db->prepare(
            'SELECT * FROM refresh_tokens WHERE user_id = :user_id AND token_hash = :hash AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP'
        );
        $stmt->execute([':user_id' => $userId, ':hash' => $hash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function revokeRefreshToken(string $token): void
    {
        $db = Database::connection();
        $hash = hash('sha256', $token);

        $stmt = $db->prepare(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = :hash'
        );
        $stmt->execute([':hash' => $hash]);
    }

    private function blacklistToken(string $token): void
    {
        $db = Database::connection();
        $hash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $db->prepare(
            'INSERT INTO token_blacklist (token_hash, expires_at, created_at) VALUES (:hash, :expires, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([':hash' => $hash, ':expires' => $expiresAt]);
    }
}
