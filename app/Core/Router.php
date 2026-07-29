<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private array $namedRoutes = [];

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function match(array $methods, string $path, callable|array $handler): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler);
        }
        return $this;
    }

    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];
        $callback($this);
        array_pop($this->groupStack);
        return $this;
    }

    public function name(string $name): self
    {
        if (!empty($this->routes)) {
            $route = &$this->routes[array_key_last($this->routes)];
            $route['name'] = $name;
            $this->namedRoutes[$name] = $route;
        }
        return $this;
    }

    public function middleware(string|array $middleware): self
    {
        if (!empty($this->routes)) {
            $route = &$this->routes[array_key_last($this->routes)];
            if (is_array($middleware)) {
                $route['middleware'] = array_merge($route['middleware'], $middleware);
            } else {
                $route['middleware'][] = $middleware;
            }
        }
        return $this;
    }

    private function addRoute(string $method, string $path, callable|array $handler): self
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $middleware = array_merge($middleware, $group['middleware']);
        }

        $fullPath = $prefix . $path;
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => null,
        ];

        return $this;
    }

    public function dispatch(Request $request, Response $response): void
    {
        $uri = $request->uri();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $middleware) {
                    $instance = is_string($middleware) ? new $middleware() : $middleware;
                    $result = $instance->handle($request, $response, fn() => null);
                    if ($result === false) {
                        return;
                    }
                }

                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($request, $response, $params);
                } else {
                    $handler($request, $response, $params);
                }

                return;
            }
        }

        $response->status(404)->json(['error' => 'Not Found']);
    }

    public function route(string $name, array $params = []): ?string
    {
        if (!isset($this->namedRoutes[$name])) {
            return null;
        }

        $path = $this->namedRoutes[$name]['path'];
        foreach ($params as $key => $value) {
            $path = str_replace("{{$key}}", (string)$value, $path);
        }

        return $path;
    }
}
