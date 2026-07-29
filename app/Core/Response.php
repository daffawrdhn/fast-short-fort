<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];
    private ?string $body = null;

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function cookie(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        $this->cookies[] = [$name, $value, $expire, $path, $domain, $secure, $httpOnly];
        return $this;
    }

    public function json(mixed $data, ?int $status = null): self
    {
        if ($status !== null) {
            $this->statusCode = $status;
        }
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    public function body(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    public function view(string $template, array $data = []): self
    {
        return View::getInstance()->render($template, $data);
    }

    public function redirect(string $url, int $status = 302): self
    {
        $this->statusCode = $status;
        $this->header('Location', $url);
        return $this;
    }

    public function back(): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $baseUrl = Env::get('APP_URL', 'http://localhost');
        if (!str_starts_with($referer, $baseUrl)) {
            $referer = '/';
        }
        return $this->redirect($referer);
    }

    public function send(): void
    {
        $defaultHeaders = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
        if (Env::get('APP_ENV', 'production') === 'production') {
            $defaultHeaders['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($defaultHeaders as $key => $value) {
            if (!isset($this->headers[$key])) {
                $this->headers[$key] = $value;
            }
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        foreach ($this->cookies as $cookie) {
            setcookie(...$cookie);
        }

        if ($this->body !== null) {
            echo $this->body;
        }
    }
}
