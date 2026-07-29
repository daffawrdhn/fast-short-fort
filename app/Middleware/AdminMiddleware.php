<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

class AdminMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $session = Session::getInstance();

        if (!$session->has('user_id') || !$session->get('user_is_admin')) {
            $response->status(403);
            View::getInstance()->render('errors.403');
            $response->send();
            exit;
        }

        return $next($request, $response);
    }
}
