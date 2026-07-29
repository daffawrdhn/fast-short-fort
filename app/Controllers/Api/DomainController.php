<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\ApiService;
use PDO;

class DomainController
{
    private ApiService $api;

    public function __construct()
    {
        $this->api = new ApiService();
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $userId = $_SERVER['auth_user_id'] ?? null;
        $workspaceId = $_SERVER['auth_workspace_id'] ?? null;

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
        $stmt = $db->prepare("SELECT * FROM custom_domains {$whereClause} ORDER BY created_at DESC");
        $stmt->execute($bindings);
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse($domains)->send();
    }

    public function store(Request $request, Response $response, array $params = []): void
    {
        $data = $request->only(['domain', 'workspace_id']);

        $validation = $this->api->validateRequest($data, [
            'domain' => 'required|max:255',
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

        $domain = strtolower(trim($data['domain']));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = rtrim($domain, '/');

        $db = Database::connection();

        $existingStmt = $db->prepare('SELECT COUNT(*) FROM custom_domains WHERE domain = :domain');
        $existingStmt->execute([':domain' => $domain]);
        if ((int) $existingStmt->fetchColumn() > 0) {
            $this->api->errorResponse('This domain has already been added.', 409, 'DOMAIN_EXISTS')->send();
            return;
        }

        $stmt = $db->prepare('
            INSERT INTO custom_domains (user_id, workspace_id, domain, verified, created_at, updated_at)
            VALUES (:user_id, :workspace_id, :domain, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':workspace_id' => $data['workspace_id'] ?? $_SERVER['auth_workspace_id'],
            ':domain' => $domain,
        ]);

        $domainId = (int) $db->lastInsertId();
        $domainStmt = $db->prepare('SELECT * FROM custom_domains WHERE id = :id');
        $domainStmt->execute([':id' => $domainId]);
        $created = $domainStmt->fetch(PDO::FETCH_ASSOC);

        $this->api->createdResponse($created)->send();
    }

    public function verify(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM custom_domains WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($domain === false) {
            $this->api->errorResponse('Domain not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $dnsRecord = $this->checkDnsTxtRecord($domain['domain']);

        if ($dnsRecord) {
            $db->prepare('UPDATE custom_domains SET verified = 1, verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':id' => $id]);
            $this->api->successResponse([
                'domain' => $domain['domain'],
                'verified' => true,
                'message' => 'Domain verified successfully.',
            ])->send();
        } else {
            $this->api->successResponse([
                'domain' => $domain['domain'],
                'verified' => false,
                'message' => 'DNS verification failed. Add a TXT record with the verification code.',
                'verification_code' => 'fort-verify-' . hash('md5', $domain['domain']),
            ])->send();
        }
    }

    public function destroy(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM custom_domains WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($domain === false) {
            $this->api->errorResponse('Domain not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $db->prepare('DELETE FROM custom_domains WHERE id = :id')->execute([':id' => $id]);

        $this->api->noContentResponse()->send();
    }

    private function checkDnsTxtRecord(string $domain): bool
    {
        $records = @dns_get_record($domain, DNS_TXT);
        if ($records === false) {
            return false;
        }

        $expected = 'fort-verify-' . hash('md5', $domain);

        foreach ($records as $record) {
            if (isset($record['txt']) && str_contains($record['txt'], $expected)) {
                return true;
            }
        }

        return false;
    }
}
