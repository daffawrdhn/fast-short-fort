<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class PasswordReset
{
    public ?string $email = null;
    public ?string $token = null;
    public ?string $expires_at = null;
    public ?string $created_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function create(string $email, string $token, ?string $expiresAt = null): self
    {
        if ($expiresAt === null) {
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        }

        $stmt = self::db()->prepare('
            INSERT INTO password_resets (email, token, expires_at, created_at)
            VALUES (:email, :token, :expires_at, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':email' => $email,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $reset = new self();
        $reset->email = $email;
        $reset->token = $token;
        $reset->expires_at = $expiresAt;
        return $reset;
    }

    public static function findByToken(string $token): ?self
    {
        $driver = self::db()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $dateExpr = $driver === 'pgsql' ? 'CURRENT_TIMESTAMP' : "datetime('now')";

        $stmt = self::db()->prepare("
            SELECT * FROM password_resets
            WHERE token = :token AND expires_at > {$dateExpr}
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return self::hydrate($data);
    }

    public static function deleteExpired(): int
    {
        $driver = self::db()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $dateExpr = $driver === 'pgsql' ? 'CURRENT_TIMESTAMP' : "datetime('now')";

        $stmt = self::db()->prepare("DELETE FROM password_resets WHERE expires_at < {$dateExpr}");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function deleteByEmail(string $email): bool
    {
        $stmt = self::db()->prepare('DELETE FROM password_resets WHERE email = :email');
        return $stmt->execute([':email' => $email]);
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'token' => $this->token,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $reset = new self();
        $reset->email = $data['email'] ?? null;
        $reset->token = $data['token'] ?? null;
        $reset->expires_at = $data['expires_at'] ?? null;
        $reset->created_at = $data['created_at'] ?? null;
        return $reset;
    }
}
