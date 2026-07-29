<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class GuestMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $session = Session::getInstance();

        if ($session->has('user_id')) {
            $response->redirect('/dashboard')->send();
            exit;
        }

        return $next($request, $response);
    }
}
