<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\JWTService;
use PDO;

class ApiAuthMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $authHeader = $request->header('Authorization');
        $apiKey = $request->header('X-API-Key');

        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $this->authenticateJwt($token, $request, $response, $next);
        }

        if ($apiKey !== null) {
            return $this->authenticateApiKey($apiKey, $request, $response, $next);
        }

        $response->json([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication required. Provide a Bearer token or X-API-Key header.',
                'errors' => null,
            ],
            'meta' => null,
        ], 401)->send();
        exit;
    }

    private function authenticateJwt(string $token, Request $request, Response $response, callable $next): mixed
    {
        $jwtService = new JWTService();
        $payload = $jwtService->validateToken($token);

        if ($payload === null) {
            $response->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'The provided token is invalid or expired.',
                    'errors' => null,
                ],
                'meta' => null,
            ], 401)->send();
            exit;
        }

        $request->input('auth_user_id', $payload->sub ?? null);
        $request->input('auth_workspace_id', $payload->workspace_id ?? null);

        $_SERVER['auth_user_id'] = $payload->sub ?? null;
        $_SERVER['auth_workspace_id'] = $payload->workspace_id ?? null;

        return $next($request, $response);
    }

    private function authenticateApiKey(string $apiKey, Request $request, Response $response, callable $next): mixed
    {
        $hashedKey = hash('sha256', $apiKey);

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM api_keys WHERE key_hash = :key_hash AND revoked_at IS NULL');
        $stmt->execute([':key_hash' => $hashedKey]);
        $keyData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($keyData === false) {
            $response->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'The provided API key is invalid or has been revoked.',
                    'errors' => null,
                ],
                'meta' => null,
            ], 401)->send();
            exit;
        }

        $updateStmt = $db->prepare('UPDATE api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([':id' => $keyData['id']]);

        $_SERVER['auth_user_id'] = $keyData['user_id'];
        $_SERVER['auth_workspace_id'] = $keyData['workspace_id'] ?? null;

        return $next($request, $response);
    }
}
