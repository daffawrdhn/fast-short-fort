<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class AuditLog
{
    public ?int $id = null;
    public ?int $user_id = null;
    public ?int $workspace_id = null;
    public ?string $action = null;
    public ?string $meta = null;
    public ?string $ip_address = null;
    public ?string $user_agent = null;
    public ?string $created_at = null;

    private static function db(): PDO
    {
        return Database::connection();
    }

    public static function log(string $action, ?int $userId, ?int $workspaceId, ?array $meta, string $ip, ?string $userAgent): self
    {
        $stmt = self::db()->prepare('
            INSERT INTO audit_logs (user_id, workspace_id, action, meta, ip_address, user_agent, created_at)
            VALUES (:user_id, :workspace_id, :action, :meta, :ip_address, :user_agent, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':workspace_id' => $workspaceId,
            ':action' => $action,
            ':meta' => $meta ? json_encode($meta) : null,
            ':ip_address' => $ip,
            ':user_agent' => $userAgent,
        ]);

        $id = (int) self::db()->lastInsertId();
        $stmt = self::db()->prepare('SELECT * FROM audit_logs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return self::hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public static function getLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['workspace_id'])) {
            $conditions[] = 'workspace_id = :workspace_id';
            $params[':workspace_id'] = $filters['workspace_id'];
        }

        if (!empty($filters['action'])) {
            $conditions[] = 'action = :action';
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $offset = ($page - 1) * $perPage;

        $countStmt = self::db()->prepare("SELECT COUNT(*) FROM audit_logs {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = self::db()->prepare("
            SELECT * FROM audit_logs {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        $logs = array_map(fn($data) => self::hydrate($data), $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public static function purgeOld(int $days = 365): int
    {
        $stmt = self::db()->prepare('
            DELETE FROM audit_logs WHERE created_at < datetime(\'now\', :interval)
        ');
        $stmt->execute([':interval' => "-{$days} days"]);
        return $stmt->rowCount();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'workspace_id' => $this->workspace_id,
            'action' => $this->action,
            'meta' => $this->meta ? json_decode($this->meta, true) : null,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }

    private static function hydrate(array $data): self
    {
        $log = new self();
        $log->id = (int) $data['id'];
        $log->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $log->workspace_id = isset($data['workspace_id']) ? (int) $data['workspace_id'] : null;
        $log->action = $data['action'] ?? null;
        $log->meta = $data['meta'] ?? null;
        $log->ip_address = $data['ip_address'] ?? null;
        $log->user_agent = $data['user_agent'] ?? null;
        $log->created_at = $data['created_at'] ?? null;
        return $log;
    }
}
