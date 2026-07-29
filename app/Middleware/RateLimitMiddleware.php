<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Env;
use PDO;

class RateLimitMiddleware extends Middleware
{
    private int $maxAttempts;
    private int $decaySeconds;
    private string $prefix;

    public function __construct(int $maxAttempts = 0, int $decaySeconds = 60, string $prefix = 'api')
    {
        $this->maxAttempts = $maxAttempts > 0 ? $maxAttempts : (int) Env::get('RATE_LIMIT_API', 60);
        $this->decaySeconds = $decaySeconds;
        $this->prefix = $prefix;
    }

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $identifier = $this->resolveIdentifier($request);
        $key = $this->buildKey($identifier);

        $current = $this->getAttempts($key);

        if ($current >= $this->maxAttempts) {
            $retryAfter = $this->getRetryAfter($key);
            $response->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                    'message' => 'Too many requests. Please try again later.',
                    'errors' => null,
                ],
                'meta' => null,
            ], 429);
            $response->header('Retry-After', (string) $retryAfter);
            $response->send();
            exit;
        }

        $this->incrementAttempts($key);

        return $next($request, $response);
    }

    private function resolveIdentifier(Request $request): string
    {
        $apiKey = $request->header('X-API-Key');
        if ($apiKey !== null) {
            return 'apikey:' . hash('sha256', $apiKey);
        }
        return 'ip:' . $request->ip();
    }

    private function buildKey(string $identifier): string
    {
        return $this->prefix . ':' . $identifier;
    }

    private function getAttempts(string $key): int
    {
        $db = Database::connection();
        $driver = Database::getInstance()->getDriver();

        if ($driver === 'sqlite') {
            $stmt = $db->prepare(
                'SELECT attempts FROM rate_limits WHERE key_name = :key AND expires_at > datetime(\'now\')'
            );
        } else {
            $stmt = $db->prepare(
                'SELECT attempts FROM rate_limits WHERE key_name = :key AND expires_at > NOW()'
            );
        }

        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['attempts'] : 0;
    }

    private function incrementAttempts(string $key): void
    {
        $db = Database::connection();
        $driver = Database::getInstance()->getDriver();

        $stmt = $db->prepare('SELECT id, attempts FROM rate_limits WHERE key_name = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($driver === 'sqlite') {
                $update = $db->prepare(
                    'UPDATE rate_limits SET attempts = attempts + 1 WHERE id = :id'
                );
            } else {
                $update = $db->prepare(
                    'UPDATE rate_limits SET attempts = attempts + 1 WHERE id = :id'
                );
            }
            $update->execute([':id' => $row['id']]);
        } else {
            if ($driver === 'sqlite') {
                $insert = $db->prepare(
                    'INSERT INTO rate_limits (key_name, attempts, expires_at) VALUES (:key, 1, datetime(\'now\', :decay || \' seconds\'))'
                );
            } else {
                $insert = $db->prepare(
                    'INSERT INTO rate_limits (key_name, attempts, expires_at) VALUES (:key, 1, NOW() + (:decay || \' second\')::INTERVAL)'
                );
            }
            $insert->execute([':key' => $key, ':decay' => (string)$this->decaySeconds]);
        }
    }

    private function getRetryAfter(string $key): int
    {
        $db = Database::connection();
        $driver = Database::getInstance()->getDriver();

        if ($driver === 'sqlite') {
            $stmt = $db->prepare(
                "SELECT (strftime('%s', expires_at) - strftime('%s', 'now')) AS retry FROM rate_limits WHERE key_name = :key"
            );
        } else {
            $stmt = $db->prepare(
                'SELECT EXTRACT(EPOCH FROM expires_at - NOW())::int AS retry FROM rate_limits WHERE key_name = :key'
            );
        }

        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? max(1, (int) $row['retry']) : 1;
    }
}
