<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Workspace;
use App\Models\User;

class WorkspaceController
{
    private View $view;
    private Session $session;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
    }

    private function getWorkspaceId(int $userId): ?int
    {
        $ws = $this->session->get('workspace_id');
        if ($ws === null) {
            $owned = Workspace::findByOwner($userId);
            if (!empty($owned)) {
                $this->session->set('workspace_id', $owned[0]->id);
                return $owned[0]->id;
            }
        }
        return $ws ? (int) $ws : null;
    }

    public function index(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login');
            return;
        }

        $ownedWorkspaces = Workspace::findByOwner((int) $userId);

        $db = \App\Core\Database::connection();
        $stmt = $db->prepare('
            SELECT w.*, wm.role
            FROM workspaces w
            JOIN workspace_members wm ON wm.workspace_id = w.id
            WHERE wm.user_id = :user_id
        ');
        $stmt->execute([':user_id' => $userId]);
        $memberWorkspaces = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $activeId = $this->getWorkspaceId((int) $userId);
        $activeWorkspace = $activeId ? Workspace::findById($activeId) : null;
        $members = $activeWorkspace ? $activeWorkspace->members() : [];

        $userModel = User::findById((int) $userId);

        $res->status(200)->view('workspace.index', [
            'title' => 'Workspace - FORT',
            'activeNav' => 'workspace',
            'ownedWorkspaces' => $ownedWorkspaces,
            'memberWorkspaces' => $memberWorkspaces,
            'activeWorkspace' => $activeWorkspace,
            'members' => $members,
            'user' => [
                'name' => $userModel->name ?? 'User',
            ]
        ]);
    }

    public function create(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login');
            return;
        }

        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/workspace');
            return;
        }

        $name = trim($req->input('name', ''));
        if ($name === '') {
            $this->session->flash('error', 'Workspace name is required.');
            $res->redirect('/workspace');
            return;
        }

        $slug = trim($req->input('slug', ''));
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        }

        // Ensure slug uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while (Workspace::findBySlug($slug) !== null) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        try {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $slug,
                'owner_id' => $userId,
                'plan' => 'free',
            ]);
            $this->session->set('workspace_id', $workspace->id);
            $this->session->flash('success', 'Workspace created successfully.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to create workspace.');
        }

        $res->redirect('/workspace');
    }

    public function switch(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login');
            return;
        }

        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/workspace');
            return;
        }

        $workspaceId = (int) $req->input('workspace_id', 0);
        if ($workspaceId <= 0) {
            $res->redirect('/workspace');
            return;
        }

        // Verify user owns or belongs to this workspace
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM workspaces WHERE id = :id AND owner_id = :user_id
        ');
        $stmt->execute([':id' => $workspaceId, ':user_id' => $userId]);
        $isOwner = (int) $stmt->fetchColumn() > 0;

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM workspace_members WHERE workspace_id = :ws AND user_id = :user_id
        ');
        $stmt->execute([':ws' => $workspaceId, ':user_id' => $userId]);
        $isMember = (int) $stmt->fetchColumn() > 0;

        if ($isOwner || $isMember) {
            $this->session->set('workspace_id', $workspaceId);
            $this->session->flash('success', 'Switched workspace successfully.');
        } else {
            $this->session->flash('error', 'Access denied.');
        }

        $res->redirect('/workspace');
    }

    public function inviteMember(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login');
            return;
        }

        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/workspace');
            return;
        }

        $activeId = $this->getWorkspaceId((int) $userId);
        if (!$activeId) {
            $res->redirect('/workspace');
            return;
        }

        $workspace = Workspace::findById($activeId);
        if (!$workspace || $workspace->owner_id !== (int) $userId) {
            $this->session->flash('error', 'Only workspace owner can invite members.');
            $res->redirect('/workspace');
            return;
        }

        $email = trim($req->input('email', ''));
        $role = trim($req->input('role', 'viewer'));

        $invitee = User::findByEmail($email);
        if (!$invitee) {
            $this->session->flash('error', 'User with this email not found.');
            $res->redirect('/workspace');
            return;
        }

        if ($invitee->id === (int) $userId) {
            $this->session->flash('error', 'You are already the owner of this workspace.');
            $res->redirect('/workspace');
            return;
        }

        // Check if already a member
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM workspace_members WHERE workspace_id = :ws AND user_id = :user_id
        ');
        $stmt->execute([':ws' => $activeId, ':user_id' => $invitee->id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->session->flash('error', 'User is already a member of this workspace.');
            $res->redirect('/workspace');
            return;
        }

        try {
            $workspace->addMember($invitee->id, $role);
            // Auto mark joined_at for instant access
            $stmt = $db->prepare('UPDATE workspace_members SET joined_at = CURRENT_TIMESTAMP WHERE workspace_id = :ws AND user_id = :user_id');
            $stmt->execute([':ws' => $activeId, ':user_id' => $invitee->id]);

            $this->session->flash('success', "Invited {$invitee->name} successfully.");
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to add member.');
        }

        $res->redirect('/workspace');
    }

    public function removeMember(Request $req, Response $res): void
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            $res->redirect('/login');
            return;
        }

        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/workspace');
            return;
        }

        $activeId = $this->getWorkspaceId((int) $userId);
        if (!$activeId) {
            $res->redirect('/workspace');
            return;
        }

        $workspace = Workspace::findById($activeId);
        if (!$workspace || $workspace->owner_id !== (int) $userId) {
            $this->session->flash('error', 'Only workspace owner can manage members.');
            $res->redirect('/workspace');
            return;
        }

        $memberId = (int) $req->input('member_id', 0);
        if ($memberId <= 0) {
            $res->redirect('/workspace');
            return;
        }

        try {
            $workspace->removeMember($memberId);
            $this->session->flash('success', 'Member removed successfully.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to remove member.');
        }

        $res->redirect('/workspace');
    }
}
