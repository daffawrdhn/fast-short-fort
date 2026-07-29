<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Session;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

error_reporting(E_ALL);

Env::load(dirname(__DIR__));

$debug = Env::get('APP_DEBUG', 'false') === 'true';

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(function (Throwable $e) use ($debug) {
    http_response_code(500);
    if ($debug) {
        echo '<h1>Internal Server Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        View::getInstance()->render('errors.500');
    }
    exit;
});

Session::getInstance();

$request = new Request();
$response = new Response();

$router = new Router();

$router->group('', function (Router $router) {
    $router->get('/', function (Request $req, Response $res) {
        $res->redirect('/dashboard');
    });
    $router->get('/install', [\App\Controllers\Web\InstallController::class, 'index'])->name('install');
    $router->get('/install/requirements', [\App\Controllers\Web\InstallController::class, 'requirements']);
    $router->get('/install/database', [\App\Controllers\Web\InstallController::class, 'database']);
    $router->post('/install/database', [\App\Controllers\Web\InstallController::class, 'saveDatabase']);
    $router->get('/install/configuration', [\App\Controllers\Web\InstallController::class, 'configuration']);
    $router->post('/install/install', [\App\Controllers\Web\InstallController::class, 'install']);
    $router->get('/install/complete', [\App\Controllers\Web\InstallController::class, 'complete']);
}, [\App\Middleware\SecurityHeadersMiddleware::class]);

$router->get('/login', [\App\Controllers\Web\AuthController::class, 'showLoginForm'])->name('login');
$router->post('/login', [\App\Controllers\Web\AuthController::class, 'login']);
$router->get('/register', [\App\Controllers\Web\AuthController::class, 'showRegisterForm'])->name('register');
$router->post('/register', [\App\Controllers\Web\AuthController::class, 'register']);
$router->post('/logout', [\App\Controllers\Web\AuthController::class, 'logout'])->name('logout');
$router->get('/verify-email', [\App\Controllers\Web\AuthController::class, 'showVerifyEmail'])->name('verify.email');
$router->get('/verify-email/{token}', [\App\Controllers\Web\AuthController::class, 'verifyEmail']);
$router->post('/verify-email/resend', [\App\Controllers\Web\AuthController::class, 'resendVerification']);
$router->get('/forgot-password', [\App\Controllers\Web\AuthController::class, 'showForgotPassword'])->name('forgot.password');
$router->post('/forgot-password', [\App\Controllers\Web\AuthController::class, 'sendResetLink'])->middleware(\App\Middleware\RateLimitMiddleware::class);
$router->get('/reset-password/{token}', [\App\Controllers\Web\AuthController::class, 'showResetPassword'])->name('reset.password');
$router->get('/reset-password', [\App\Controllers\Web\AuthController::class, 'showResetPassword']);
$router->post('/reset-password/{token}', [\App\Controllers\Web\AuthController::class, 'resetPassword'])->middleware(\App\Middleware\RateLimitMiddleware::class);
$router->get('/twofa/challenge', [\App\Controllers\Web\AuthController::class, 'showTwoFA'])->name('2fa.challenge');
$router->post('/twofa/challenge', [\App\Controllers\Web\AuthController::class, 'verifyTwoFA']);
$router->get('/twofa/setup', [\App\Controllers\Web\AuthController::class, 'showSetupTwoFA'])->name('2fa.setup');
$router->post('/twofa/setup', [\App\Controllers\Web\AuthController::class, 'setupTwoFA']);
$router->post('/twofa/disable', [\App\Controllers\Web\AuthController::class, 'disableTwoFA']);

