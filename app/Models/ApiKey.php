<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ApiKey
{
    public ?int $id = null;
    public ?int $user_id = null;
    public ?int $workspace_id = null;
    public ?string $key_hash = null;
    public ?string $name = null;
    public int $rate_limit = 60;
    public ?string $last_used_at = null;
    public ?string $expires_at = null;
    public ?string $revoked_at = null;
    public ?string $created_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function generate(array $data): array
    {
        $rawKey = bin2hex(random_bytes(32));
        $keyHash = self::hash($rawKey);

        $stmt = self::db()->prepare('
            INSERT INTO api_keys (user_id, workspace_id, key_hash, name, rate_limit, expires_at, created_at)
            VALUES (:user_id, :workspace_id, :key_hash, :name, :rate_limit, :expires_at, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':workspace_id' => $data['workspace_id'],
            ':key_hash' => $keyHash,
            ':name' => $data['name'] ?? null,
            ':rate_limit' => $data['rate_limit'] ?? 60,
            ':expires_at' => $data['expires_at'] ?? null,
        ]);

        $id = (int) self::db()->lastInsertId();

        return [
            'id' => $id,
            'raw_key' => $rawKey,
            'api_key' => self::findById($id),
        ];
    }

    public static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function findByKey(string $rawKey): ?self
    {
        $hash = self::hash($rawKey);
        $stmt = self::db()->prepare('SELECT * FROM api_keys WHERE key_hash = :key_hash');
        $stmt->execute([':key_hash' => $hash]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM api_keys WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByWorkspace(int $workspaceId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM api_keys WHERE workspace_id = :workspace_id ORDER BY created_at DESC');
        $stmt->execute([':workspace_id' => $workspaceId]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function revoke(): bool
    {
        $stmt = self::db()->prepare('UPDATE api_keys SET revoked_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function updateRateLimit(int $limit): bool
    {
        $stmt = self::db()->prepare('UPDATE api_keys SET rate_limit = :rate_limit WHERE id = :id');
        return $stmt->execute([':id' => $this->id, ':rate_limit' => $limit]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'rate_limit' => $this->rate_limit,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $key = new self();
        $key->id = (int) $data['id'];
        $key->user_id = (int) ($data['user_id'] ?? 0);
        $key->workspace_id = (int) ($data['workspace_id'] ?? 0);
        $key->key_hash = $data['key_hash'] ?? null;
        $key->name = $data['name'] ?? null;
        $key->rate_limit = (int) ($data['rate_limit'] ?? 60);
        $key->last_used_at = $data['last_used_at'] ?? null;
        $key->expires_at = $data['expires_at'] ?? null;
        $key->revoked_at = $data['revoked_at'] ?? null;
        $key->created_at = $data['created_at'] ?? null;
        return $key;
    }
}
