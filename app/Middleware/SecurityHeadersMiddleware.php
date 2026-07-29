<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

class SecurityHeadersMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if (Env::get('APP_ENV', 'production') === 'production') {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->header(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "img-src 'self' data: https:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'"
        );

        return $next($request, $response);
    }
}
