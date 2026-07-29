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
if ($allowedOrigins === '*') {
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

$router->post('/api/auth/login', [\App\Controllers\Api\AuthController::class, 'login']);
$router->post('/api/auth/register', [\App\Controllers\Api\AuthController::class, 'register']);
$router->post('/api/auth/logout', [\App\Controllers\Api\AuthController::class, 'logout']);
$router->post('/api/auth/refresh', [\App\Controllers\Api\AuthController::class, 'refresh']);

$router->group('/api/links', function (Router $router) {
    $router->get('/', [\App\Controllers\Api\LinkController::class, 'index']);
    $router->post('/', [\App\Controllers\Api\LinkController::class, 'store']);
    $router->get('/{id}', [\App\Controllers\Api\LinkController::class, 'show']);
    $router->put('/{id}', [\App\Controllers\Api\LinkController::class, 'update']);
    $router->delete('/{id}', [\App\Controllers\Api\LinkController::class, 'destroy']);
}, [\App\Middleware\ApiAuthMiddleware::class]);

$router->get('/api/analytics/{id}', [\App\Controllers\Api\AnalyticsController::class, 'show']);
$router->get('/api/health', function (Request $req, Response $res) {
    $res->json(['status' => 'ok', 'timestamp' => date('c')]);
});

$router->dispatch($request, $response);
$response->send();
