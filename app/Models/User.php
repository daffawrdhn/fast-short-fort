<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $password_hash = null;
    public ?string $two_fa_secret = null;
    public bool $two_fa_enabled = false;
    public ?string $email_verified_at = null;
    public ?string $remember_token = null;
    public ?string $email_verification_token = null;
    public bool $is_admin = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findAll(): array
    {
        $stmt = self::db()->query('SELECT * FROM users ORDER BY created_at DESC');
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $data): self
    {
        // Use Hash::make() which applies PASSWORD_ARGON2ID — consistent with AuthService
        $passwordHash = \App\Core\Hash::make($data['password']);
        $stmt = self::db()->prepare('
            INSERT INTO users (name, email, password_hash, created_at, updated_at)
            VALUES (:name, :email, :password_hash, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':name' => $data['name'] ?? '',
            ':email' => $data['email'],
            ':password_hash' => $passwordHash,
        ]);

        $id = (int) self::db()->lastInsertId();
        return self::findById($id);
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [':id' => $this->id];

        foreach (
            ['name', 'email', 'password_hash', 'two_fa_secret', 'two_fa_enabled',
                      'email_verified_at', 'remember_token', 'email_verification_token', 'is_admin'] as $field
        ) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $stmt = self::db()->prepare('DELETE FROM users WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function verifyEmail(): bool
    {
        return $this->update([
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function setTwoFA(string $secret): bool
    {
        return $this->update([
            'two_fa_secret' => $secret,
            'two_fa_enabled' => true,
        ]);
    }

    public function disableTwoFA(): bool
    {
        return $this->update([
            'two_fa_secret' => null,
            'two_fa_enabled' => false,
        ]);
    }

    public function workspaces(): array
    {
        $stmt = self::db()->prepare('
            SELECT w.*, wm.role FROM workspaces w
            JOIN workspace_members wm ON wm.workspace_id = w.id
            WHERE wm.user_id = :user_id
            UNION
            SELECT w.*, \'owner\' AS role FROM workspaces w
            WHERE w.owner_id = :owner_id
            ORDER BY created_at DESC
        ');
        $stmt->execute([':user_id' => $this->id, ':owner_id' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'two_fa_enabled' => $this->two_fa_enabled,
            'email_verified_at' => $this->email_verified_at,
            'is_admin' => $this->is_admin,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $user = new self();
        $user->id = (int) $data['id'];
        $user->name = $data['name'] ?? null;
        $user->email = $data['email'] ?? null;
        $user->password_hash = $data['password_hash'] ?? null;
        $user->two_fa_secret = $data['two_fa_secret'] ?? null;
        $user->two_fa_enabled = !empty($data['two_fa_enabled']);
        $user->email_verified_at = $data['email_verified_at'] ?? null;
        $user->remember_token = $data['remember_token'] ?? null;
        $user->email_verification_token = $data['email_verification_token'] ?? null;
        $user->is_admin = !empty($data['is_admin']);
        $user->created_at = $data['created_at'] ?? null;
        $user->updated_at = $data['updated_at'] ?? null;
        return $user;
    }
}
