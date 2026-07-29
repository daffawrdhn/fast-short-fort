<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Database;
use App\Core\Env;
use App\Core\Hash;
use App\Core\Session;
use App\Models\User;
use App\Models\Workspace;
use PDO;

class AdminController
{
    private View $view;
    private Session $session;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::connection();
        }
        return $this->db;
    }

    private function adminLayout(Response $res, string $title, string $activeNav, string $content): void
    {
        $flash = [];
        foreach (['success', 'error', 'info', 'warning'] as $type) {
            if ($this->session->hasFlash($type)) {
                $flash[$type] = $this->session->flash($type);
            }
        }
        $user = [
            'id'       => $_SESSION['user_id'] ?? null,
            'name'     => $_SESSION['user_name'] ?? 'Admin',
            'email'    => $_SESSION['user_email'] ?? '',
            'is_admin' => $_SESSION['user_is_admin'] ?? false,
        ];
        $res->view('layouts.admin', [
            'title' => $title . ' - Admin - FORT',
            'activeNav' => $activeNav,
            'content' => $content,
            'flash' => $flash,
            'user' => $user,
            'isAdmin' => true,
            'csrf' => $this->session->csrfToken(),
        ]);
    }

    public function index(Request $req, Response $res): void
    {
        $stats = [
            'total_users' => 0,
            'total_workspaces' => 0,
            'total_links' => 0,
            'total_clicks' => 0,
        ];
        try {
            $stats['total_users'] = (int) $this->db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $stats['total_workspaces'] = (int) $this->db()->query('SELECT COUNT(*) FROM workspaces')->fetchColumn();
            $stats['total_links'] = (int) $this->db()->query('SELECT COUNT(*) FROM links')->fetchColumn();
            $stats['total_clicks'] = (int) $this->db()->query('SELECT COUNT(*) FROM link_clicks')->fetchColumn();
        } catch (\Throwable $e) {
            $stats = [];
        }

        $dbStatus = $this->checkDb();
        $diskUsage = $this->getDiskUsage();
        $extensions = $this->checkExtensions();

        $recentUsers = [];
        try {
            $stmt = $this->db()->query('SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5');
            $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $html = $this->view->renderString('admin.index', [
            'stats' => $stats,
            'dbStatus' => $dbStatus,
            'diskUsage' => $diskUsage,
            'extensions' => $extensions,
            'recentUsers' => $recentUsers,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Dashboard', 'dashboard', $html);
    }

    public function users(Request $req, Response $res): void
    {
        $search = trim($req->query('search', ''));
        $page = max(1, (int) $req->query('page', 1));
        $allowedLimits = [10, 25, 50, 100];
        $perPage = (int) $req->query('limit', 10);
        if (!in_array($perPage, $allowedLimits, true)) $perPage = 10;
        $offset = ($page - 1) * $perPage;

        try {
            $countQuery = 'SELECT COUNT(*) FROM users';
            $query = 'SELECT * FROM users';
            $params = [];
            if ($search !== '') {
                $where = ' WHERE name LIKE :search OR email LIKE :search2';
                $countQuery .= $where;
                $query .= $where;
                $params[':search'] = "%{$search}%";
                $params[':search2'] = "%{$search}%";
            }
            $query .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

            $total = (int) $this->db()->prepare($countQuery)->execute($params) ? 0 : 0;
            if (empty($params)) {
                $total = (int) $this->db()->query($countQuery)->fetchColumn();
            } else {
                $stmt = $this->db()->prepare($countQuery);
                $stmt->execute($params);
                $total = (int) $stmt->fetchColumn();
            }

            $stmt = $this->db()->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = (int) ceil($total / $perPage);
        } catch (\Throwable $e) {
            $users = [];
            $total = 0;
            $totalPages = 1;
        }

        $html = $this->view->renderString('admin.users', [
            'users' => $users,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Users', 'users', $html);
    }

    public function createUser(Request $req, Response $res): void
    {
        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/users');
                return;
            }

            $name = trim($req->input('name', ''));
            $email = trim($req->input('email', ''));
            $password = $req->input('password', '');
            $isAdmin = !empty($req->input('is_admin'));

            if ($name === '' || $email === '' || $password === '') {
                $this->session->flash('error', 'Name, email, and password are required.');
                $res->redirect('/admin/users');
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->session->flash('error', 'Invalid email address.');
                $res->redirect('/admin/users');
                return;
            }

            if (strlen($password) < 8) {
                $this->session->flash('error', 'Password must be at least 8 characters.');
                $res->redirect('/admin/users');
                return;
            }

            try {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);
                if ($isAdmin) {
                    $user->update(['is_admin' => true]);
                }
                Logger::info('Admin created user', ['user_id' => $user->id, 'email' => $email]);
                $this->session->flash('success', 'User created successfully.');
            } catch (\Throwable $e) {
                $this->session->flash('error', 'Failed to create user: ' . $e->getMessage());
            }
        }
        $res->redirect('/admin/users');
    }

    public function editUser(Request $req, Response $res, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $user = User::findById($id);
        if ($user === null) {
            $this->session->flash('error', 'User not found.');
            $res->redirect('/admin/users');
            return;
        }

        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/users');
                return;
            }

            $name = trim($req->input('name', ''));
            $email = trim($req->input('email', ''));
            $password = $req->input('password', '');
            $isAdmin = !empty($req->input('is_admin'));

            if ($name !== '') {
                $user->update(['name' => $name]);
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $user->update(['email' => $email]);
            }
            if ($password !== '') {
                if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                    $this->session->flash('error', 'Password must be at least 8 characters with uppercase, lowercase, and a number.');
                    $res->redirect('/admin/users');
                    return;
                }
                $user->update(['password_hash' => Hash::make($password)]);
            }
            $user->update(['is_admin' => $isAdmin ? 1 : 0]);
            $this->session->flash('success', 'User updated successfully.');
        }
        $res->redirect('/admin/users');
    }

    public function deleteUser(Request $req, Response $res, array $params): void
    {
        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/users');
                return;
            }
            $id = (int) ($params['id'] ?? 0);
            if ($id === (int) ($_SESSION['user']['id'] ?? 0)) {
                $this->session->flash('error', 'You cannot delete your own account.');
                $res->redirect('/admin/users');
                return;
            }
            $user = User::findById($id);
            if ($user !== null) {
                $user->delete();
                Logger::warning('Admin deleted user', ['user_id' => $id, 'email' => $user->email]);
                $this->session->flash('success', 'User deleted successfully.');
            } else {
                $this->session->flash('error', 'User not found.');
            }
        }
        $res->redirect('/admin/users');
    }

    public function impersonate(Request $req, Response $res, array $params): void
    {
        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/users');
                return;
            }
            $id = (int) ($params['id'] ?? 0);
            $user = User::findById($id);
            if ($user === null) {
                $this->session->flash('error', 'User not found.');
                $res->redirect('/admin/users');
                return;
            }
            $_SESSION['user'] = $user->toArray();
            $_SESSION['impersonating'] = true;
            $this->session->regenerate();
            $this->session->flash('info', 'You are now impersonating ' . $user->email);
            $res->redirect('/dashboard');
        }
    }

    public function workspaces(Request $req, Response $res): void
    {
        $search = trim($req->query('search', ''));
        $page = max(1, (int) $req->query('page', 1));
        $allowedLimits = [10, 25, 50, 100];
        $perPage = (int) $req->query('limit', 10);
        if (!in_array($perPage, $allowedLimits, true)) $perPage = 10;
        $offset = ($page - 1) * $perPage;

        try {
            $countQuery = 'SELECT COUNT(*) FROM workspaces';
            $query = 'SELECT w.*, u.name AS owner_name, u.email AS owner_email FROM workspaces w LEFT JOIN users u ON u.id = w.owner_id';
            $params = [];
            if ($search !== '') {
                $where = ' WHERE w.name LIKE :search OR w.slug LIKE :search2';
                $countQuery .= $where;
                $query .= $where;
                $params[':search'] = "%{$search}%";
                $params[':search2'] = "%{$search}%";
            }
            $query .= ' ORDER BY w.created_at DESC LIMIT :limit OFFSET :offset';

            if (empty($params)) {
                $total = (int) $this->db()->query($countQuery)->fetchColumn();
            } else {
                $stmt = $this->db()->prepare($countQuery);
                $stmt->execute($params);
                $total = (int) $stmt->fetchColumn();
            }

            $stmt = $this->db()->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $workspaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalPages = (int) ceil($total / $perPage);
        } catch (\Throwable $e) {
            $workspaces = [];
            $total = 0;
            $totalPages = 1;
        }

        $html = $this->view->renderString('admin.workspaces', [
            'workspaces' => $workspaces,
            'search' => $search,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Workspaces', 'workspaces', $html);
    }

    public function editWorkspace(Request $req, Response $res, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $workspace = Workspace::findById($id);
        if ($workspace === null) {
            $this->session->flash('error', 'Workspace not found.');
            $res->redirect('/admin/workspaces');
            return;
        }

        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/workspaces');
                return;
            }
            $plan = $req->input('plan', 'free');
            $workspace->update(['plan' => $plan]);
            $this->session->flash('success', 'Workspace plan updated.');
        }
        $res->redirect('/admin/workspaces');
    }

    public function deleteWorkspace(Request $req, Response $res, array $params): void
    {
        if ($req->method() === 'POST') {
            if (!$req->validateCsrf()) {
                $this->session->flash('error', 'Invalid CSRF token.');
                $res->redirect('/admin/workspaces');
                return;
            }
            $id = (int) ($params['id'] ?? 0);
            $workspace = Workspace::findById($id);
            if ($workspace !== null) {
                $workspace->delete();
                $this->session->flash('success', 'Workspace deleted successfully.');
            } else {
                $this->session->flash('error', 'Workspace not found.');
            }
        }
        $res->redirect('/admin/workspaces');
    }

    public function settings(Request $req, Response $res): void
    {
        $config = [
            'APP_NAME' => Env::get('APP_NAME', 'FORT (Fast Short)'),
            'APP_URL' => Env::get('APP_URL', ''),
            'DB_DRIVER' => Env::get('DB_DRIVER', 'sqlite'),
            'RATE_LIMIT_GLOBAL' => Env::get('RATE_LIMIT_GLOBAL', '100'),
            'RATE_LIMIT_LOGIN' => Env::get('RATE_LIMIT_LOGIN', '10'),
            'RATE_LIMIT_CREATE_LINK' => Env::get('RATE_LIMIT_CREATE_LINK', '30'),
            'RATE_LIMIT_API' => Env::get('RATE_LIMIT_API', '60'),
            'REGISTRATION_ENABLED' => Env::get('REGISTRATION_ENABLED', 'true'),
            'EMAIL_VERIFICATION_REQUIRED' => Env::get('EMAIL_VERIFICATION_REQUIRED', 'false'),
            'DEFAULT_USER_PLAN' => Env::get('DEFAULT_USER_PLAN', 'free'),
        ];

        $html = $this->view->renderString('admin.settings', [
            'config' => $config,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Settings', 'settings', $html);
    }

    public function updateSettings(Request $req, Response $res): void
    {
        Logger::info('Admin updated settings', ['admin_id' => $_SESSION['user_id'] ?? null]);
        $this->session->flash('success', 'Settings updated successfully.');
        $res->redirect('/admin/settings')->send();
    }

    public function health(Request $req, Response $res): void
    {
        $phpVersion = PHP_VERSION;
        $phpOk = version_compare($phpVersion, '8.2.0', '>=');

        $requiredExtensions = ['pdo', 'pdo_pgsql', 'pdo_sqlite', 'json', 'mbstring', 'openssl', 'gd', 'curl'];
        $extensions = [];
        foreach ($requiredExtensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }
        $extensions['imagick'] = extension_loaded('imagick');

        $directories = [
            'storage/' => is_writable(dirname(__DIR__, 3) . '/storage'),
            'storage/logs/' => is_writable(dirname(__DIR__, 3) . '/storage/logs'),
            'storage/cache/' => is_writable(dirname(__DIR__, 3) . '/storage/cache'),
        ];

        $dbStatus = $this->checkDb();

        $diskFree = disk_free_space(dirname(__DIR__, 3));
        $diskTotal = disk_total_space(dirname(__DIR__, 3));
        $diskPercent = $diskTotal > 0 ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) : 0;

        $html = $this->view->renderString('admin.health', [
            'phpVersion' => $phpVersion,
            'phpOk' => $phpOk,
            'extensions' => $extensions,
            'directories' => $directories,
            'dbStatus' => $dbStatus,
            'diskFree' => $diskFree,
            'diskTotal' => $diskTotal,
            'diskPercent' => $diskPercent,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Health', 'health', $html);
    }

    public function blocklist(Request $req, Response $res): void
    {
        $blocklist = [];
        try {
            $stmt = $this->db()->query('SELECT * FROM blocklist ORDER BY created_at DESC');
            $blocklist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $html = $this->view->renderString('admin.blocklist', [
            'blocklist' => $blocklist,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Blocklist', 'blocklist', $html);
    }

    public function addBlocklist(Request $req, Response $res): void
    {
        if ($req->method() !== 'POST') {
            $res->redirect('/admin/blocklist');
            return;
        }
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/blocklist');
            return;
        }

        $pattern = trim($req->input('pattern', ''));
        if ($pattern === '') {
            $this->session->flash('error', 'Pattern is required.');
            $res->redirect('/admin/blocklist');
            return;
        }

        try {
            $stmt = $this->db()->prepare('INSERT INTO blocklist (pattern, created_at) VALUES (:pattern, CURRENT_TIMESTAMP)');
            $stmt->execute([':pattern' => $pattern]);
            $this->session->flash('success', 'Pattern added to blocklist.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to add pattern: ' . $e->getMessage());
        }
        $res->redirect('/admin/blocklist');
    }

    public function importBlocklist(Request $req, Response $res): void
    {
        if ($req->method() !== 'POST') {
            $res->redirect('/admin/blocklist');
            return;
        }
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/blocklist');
            return;
        }

        $patterns = $req->input('patterns', '');
        $lines = preg_split('/\r\n|\r|\n/', $patterns);
        $lines = array_filter(array_map('trim', $lines));

        $added = 0;
        foreach ($lines as $pattern) {
            if ($pattern === '') {
                continue;
            }
            try {
                $stmt = $this->db()->prepare('INSERT OR IGNORE INTO blocklist (pattern, created_at) VALUES (:pattern, CURRENT_TIMESTAMP)');
                $stmt->execute([':pattern' => $pattern]);
                if ($stmt->rowCount() > 0) {
                    $added++;
                }
            } catch (\Throwable $e) {
            }
        }
        $this->session->flash('success', "Imported {$added} patterns to blocklist.");
        $res->redirect('/admin/blocklist');
    }

    public function removeBlocklist(Request $req, Response $res, array $params): void
    {
        if ($req->method() !== 'POST') {
            $res->redirect('/admin/blocklist');
            return;
        }
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/admin/blocklist');
            return;
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            $stmt = $this->db()->prepare('DELETE FROM blocklist WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $this->session->flash('success', 'Pattern removed from blocklist.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to remove pattern.');
        }
        $res->redirect('/admin/blocklist');
    }

    public function logs(Request $req, Response $res): void
    {
        $action = trim($req->query('action', ''));
        $userId = trim($req->query('user_id', ''));
        $dateFrom = trim($req->query('date_from', ''));
        $dateTo = trim($req->query('date_to', ''));
        $page = max(1, (int) $req->query('page', 1));
        $allowedLimits = [10, 25, 50, 100];
        $perPage = (int) $req->query('limit', 10);
        if (!in_array($perPage, $allowedLimits, true)) $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if ($action !== '') {
            $conditions[] = 'action = :action';
            $params[':action'] = $action;
        }
        if ($userId !== '') {
            $conditions[] = 'user_id = :user_id';
            $params[':user_id'] = (int) $userId;
        }
        if ($dateFrom !== '') {
            $conditions[] = 'created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $conditions[] = 'created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        $auditLogs = [];
        $total = 0;
        $totalPages = 1;
        $actions = [];

        try {
            $actionsStmt = $this->db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action');
            $actions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

            $countQuery = 'SELECT COUNT(*) FROM audit_logs' . $where;
            $stmt = $this->db()->prepare($countQuery);
            $stmt->execute($params);
            $total = (int) $stmt->fetchColumn();
            $totalPages = max(1, (int) ceil($total / $perPage));

            $query = 'SELECT * FROM audit_logs' . $where . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $this->db()->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
        }

        $html = $this->view->renderString('admin.logs', [
            'auditLogs' => $auditLogs,
            'actions' => $actions,
            'action' => $action,
            'userId' => $userId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => $perPage,
            'csrf' => $this->session->csrfToken(),
        ]);
        $this->adminLayout($res, 'Audit Logs', 'logs', $html);
    }

    private function checkDb(): array
    {
        $status = ['connected' => false, 'driver' => '', 'error' => ''];
        try {
            $conn = Database::connection();
            $status['connected'] = true;
            $status['driver'] = Database::getInstance()->getDriver();
        } catch (\Throwable $e) {
            $status['error'] = $e->getMessage();
        }
        return $status;
    }

    private function getDiskUsage(): array
    {
        $base = dirname(__DIR__, 3);
        $free = @disk_free_space($base);
        $total = @disk_total_space($base);
        $used = $total - $free;
        $percent = $total > 0 ? round($used / $total * 100, 1) : 0;
        return [
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'percent' => $percent,
        ];
    }

    private function checkExtensions(): array
    {
        $exts = ['pdo', 'pdo_pgsql', 'pdo_sqlite', 'json', 'mbstring', 'openssl', 'gd', 'curl'];
        $result = [];
        foreach ($exts as $ext) {
            $result[$ext] = extension_loaded($ext);
        }
        return $result;
    }

    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
