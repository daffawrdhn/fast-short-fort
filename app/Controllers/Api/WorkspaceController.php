<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Services\ApiService;
use PDO;

class WorkspaceController
{
    private ApiService $api;

    public function __construct()
    {
        $this->api = new ApiService();
    }

    public function index(Request $request, Response $response, array $params = []): void
    {
        $userId = $_SERVER['auth_user_id'] ?? null;

        if ($userId === null) {
            $this->api->errorResponse('Unauthenticated.', 401, 'UNAUTHENTICATED')->send();
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare('
            SELECT w.*, wm.role AS member_role FROM workspaces w
            JOIN workspace_members wm ON wm.workspace_id = w.id
            WHERE wm.user_id = :user_id
            UNION
            SELECT w.*, \'owner\' AS member_role FROM workspaces w
            WHERE w.owner_id = :owner_id
            ORDER BY created_at DESC
        ');
        $stmt->execute([':user_id' => $userId, ':owner_id' => $userId]);
        $workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse($workspaces)->send();
    }

    public function show(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $membersStmt = $db->prepare('
            SELECT u.id, u.name, u.email, wm.role, wm.joined_at
            FROM workspace_members wm
            JOIN users u ON u.id = wm.user_id
            WHERE wm.workspace_id = :workspace_id
            ORDER BY wm.joined_at ASC
        ');
        $membersStmt->execute([':workspace_id' => $id]);
        $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

        $workspace['members'] = $members;

        $this->api->successResponse($workspace)->send();
    }

    public function store(Request $request, Response $response, array $params = []): void
    {
        $data = $request->only(['name', 'slug', 'description']);

        $validation = $this->api->validateRequest($data, [
            'name' => 'required|min:2|max:255',
            'slug' => 'slug|unique:workspaces,slug|max:255',
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

        $db = Database::connection();
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);

        $stmt = $db->prepare('
            INSERT INTO workspaces (owner_id, name, slug, description, created_at, updated_at)
            VALUES (:owner_id, :name, :slug, :description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':owner_id' => $userId,
            ':name' => $data['name'],
            ':slug' => $slug,
            ':description' => $data['description'] ?? null,
        ]);

        $workspaceId = (int) $db->lastInsertId();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $workspaceId]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->api->createdResponse($workspace)->send();
    }

    public function update(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $data = $request->only(['name', 'slug', 'description']);
        $fields = [];
        $bindings = [':id' => $id];

        foreach (['name', 'slug', 'description'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[] = "{$field} = :{$field}";
                $bindings[":{$field}"] = $data[$field];
            }
        }

        if (!empty($fields)) {
            $fields[] = 'updated_at = CURRENT_TIMESTAMP';
            $sql = 'UPDATE workspaces SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($bindings);
        }

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->api->successResponse($updated)->send();
    }

    public function destroy(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $db->prepare('DELETE FROM workspace_members WHERE workspace_id = :id')->execute([':id' => $id]);
        $db->prepare('UPDATE links SET workspace_id = NULL, deleted_at = CURRENT_TIMESTAMP WHERE workspace_id = :id')->execute([':id' => $id]);
        $db->prepare('DELETE FROM workspaces WHERE id = :id')->execute([':id' => $id]);

        $this->api->noContentResponse()->send();
    }

    public function members(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $membersStmt = $db->prepare('
            SELECT u.id, u.name, u.email, wm.role, wm.joined_at
            FROM workspace_members wm
            JOIN users u ON u.id = wm.user_id
            WHERE wm.workspace_id = :workspace_id
            ORDER BY wm.joined_at ASC
        ');
        $membersStmt->execute([':workspace_id' => $id]);
        $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->api->successResponse($members)->send();
    }

    public function addMember(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $data = $request->only(['email', 'role']);

        $validation = $this->api->validateRequest($data, [
            'email' => 'required|email',
            'role' => 'max:50',
        ]);

        if ($validation !== null) {
            $validation->send();
            return;
        }

        $userStmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $userStmt->execute([':email' => $data['email']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            $this->api->errorResponse('User not found with that email.', 404, 'USER_NOT_FOUND')->send();
            return;
        }

        $existingStmt = $db->prepare('SELECT COUNT(*) FROM workspace_members WHERE workspace_id = :workspace_id AND user_id = :user_id');
        $existingStmt->execute([':workspace_id' => $id, ':user_id' => $user['id']]);
        if ((int) $existingStmt->fetchColumn() > 0) {
            $this->api->errorResponse('User is already a member of this workspace.', 409, 'ALREADY_MEMBER')->send();
            return;
        }

        $role = $data['role'] ?? 'member';
        $insertStmt = $db->prepare('
            INSERT INTO workspace_members (workspace_id, user_id, role, joined_at)
            VALUES (:workspace_id, :user_id, :role, CURRENT_TIMESTAMP)
        ');
        $insertStmt->execute([
            ':workspace_id' => $id,
            ':user_id' => $user['id'],
            ':role' => $role,
        ]);

        $this->api->createdResponse([
            'workspace_id' => (int) $id,
            'user_id' => (int) $user['id'],
            'role' => $role,
        ])->send();
    }

    public function removeMember(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $userId = $params['userId'] ?? null;

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $db->prepare('DELETE FROM workspace_members WHERE workspace_id = :workspace_id AND user_id = :user_id')
            ->execute([':workspace_id' => $id, ':user_id' => $userId]);

        $this->api->noContentResponse()->send();
    }

    public function updateMemberRole(Request $request, Response $response, array $params = []): void
    {
        $id = $params['id'] ?? null;
        $userId = $params['userId'] ?? null;

        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM workspaces WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $workspace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            $this->api->errorResponse('Workspace not found.', 404, 'NOT_FOUND')->send();
            return;
        }

        $role = $request->input('role', 'member');

        $updateStmt = $db->prepare('UPDATE workspace_members SET role = :role WHERE workspace_id = :workspace_id AND user_id = :user_id');
        $updateStmt->execute([':role' => $role, ':workspace_id' => $id, ':user_id' => $userId]);

        $this->api->successResponse([
            'workspace_id' => (int) $id,
            'user_id' => (int) $userId,
            'role' => $role,
        ])->send();
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $name), '-'));
        if ($slug === '') {
            $slug = 'workspace-' . bin2hex(random_bytes(4));
        }
        return $slug;
    }
}
