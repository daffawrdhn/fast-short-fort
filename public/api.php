<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

error_reporting(E_ALL);

Env::load(dirname(__DIR__));

$debug = Env::get('APP_DEBUG', 'false') === 'true';

if ($debug) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

header('Content-Type: application/json; charset=utf-8');

$allowedOrigins = Env::get('CORS_ALLOWED_ORIGINS', '*');
$appEnv = Env::get('APP_ENV', 'production');
if ($allowedOrigins === '*' && $appEnv === 'production') {
    header('Access-Control-Allow-Origin: ' . Env::get('APP_URL', 'https://example.com'));
} elseif ($allowedOrigins === '*') {
    header('Access-Control-Allow-Origin: *');
} else {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $origins = explode(',', $allowedOrigins);
    if (in_array($origin, $origins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

set_exception_handler(function (Throwable $e) use ($debug) {
    http_response_code(500);
    echo json_encode([
        'error' => $debug ? $e->getMessage() : 'Internal Server Error',
        'code' => $e->getCode(),
    ]);
    exit;
});

$request = new Request();
$response = new Response();

$router = new Router();

$router->get('/api/health', function (Request $req, Response $res) {
    $res->json(['status' => 'ok', 'timestamp' => date('c')]);
}, [\App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/auth', function (Router $router) {
    $router->post('/login', [\App\Controllers\Api\AuthController::class, 'login']);
    $router->post('/register', [\App\Controllers\Api\AuthController::class, 'register']);
    $router->post('/refresh', [\App\Controllers\Api\AuthController::class, 'refresh']);
    $router->get('/me', [\App\Controllers\Api\AuthController::class, 'me']);
    $router->post('/logout', [\App\Controllers\Api\AuthController::class, 'logout']);
}, [\App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/links', function (Router $router) {
    $router->get('/', [\App\Controllers\Api\LinkController::class, 'index']);
    $router->post('/', [\App\Controllers\Api\LinkController::class, 'store']);
    $router->get('/{id}', [\App\Controllers\Api\LinkController::class, 'show']);
    $router->put('/{id}', [\App\Controllers\Api\LinkController::class, 'update']);
    $router->delete('/{id}', [\App\Controllers\Api\LinkController::class, 'destroy']);
    $router->get('/{id}/analytics', [\App\Controllers\Api\LinkController::class, 'analytics']);
    $router->post('/bulk', [\App\Controllers\Api\LinkController::class, 'bulkStore']);
    $router->get('/{id}/qrcode', [\App\Controllers\Api\LinkController::class, 'qrcode']);
}, [\App\Middleware\ApiAuthMiddleware::class, \App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/workspaces', function (Router $router) {
    $router->get('/', [\App\Controllers\Api\WorkspaceController::class, 'index']);
    $router->post('/', [\App\Controllers\Api\WorkspaceController::class, 'store']);
    $router->get('/{id}', [\App\Controllers\Api\WorkspaceController::class, 'show']);
    $router->put('/{id}', [\App\Controllers\Api\WorkspaceController::class, 'update']);
    $router->delete('/{id}', [\App\Controllers\Api\WorkspaceController::class, 'destroy']);
    $router->get('/{id}/members', [\App\Controllers\Api\WorkspaceController::class, 'members']);
    $router->post('/{id}/members', [\App\Controllers\Api\WorkspaceController::class, 'addMember']);
    $router->delete('/{id}/members/{userId}', [\App\Controllers\Api\WorkspaceController::class, 'removeMember']);
    $router->put('/{id}/members/{userId}', [\App\Controllers\Api\WorkspaceController::class, 'updateMemberRole']);
}, [\App\Middleware\ApiAuthMiddleware::class, \App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/analytics', function (Router $router) {
    $router->get('/links/{linkId}', [\App\Controllers\Api\AnalyticsController::class, 'linkAnalytics']);
    $router->get('/workspaces/{workspaceId}', [\App\Controllers\Api\AnalyticsController::class, 'workspaceAnalytics']);
    $router->get('/links/{linkId}/export/{format}', [\App\Controllers\Api\AnalyticsController::class, 'export']);
}, [\App\Middleware\ApiAuthMiddleware::class, \App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/domains', function (Router $router) {
    $router->get('/', [\App\Controllers\Api\DomainController::class, 'index']);
    $router->post('/', [\App\Controllers\Api\DomainController::class, 'store']);
    $router->post('/{id}/verify', [\App\Controllers\Api\DomainController::class, 'verify']);
    $router->delete('/{id}', [\App\Controllers\Api\DomainController::class, 'destroy']);
}, [\App\Middleware\ApiAuthMiddleware::class, \App\Middleware\RateLimitMiddleware::class]);

$router->group('/api/webhooks', function (Router $router) {
    $router->get('/', [\App\Controllers\Api\WebhookController::class, 'index']);
    $router->post('/', [\App\Controllers\Api\WebhookController::class, 'store']);
    $router->put('/{id}', [\App\Controllers\Api\WebhookController::class, 'update']);
    $router->delete('/{id}', [\App\Controllers\Api\WebhookController::class, 'destroy']);
    $router->post('/{id}/test', [\App\Controllers\Api\WebhookController::class, 'test']);
}, [\App\Middleware\ApiAuthMiddleware::class, \App\Middleware\RateLimitMiddleware::class]);

$router->dispatch($request, $response);
$response->send();
