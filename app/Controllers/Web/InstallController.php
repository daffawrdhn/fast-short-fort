<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Session;
use App\Core\Env;
use App\Core\Migration;
use App\Models\User;
use App\Models\Workspace;
use PDO;
use PDOException;

class InstallController
{
    private View $view;
    private Session $session;
    private string $basePath;
    private string $installLock;

    public function __construct()
    {
        $this->view = View::getInstance();
        $this->session = Session::getInstance();
        $this->basePath = dirname(__DIR__, 3);
        $this->installLock = $this->basePath . '/storage/.install-lock';
    }

    private function renderInstallPage(Response $res, string $template, array $data = []): void
    {
        $flash = [];
        foreach (['success', 'error', 'info', 'warning'] as $type) {
            if ($this->session->hasFlash($type)) {
                $flash[$type] = $this->session->flash($type);
            }
        }
        $data['flash'] = $flash;
        $data['csrf'] = $this->session->csrfToken();
        $res->view('install.' . $template, $data);
    }

    public function index(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
            return;
        }
        $this->renderInstallPage($res, 'index', ['title' => 'Install - FORT (Fast Short)']);
    }

    public function requirements(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
            return;
        }

        $checks = [
            'php_version' => [
                'label' => 'PHP Version >= 8.2',
                'pass' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'value' => PHP_VERSION,
            ],
        ];

        $requiredExtensions = ['pdo', 'pdo_pgsql', 'pdo_sqlite', 'json', 'mbstring', 'openssl', 'gd', 'curl', 'xml', 'bcmath'];
        foreach ($requiredExtensions as $ext) {
            $checks['ext_' . $ext] = [
                'label' => 'PHP Extension: ' . $ext,
                'pass' => extension_loaded($ext),
                'value' => extension_loaded($ext) ? 'Loaded' : 'Missing',
            ];
        }

        $dirs = [
            'storage' => $this->basePath . '/storage',
            'storage/logs' => $this->basePath . '/storage/logs',
            'storage/cache' => $this->basePath . '/storage/cache',
        ];
        foreach ($dirs as $key => $path) {
            $checks['dir_' . $key] = [
                'label' => 'Directory writable: ' . $key . '/',
                'pass' => is_dir($path) && is_writable($path),
                'value' => is_dir($path) && is_writable($path) ? 'Writable' : 'Not writable',
            ];
        }

        $allPass = true;
        foreach ($checks as $check) {
            if (!$check['pass']) {
                $allPass = false;
                break;
            }
        }

        $this->renderInstallPage($res, 'requirements', [
            'title' => 'Requirements - Install - FORT',
            'checks' => $checks,
            'allPass' => $allPass,
        ]);
    }

    public function database(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
            return;
        }

        $driver = $req->query('driver', Env::get('DB_DRIVER', 'sqlite'));

        $this->renderInstallPage($res, 'database', [
            'title' => 'Database - Install - FORT',
            'driver' => $driver,
        ]);
    }

    public function testConnection(Request $req, Response $res): void
    {
        $driver = $req->input('driver', 'sqlite');
        $host = $req->input('host', '127.0.0.1');
        $port = $req->input('port', '5432');
        $database = $req->input('database', 'fort');
        $username = $req->input('username', 'fort');
        $password = $req->input('password', '');

        try {
            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $res->json(['success' => true, 'message' => 'Connection successful.']);
            } else {
                $path = $this->basePath . '/storage/fort.sqlite';
                $pdo = new PDO("sqlite:{$path}", null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $res->json(['success' => true, 'message' => 'SQLite ready at: storage/fort.sqlite']);
            }
        } catch (PDOException $e) {
            $res->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function configuration(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
            return;
        }
        $this->renderInstallPage($res, 'configuration', [
            'title' => 'Configuration - Install - FORT',
        ]);
    }

    public function install(Request $req, Response $res): void
    {
        if ($this->isInstalled()) {
            $res->redirect('/admin');
            return;
        }
        if ($req->method() !== 'POST') {
            $res->redirect('/install');
            return;
        }
        if (!$req->validateCsrf()) {
            $this->session->flash('error', 'Invalid CSRF token.');
            $res->redirect('/install/configuration');
            return;
        }

        $appName = trim($req->input('app_name', 'FORT (Fast Short)'));
        $appUrl = trim($req->input('app_url', 'http://localhost'));
        $adminName = trim($req->input('admin_name', ''));
        $adminEmail = trim($req->input('admin_email', ''));
        $adminPassword = $req->input('admin_password', '');
        $dbDriver = trim($req->input('db_driver', 'sqlite'));
        $dbHost = trim($req->input('db_host', '127.0.0.1'));
        $dbPort = trim($req->input('db_port', '5432'));
        $dbName = trim($req->input('db_name', 'fort'));
        $dbUser = trim($req->input('db_user', 'fort'));
        $dbPassword = $req->input('db_password', '');

        if ($adminName === '' || $adminEmail === '' || $adminPassword === '') {
            $this->session->flash('error', 'Admin name, email, and password are required.');
            $res->redirect('/install/configuration');
            return;
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'Invalid admin email address.');
            $res->redirect('/install/configuration');
            return;
        }
        if (strlen($adminPassword) < 8) {
            $this->session->flash('error', 'Admin password must be at least 8 characters.');
            $res->redirect('/install/configuration');
            return;
        }

        $appKey = bin2hex(random_bytes(32));

        $envContent = '# FORT (Fast Short) - Configuration' . PHP_EOL;
        $envContent .= '# Generated by installer' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Application ---' . PHP_EOL;
        $envContent .= 'APP_NAME="' . str_replace('"', '\"', $appName) . '"' . PHP_EOL;
        $envContent .= 'APP_ENV=production' . PHP_EOL;
        $envContent .= 'APP_DEBUG=false' . PHP_EOL;
        $envContent .= 'APP_URL=' . $appUrl . PHP_EOL;
        $envContent .= 'APP_KEY=' . $appKey . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Database ---' . PHP_EOL;
        $envContent .= 'DB_DRIVER=' . $dbDriver . PHP_EOL;
        $envContent .= 'DB_SQLITE_PATH=storage/fort.sqlite' . PHP_EOL;
        if ($dbDriver === 'pgsql') {
            $envContent .= 'DB_HOST=' . $dbHost . PHP_EOL;
            $envContent .= 'DB_PORT=' . $dbPort . PHP_EOL;
            $envContent .= 'DB_NAME=' . $dbName . PHP_EOL;
            $envContent .= 'DB_USER=' . $dbUser . PHP_EOL;
            $envContent .= 'DB_PASSWORD=' . $dbPassword . PHP_EOL;
        }
        $envContent .= PHP_EOL . '# --- Session ---' . PHP_EOL;
        $envContent .= 'SESSION_DRIVER=database' . PHP_EOL;
        $envContent .= 'SESSION_LIFETIME=120' . PHP_EOL;
        $envContent .= 'SESSION_HTTPS_ONLY=true' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Mail ---' . PHP_EOL;
        $envContent .= 'MAIL_DRIVER=smtp' . PHP_EOL;
        $envContent .= 'MAIL_HOST=smtp.mailtrap.io' . PHP_EOL;
        $envContent .= 'MAIL_PORT=2525' . PHP_EOL;
        $envContent .= 'MAIL_USERNAME=null' . PHP_EOL;
        $envContent .= 'MAIL_PASSWORD=null' . PHP_EOL;
        $envContent .= 'MAIL_ENCRYPTION=tls' . PHP_EOL;
        $envContent .= 'MAIL_FROM_ADDRESS=noreply@example.com' . PHP_EOL;
        $envContent .= 'MAIL_FROM_NAME="' . str_replace('"', '\"', $appName) . '"' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Rate Limiting ---' . PHP_EOL;
        $envContent .= 'RATE_LIMIT_GLOBAL=100' . PHP_EOL;
        $envContent .= 'RATE_LIMIT_LOGIN=10' . PHP_EOL;
        $envContent .= 'RATE_LIMIT_CREATE_LINK=30' . PHP_EOL;
        $envContent .= 'RATE_LIMIT_API=60' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Security ---' . PHP_EOL;
        $envContent .= 'CORS_ALLOWED_ORIGINS=' . (Env::get('APP_URL', 'https://localhost')) . PHP_EOL;
        $envContent .= 'SESSION_SECURE_COOKIE=true' . PHP_EOL;
        $envContent .= 'TRUSTED_PROXIES=' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- 2FA ---' . PHP_EOL;
        $envContent .= 'TOTP_ISSUER="' . str_replace('"', '\"', $appName) . '"' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Google Safe Browsing ---' . PHP_EOL;
        $envContent .= 'SAFE_BROWSING_API_KEY=' . PHP_EOL . PHP_EOL;
        $envContent .= '# --- Cron / Cleanup ---' . PHP_EOL;
        $envContent .= 'CLEANUP_EXPIRED_LINKS_DAYS=30' . PHP_EOL;
        $envContent .= 'PURGE_AUDIT_LOGS_DAYS=365' . PHP_EOL;
        $envContent .= 'PURGE_UNVERIFIED_USERS_DAYS=7' . PHP_EOL;

        $envPath = $this->basePath . '/.env';
        if (file_put_contents($envPath, $envContent) === false) {
            $this->session->flash('error', 'Failed to write .env file.');
            $res->redirect('/install/configuration');
            return;
        }

        $_ENV = [];
        Env::load($this->basePath);

        try {
            $migration = new Migration();
            $migration->run();
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Migration failed: ' . $e->getMessage());
            $res->redirect('/install/configuration');
            return;
        }

        try {
            $user = User::create([
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ]);
            $user->update(['is_admin' => true]);
            $user->verifyEmail();
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to create admin user: ' . $e->getMessage());
            $res->redirect('/install/configuration');
            return;
        }

        try {
            $slug = 'personal-' . $user->id;
            $workspace = Workspace::create([
                'name' => $adminName . '\'s Workspace',
                'slug' => $slug,
                'owner_id' => $user->id,
                'plan' => 'enterprise',
            ]);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Failed to create workspace: ' . $e->getMessage());
            $res->redirect('/install/configuration');
            return;
        }

        file_put_contents($this->installLock, date('Y-m-d H:i:s') . PHP_EOL);

        $this->session->flash('success', 'Installation completed successfully.');
        $res->redirect('/install/complete');
    }

    public function complete(Request $req, Response $res): void
    {
        if (!$this->isInstalled()) {
            $res->redirect('/install');
            return;
        }
        $this->renderInstallPage($res, 'complete', [
            'title' => 'Complete - Install - FORT',
        ]);
    }

    private function isInstalled(): bool
    {
        return file_exists($this->installLock);
    }
}
