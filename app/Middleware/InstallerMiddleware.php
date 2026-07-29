<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

class InstallerMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $basePath = dirname(__DIR__);
        $installLock = $basePath . '/storage/.install-lock';
        $uri = $request->uri();

        $isInstalled = file_exists($installLock);
        $isInstallRoute = str_starts_with($uri, '/install');

        if ($isInstalled && $isInstallRoute) {
            $response->redirect('/admin')->send();
            return false;
        }

        if (!$isInstalled && !$isInstallRoute) {
            $response->redirect('/install')->send();
            return false;
        }

        return $next($request, $response);
    }
}
