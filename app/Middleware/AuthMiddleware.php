<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $session = Session::getInstance();

        if ($session->has('user_id')) {
            return $next($request, $response);
        }

        $token = $_COOKIE['remember_me'] ?? null;
        if ($token !== null) {
            $authService = new AuthService();
            $user = $authService->validateRememberToken($token);
            if ($user !== null) {
                $authService->createSession($user);
                $newToken = $authService->generateRememberToken($user);
                setcookie('remember_me', $newToken, time() + 86400 * 30, '/', '', false, true);
                return $next($request, $response);
            }
        }

        $response->redirect('/login')->send();
        exit;
    }
}
