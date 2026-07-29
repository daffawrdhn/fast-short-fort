<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Workspace
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?int $owner_id = null;
    public string $plan = 'free';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findBySlug(string $slug): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM workspaces WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByOwner(int $ownerId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM workspaces WHERE owner_id = :owner_id ORDER BY created_at DESC');
        $stmt->execute([':owner_id' => $ownerId]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function findAll(): array
    {
        $stmt = self::db()->query('SELECT * FROM workspaces ORDER BY created_at DESC');
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $data): self
    {
        $stmt = self::db()->prepare('
            INSERT INTO workspaces (name, slug, owner_id, plan, created_at, updated_at)
            VALUES (:name, :slug, :owner_id, :plan, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':owner_id' => $data['owner_id'],
            ':plan' => $data['plan'] ?? 'free',
        ]);

        $id = (int) self::db()->lastInsertId();
        return self::findById($id);
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [':id' => $this->id];

        foreach (['name', 'slug', 'owner_id', 'plan'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE workspaces SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $stmt = self::db()->prepare('DELETE FROM workspaces WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function members(): array
    {
        $stmt = self::db()->prepare('
            SELECT u.*, wm.role, wm.invited_at, wm.joined_at
            FROM workspace_members wm
            JOIN users u ON u.id = wm.user_id
            WHERE wm.workspace_id = :workspace_id
            ORDER BY wm.joined_at ASC
        ');
        $stmt->execute([':workspace_id' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addMember(int $userId, string $role = 'viewer'): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO workspace_members (workspace_id, user_id, role, invited_at)
            VALUES (:workspace_id, :user_id, :role, CURRENT_TIMESTAMP)
        ');
        return $stmt->execute([
            ':workspace_id' => $this->id,
            ':user_id' => $userId,
            ':role' => $role,
        ]);
    }

    public function removeMember(int $userId): bool
    {
        $stmt = self::db()->prepare('
            DELETE FROM workspace_members
            WHERE workspace_id = :workspace_id AND user_id = :user_id
        ');
        return $stmt->execute([
            ':workspace_id' => $this->id,
            ':user_id' => $userId,
        ]);
    }

    public function updateMemberRole(int $userId, string $role): bool
    {
        $stmt = self::db()->prepare('
            UPDATE workspace_members
            SET role = :role
            WHERE workspace_id = :workspace_id AND user_id = :user_id
        ');
        return $stmt->execute([
            ':role' => $role,
            ':workspace_id' => $this->id,
            ':user_id' => $userId,
        ]);
    }

    public function links(): array
    {
        $stmt = self::db()->prepare('
            SELECT l.*, cd.domain AS custom_domain
            FROM links l
            LEFT JOIN custom_domains cd ON cd.id = l.custom_domain_id
            WHERE l.workspace_id = :workspace_id
            ORDER BY l.created_at DESC
        ');
        $stmt->execute([':workspace_id' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_id' => $this->owner_id,
            'plan' => $this->plan,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $ws = new self();
        $ws->id = (int) $data['id'];
        $ws->name = $data['name'] ?? null;
        $ws->slug = $data['slug'] ?? null;
        $ws->owner_id = (int) ($data['owner_id'] ?? 0);
        $ws->plan = $data['plan'] ?? 'free';
        $ws->created_at = $data['created_at'] ?? null;
        $ws->updated_at = $data['updated_at'] ?? null;
        return $ws;
    }
}
