<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Webhook
{
    public ?int $id = null;
    public ?int $workspace_id = null;
    public ?string $url = null;
    public ?string $events = null;
    public ?string $secret = null;
    public bool $is_active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function findById(int $id): ?self
    {
        $stmt = self::db()->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? self::hydrate($data) : null;
    }

    public static function findByEvent(string $event, int $workspaceId): array
    {
        $stmt = self::db()->prepare("
            SELECT * FROM webhooks
            WHERE workspace_id = :workspace_id
              AND is_active = 1
              AND (events LIKE :event1 OR events LIKE :event2 OR events = :event3)
        ");
        $stmt->execute([
            ':workspace_id' => $workspaceId,
            ':event1' => "{$event},%",
            ':event2' => "%,{$event}",
            ':event3' => $event,
        ]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function findAll(int $workspaceId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM webhooks WHERE workspace_id = :workspace_id ORDER BY created_at DESC');
        $stmt->execute([':workspace_id' => $workspaceId]);
        return array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(array $data): self
    {
        $secret = $data['secret'] ?? bin2hex(random_bytes(32));

        $stmt = self::db()->prepare('
            INSERT INTO webhooks (workspace_id, url, events, secret, is_active, created_at, updated_at)
            VALUES (:workspace_id, :url, :events, :secret, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':workspace_id' => $data['workspace_id'],
            ':url' => $data['url'],
            ':events' => $data['events'],
            ':secret' => $secret,
            ':is_active' => $data['is_active'] ?? 1,
        ]);

        $id = (int) self::db()->lastInsertId();
        return self::findById($id);
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [':id' => $this->id];

        foreach (['workspace_id', 'url', 'events', 'secret', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE webhooks SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = self::db()->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(): bool
    {
        $stmt = self::db()->prepare('DELETE FROM webhooks WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function trigger(string $event, array $payload): bool
    {
        $eventsList = array_map('trim', explode(',', $this->events));

        if (!in_array($event, $eventsList, true)) {
            return false;
        }

        $body = json_encode([
            'event' => $event,
            'payload' => $payload,
            'timestamp' => time(),
        ]);

        $signature = hash_hmac('sha256', $body, $this->secret);

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "X-Webhook-Signature: {$signature}",
                'User-Agent: FORT-Webhook/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $wh = new self();
        $wh->id = (int) $data['id'];
        $wh->workspace_id = (int) ($data['workspace_id'] ?? 0);
        $wh->url = $data['url'] ?? null;
        $wh->events = $data['events'] ?? null;
        $wh->secret = $data['secret'] ?? null;
        $wh->is_active = !empty($data['is_active']);
        $wh->created_at = $data['created_at'] ?? null;
        $wh->updated_at = $data['updated_at'] ?? null;
        return $wh;
    }
}
