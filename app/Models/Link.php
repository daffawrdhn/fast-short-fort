<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Link
{
    public ?int $id = null;
    public ?int $workspace_id = null;
    public ?int $user_id = null;
    public ?string $original_url = null;
    public ?string $slug = null;
    public ?int $custom_domain_id = null;
    public ?string $password_hash = null;
    public ?string $expires_at = null;
    public ?int $click_limit = null;
    public bool $is_active = true;
    public bool $is_cloaked = false;
    public ?string $utm_source = null;
    public ?string $utm_medium = null;
    public ?string $utm_campaign = null;
    public ?string $utm_term = null;
    public ?string $utm_content = null;
    public string $link_type = 'direct';
    public ?string $deep_link_scheme = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findBySlug(string $slug, ?int $workspaceId = null): ?self
    {
        if ($workspaceId) {
            $stmt = self::db()->prepare('SELECT * FROM links WHERE slug = :slug AND workspace_id = :workspace_id');
            $stmt->execute([':slug' => $slug, ':workspace_id' => $workspaceId]);
        } else {
            $stmt = self::db()->prepare('SELECT * FROM links WHERE slug = :slug');
            $stmt->execute([':slug' => $slug]);
        }
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByWorkspace(int $workspaceId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM links WHERE workspace_id = :workspace_id ORDER BY created_at DESC');
        $stmt->execute([':workspace_id' => $workspaceId]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function findAll(): array
    {
        $stmt = self::db()->query('SELECT * FROM links ORDER BY created_at DESC');
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $data): self
    {
        $stmt = self::db()->prepare('
            INSERT INTO links (workspace_id, user_id, original_url, slug, custom_domain_id,
                               password_hash, expires_at, click_limit, is_active, is_cloaked,
                               utm_source, utm_medium, utm_campaign, utm_term, utm_content,
                               link_type, deep_link_scheme, created_at, updated_at)
            VALUES (:workspace_id, :user_id, :original_url, :slug, :custom_domain_id,
                    :password_hash, :expires_at, :click_limit, :is_active, :is_cloaked,
                    :utm_source, :utm_medium, :utm_campaign, :utm_term, :utm_content,
                    :link_type, :deep_link_scheme, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');

        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        } elseif (!empty($data['password_hash'])) {
            $passwordHash = $data['password_hash'];
        }

        $stmt->execute([
            ':workspace_id' => $data['workspace_id'],
            ':user_id' => $data['user_id'],
            ':original_url' => $data['original_url'],
            ':slug' => $data['slug'],
            ':custom_domain_id' => $data['custom_domain_id'] ?? null,
            ':password_hash' => $passwordHash,
            ':expires_at' => $data['expires_at'] ?? null,
            ':click_limit' => $data['click_limit'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':is_cloaked' => $data['is_cloaked'] ?? 0,
            ':utm_source' => $data['utm_source'] ?? null,
            ':utm_medium' => $data['utm_medium'] ?? null,
            ':utm_campaign' => $data['utm_campaign'] ?? null,
            ':utm_term' => $data['utm_term'] ?? null,
            ':utm_content' => $data['utm_content'] ?? null,
            ':link_type' => $data['link_type'] ?? 'direct',
            ':deep_link_scheme' => $data['deep_link_scheme'] ?? null,
        ]);

        $id = (int) self::db()->lastInsertId();
        return self::findById($id);
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [':id' => $this->id];

        $allowed = ['workspace_id', 'user_id', 'original_url', 'slug', 'custom_domain_id',
                     'password_hash', 'expires_at', 'click_limit', 'is_active', 'is_cloaked',
                     'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                     'link_type', 'deep_link_scheme'];

        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE links SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $stmt = self::db()->prepare('UPDATE links SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function forceDelete(): bool
    {
        self::db()->prepare('DELETE FROM link_clicks WHERE link_id = :id')->execute([':id' => $this->id]);
        $stmt = self::db()->prepare('DELETE FROM links WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function restore(): bool
    {
        $stmt = self::db()->prepare('UPDATE links SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function incrementClicks(): bool
    {
        $stmt = self::db()->prepare('UPDATE links SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function getAnalytics(): array
    {
        $stats = [];

        $stmt = self::db()->prepare('SELECT COUNT(*) AS total FROM link_clicks WHERE link_id = :link_id');
        $stmt->execute([':link_id' => $this->id]);
        $stats['total_clicks'] = (int) $stmt->fetchColumn();

        $stmt = self::db()->prepare('SELECT COUNT(DISTINCT ip_hash) AS unique_clicks FROM link_clicks WHERE link_id = :link_id');
        $stmt->execute([':link_id' => $this->id]);
        $stats['unique_clicks'] = (int) $stmt->fetchColumn();

        $stmt = self::db()->prepare('SELECT country, COUNT(*) AS count FROM link_clicks WHERE link_id = :link_id AND country IS NOT NULL GROUP BY country ORDER BY count DESC');
        $stmt->execute([':link_id' => $this->id]);
        $stats['countries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = self::db()->prepare('SELECT device_type, COUNT(*) AS count FROM link_clicks WHERE link_id = :link_id AND device_type IS NOT NULL GROUP BY device_type ORDER BY count DESC');
        $stmt->execute([':link_id' => $this->id]);
        $stats['devices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = self::db()->prepare('SELECT browser, COUNT(*) AS count FROM link_clicks WHERE link_id = :link_id AND browser IS NOT NULL GROUP BY browser ORDER BY count DESC');
        $stmt->execute([':link_id' => $this->id]);
        $stats['browsers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = self::db()->prepare('SELECT os, COUNT(*) AS count FROM link_clicks WHERE link_id = :link_id AND os IS NOT NULL GROUP BY os ORDER BY count DESC');
        $stmt->execute([':link_id' => $this->id]);
        $stats['os'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'original_url' => $this->original_url,
            'slug' => $this->slug,
            'custom_domain_id' => $this->custom_domain_id,
            'expires_at' => $this->expires_at,
            'click_limit' => $this->click_limit,
            'is_active' => $this->is_active,
            'is_cloaked' => $this->is_cloaked,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
            'link_type' => $this->link_type,
            'deep_link_scheme' => $this->deep_link_scheme,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $link = new self();
        $link->id = (int) $data['id'];
        $link->workspace_id = (int) ($data['workspace_id'] ?? 0);
        $link->user_id = (int) ($data['user_id'] ?? 0);
        $link->original_url = $data['original_url'] ?? null;
        $link->slug = $data['slug'] ?? null;
        $link->custom_domain_id = isset($data['custom_domain_id']) ? (int) $data['custom_domain_id'] : null;
        $link->password_hash = $data['password_hash'] ?? null;
        $link->expires_at = $data['expires_at'] ?? null;
        $link->click_limit = isset($data['click_limit']) ? (int) $data['click_limit'] : null;
        $link->is_active = !empty($data['is_active']);
        $link->is_cloaked = !empty($data['is_cloaked']);
        $link->utm_source = $data['utm_source'] ?? null;
        $link->utm_medium = $data['utm_medium'] ?? null;
        $link->utm_campaign = $data['utm_campaign'] ?? null;
        $link->utm_term = $data['utm_term'] ?? null;
        $link->utm_content = $data['utm_content'] ?? null;
        $link->link_type = $data['link_type'] ?? 'direct';
        $link->deep_link_scheme = $data['deep_link_scheme'] ?? null;
        $link->created_at = $data['created_at'] ?? null;
        $link->updated_at = $data['updated_at'] ?? null;
        return $link;
    }
}