$router->group('', function (Router $router) {
    $router->get('/dashboard', [\App\Controllers\Web\DashboardController::class, 'index'])->name('dashboard');
    $router->get('/links', [\App\Controllers\Web\LinkController::class, 'index'])->name('links');
    $router->get('/links/create', [\App\Controllers\Web\LinkController::class, 'create'])->name('links.create');
    $router->post('/links', [\App\Controllers\Web\LinkController::class, 'store'])->name('links.store');
    $router->get('/links/{id}', [\App\Controllers\Web\LinkController::class, 'show'])->name('links.show');
    $router->get('/links/{id}/edit', [\App\Controllers\Web\LinkController::class, 'edit'])->name('links.edit');
    $router->post('/links/{id}/edit', [\App\Controllers\Web\LinkController::class, 'update']);
    $router->post('/links/{id}/delete', [\App\Controllers\Web\LinkController::class, 'delete'])->name('links.delete');
    $router->post('/links/{id}/force-delete', [\App\Controllers\Web\LinkController::class, 'forceDelete']);
    $router->post('/links/{id}/toggle', [\App\Controllers\Web\LinkController::class, 'toggleActive']);
    $router->get('/links/{id}/qrcode', [\App\Controllers\Web\LinkController::class, 'downloadQRCode']);
    $router->post('/links/bulk/delete', [\App\Controllers\Web\LinkController::class, 'bulkDelete']);
    $router->post('/links/bulk/enable', [\App\Controllers\Web\LinkController::class, 'bulkEnable']);
    $router->post('/links/bulk/disable', [\App\Controllers\Web\LinkController::class, 'bulkDisable']);
    $router->get('/links/bulk/export/{format}', [\App\Controllers\Web\LinkController::class, 'bulkExport']);
    $router->get('/analytics', [\App\Controllers\Web\AnalyticsController::class, 'index'])->name('analytics');
    $router->get('/analytics/{linkId}', [\App\Controllers\Web\AnalyticsController::class, 'show'])->name('analytics.show');
    $router->get('/analytics/{linkId}/realtime', [\App\Controllers\Web\AnalyticsController::class, 'realtime']);
    $router->get('/profile', [\App\Controllers\Web\ProfileController::class, 'index'])->name('profile');
    $router->post('/profile', [\App\Controllers\Web\ProfileController::class, 'update']);
    $router->get('/settings', [\App\Controllers\Web\SettingsController::class, 'index'])->name('settings');
    $router->post('/settings', [\App\Controllers\Web\SettingsController::class, 'update']);
}, [\App\Middleware\AuthMiddleware::class]);

$router->group('', function (Router $router) {
    $router->get('/admin', [\App\Controllers\Web\AdminController::class, 'index'])->name('admin');
    $router->get('/admin/users', [\App\Controllers\Web\AdminController::class, 'users']);
    $router->post('/admin/users/create', [\App\Controllers\Web\AdminController::class, 'createUser']);
    $router->post('/admin/users/{id}/edit', [\App\Controllers\Web\AdminController::class, 'editUser']);
    $router->post('/admin/users/{id}/delete', [\App\Controllers\Web\AdminController::class, 'deleteUser']);
    $router->get('/admin/workspaces', [\App\Controllers\Web\AdminController::class, 'workspaces']);
    $router->post('/admin/workspaces/{id}/delete', [\App\Controllers\Web\AdminController::class, 'deleteWorkspace']);
    $router->get('/admin/settings', [\App\Controllers\Web\AdminController::class, 'settings']);
    $router->post('/admin/settings', [\App\Controllers\Web\AdminController::class, 'updateSettings']);
    $router->get('/admin/health', [\App\Controllers\Web\AdminController::class, 'health']);
    $router->get('/admin/blocklist', [\App\Controllers\Web\AdminController::class, 'blocklist']);
    $router->post('/admin/blocklist', [\App\Controllers\Web\AdminController::class, 'addToBlocklist']);
    $router->post('/admin/blocklist/import', [\App\Controllers\Web\AdminController::class, 'importBlocklist']);
    $router->post('/admin/blocklist/{id}/delete', [\App\Controllers\Web\AdminController::class, 'removeBlocklist']);
    $router->get('/admin/logs', [\App\Controllers\Web\AdminController::class, 'logs']);
}, [\App\Middleware\AuthMiddleware::class, \App\Middleware\AdminMiddleware::class]);

$router->get('/p/{slug}', [\App\Controllers\Web\LinkController::class, 'showPasswordForm'])->name('link.password');
$router->post('/p/{slug}', [\App\Controllers\Web\LinkController::class, 'verifyPassword']);
$router->get('/{slug}', [\App\Controllers\Web\LinkController::class, 'redirect'])->name('redirect');

$router->dispatch($request, $response);
$response->send();
