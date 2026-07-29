<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $headers;
    private array $queryParams;
    private array $body;
    private array $files;
    private string $method;
    private string $uri;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $this->uri = rtrim($uri, '/') ?: '/';

        $this->queryParams = $_GET;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $header = ucwords(strtolower($header), '-');
                $headers[$header] = $value;
            }
        }
        if (function_exists('getallheaders')) {
            $headers = array_merge($headers, getallheaders());
        }
        return $headers;
    }

    private function parseBody(): array
    {
        $contentType = $this->header('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            if (str_contains($contentType, 'application/x-www-form-urlencoded') || empty($contentType)) {
                return $_POST;
            }
            parse_str(file_get_contents('php://input'), $parsed);
            return $parsed;
        }

        return [];
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->body);
    }

    public function only(array $keys): array
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function ip(): string
    {
        $trustedProxies = Env::get('TRUSTED_PROXIES', '');
        if ($trustedProxies !== '' && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $proxies = array_map('trim', explode(',', $trustedProxies));
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remoteAddr, $proxies, true)) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                return trim($ips[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    public function csrfToken(): string
    {
        return Session::getInstance()->csrfToken();
    }

    public function validateCsrf(?string $token = null): bool
    {
        return Session::getInstance()->validateCsrf($token ?? $this->input('_csrf'));
    }
}
