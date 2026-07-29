<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\ApiService;
use PDO;

class WebhookController
{
    private ApiService $api;

    public function __construct()
    {
        $this->api = new ApiService();
    }

    private function authorizeWorkspace(int $workspaceId, int $userId): bool
    {
        $stmt = \App\Core\Database::connection()->prepare(
            'SELECT COUNT(*) FROM workspace_members WHERE workspace_id = :wid AND user_id = :uid'
        );
        $stmt->execute([':wid' => $workspaceId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $workspaceId = $_SERVER['auth_workspace_id'] ?? null;
        $userId = $_SERVER['auth_user_id'] ?? null;

        $db = Database::connection();
        $where = [];
        $bindings = [];

        if ($workspaceId !== null) {
            $where[] = 'workspace_id = :workspace_id';
            $bindings[':workspace_id'] = $workspaceId;
        } elseif ($userId !== null) {
            $where[] = 'user_id = :user_id';
            $bindings[':user_id'] = $userId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $db->prepare("SELECT * FROM webhooks {$whereClause} ORDER BY created_at DESC");
        $stmt->execute($bindings);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse($webhooks)->send();
    }

    public function store(Request $request, Response $response, array $params = []): void
    {
        $data = $request->only(['url', 'events', 'workspace_id', 'secret']);

        $validation = $this->api->validateRequest($data, [
            'url' => 'required|url',
            'events' => 'required',
        ]);

        if ($validation !== null) {
            $validation->send();
            return;
        }

        $userId = $_SERVER['auth_user_id'] ?? null;

        if ($userId === null) {
            $this->api->errorResponse('Unauthenticated.', 401, 'UNAUTHENTICATED')->send();
            return;
        }

        $events = $data['events'];
        if (is_string($events)) {
            $events = array_map('trim', explode(',', $events));
        }

        $db = Database::connection();
        $secret = $data['secret'] ?? bin2hex(random_bytes(16));

        $stmt = $db->prepare('
            INSERT INTO webhooks (user_id, workspace_id, url, events, secret, created_at, updated_at)
            VALUES (:user_id, :workspace_id, :url, :events, :secret, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':workspace_id' => $data['workspace_id'] ?? $_SERVER['auth_workspace_id'],
            ':url' => $data['url'],
            ':events' => json_encode($events),
            ':secret' => $secret,
        ]);

        $webhookId = (int) $db->lastInsertId();
        $webhookStmt = $db->prepare('SELECT * FROM webhooks WHERE id = :id');
        $webhookStmt->execute([':id' => $webhookId]);
        $webhook = $webhookStmt->fetch(PDO::FETCH_ASSOC);

        $this->api->createdResponse($webhook)->send();
    }

    public function update(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($webhook === false) {
            $this->api->errorResponse('Webhook not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $userId = (int) ($_SERVER['auth_user_id'] ?? 0);
        if ($userId === 0 || !$this->authorizeWorkspace((int) $webhook['workspace_id'], $userId)) {
            $this->api->errorResponse('Forbidden.', 403, 'FORBIDDEN')->send();
            return;
        }

        $data = $request->only(['url', 'events', 'secret']);
        $fields = [];
        $bindings = [':id' => $id];

        foreach (['url', 'secret'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[] = "{$field} = :{$field}";
                $bindings[":{$field}"] = $data[$field];
            }
        }

        if (array_key_exists('events', $data) && $data['events'] !== null) {
            $events = $data['events'];
            if (is_string($events)) {
                $events = array_map('trim', explode(',', $events));
            }
            $fields[] = 'events = :events';
            $bindings[':events'] = json_encode($events);
        }

        if (!empty($fields)) {
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $sql = 'UPDATE webhooks SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($bindings);
        }

        $webhookStmt = $db->prepare('SELECT * FROM webhooks WHERE id = :id');
        $webhookStmt->execute([':id' => $id]);
        $updated = $webhookStmt->fetch(PDO::FETCH_ASSOC);

        $this->api->successResponse($updated)->send();
    }

    public function destroy(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($webhook === false) {
            $this->api->errorResponse('Webhook not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $userId = (int) ($_SERVER['auth_user_id'] ?? 0);
        if ($userId === 0 || !$this->authorizeWorkspace((int) $webhook['workspace_id'], $userId)) {
            $this->api->errorResponse('Forbidden.', 403, 'FORBIDDEN')->send();
            return;
        }

        $db->prepare('DELETE FROM webhooks WHERE id = :id')->execute([':id' => $id]);

        $this->api->noContentResponse()->send();
    }

    public function test(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM webhooks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($webhook === false) {
            $this->api->errorResponse('Webhook not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $userId = (int) ($_SERVER['auth_user_id'] ?? 0);
        if ($userId === 0 || !$this->authorizeWorkspace((int) $webhook['workspace_id'], $userId)) {
            $this->api->errorResponse('Forbidden.', 403, 'FORBIDDEN')->send();
            return;
        }

        $payload = json_encode([
            'event' => 'test',
            'data' => [
                'message' => 'This is a test webhook payload from FORT URL Shortener.',
                'timestamp' => date('Y-m-d H:i:s'),
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $webhook['secret']);

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'User-Agent: FORT-Webhook/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->api->successResponse([
            'webhook_id' => (int) $id,
            'url' => $webhook['url'],
            'status_code' => $httpCode,
            'response' => $result !== false ? substr($result, 0, 1000) : null,
            'error' => $error ?: null,
            'sent_at' => date('Y-m-d H:i:s'),
        ])->send();
    }
}
