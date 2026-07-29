<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class CustomDomain
{
    public ?int $id = null;
    public ?int $workspace_id = null;
    public ?string $domain = null;
    public ?string $verified_at = null;
    public ?string $dns_record = null;
    public bool $is_active = false;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM custom_domains WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByDomain(string $domain): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM custom_domains WHERE domain = :domain');
        $stmt->execute([':domain' => $domain]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findAll(int $workspaceId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM custom_domains WHERE workspace_id = :workspace_id ORDER BY created_at DESC');
        $stmt->execute([':workspace_id' => $workspaceId]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $data): self
    {
        $stmt = self::db()->prepare('
            INSERT INTO custom_domains (workspace_id, domain, dns_record, is_active, created_at, updated_at)
            VALUES (:workspace_id, :domain, :dns_record, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':workspace_id' => $data['workspace_id'],
            ':domain' => $data['domain'],
            ':dns_record' => $data['dns_record'] ?? null,
            ':is_active' => $data['is_active'] ?? 0,
        ]);

        $id = (int) self::db()->lastInsertId();
        return self::findById($id);
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [':id' => $this->id];

        foreach (['workspace_id', 'domain', 'verified_at', 'dns_record', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE custom_domains SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $stmt = self::db()->prepare('DELETE FROM custom_domains WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function verify(): bool
    {
        $expected = $this->dns_record;
        if (!$expected) {
            return false;
        }

        $dnsRecords = @dns_get_record($this->domain, DNS_TXT);
        if (!$dnsRecords) {
            return false;
        }

        foreach ($dnsRecords as $record) {
            if (isset($record['txt']) && trim($record['txt']) === trim($expected)) {
                $this->update([
                    'verified_at' => date('Y-m-d H:i:s'),
                    'is_active' => true,
                ]);
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'domain' => $this->domain,
            'verified_at' => $this->verified_at,
            'dns_record' => $this->dns_record,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $cd = new self();
        $cd->id = (int) $data['id'];
        $cd->workspace_id = (int) ($data['workspace_id'] ?? 0);
        $cd->domain = $data['domain'] ?? null;
        $cd->verified_at = $data['verified_at'] ?? null;
        $cd->dns_record = $data['dns_record'] ?? null;
        $cd->is_active = !empty($data['is_active']);
        $cd->created_at = $data['created_at'] ?? null;
        $cd->updated_at = $data['updated_at'] ?? null;
        return $cd;
    }
}
