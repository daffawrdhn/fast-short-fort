<?php

declare(strict_types=1);

namespace App\Core;

abstract class Middleware implements MiddlewareInterface
{
    abstract public function handle(Request $request, Response $response, callable $next): mixed;
}
