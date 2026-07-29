<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class Session
{
    private static ?Session $instance = null;

    private function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = Env::get('SESSION_HTTPS_ONLY', 'false') === 'true';

            session_set_cookie_params([
                'lifetime' => (int)Env::get('SESSION_LIFETIME', '120') * 60,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            if (!session_start()) {
                throw new RuntimeException('Failed to start session.');
            }
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set(string $key, mixed $value): void
    {
        $this->checkIdleTimeout();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->checkIdleTimeout();
        return $_SESSION[$key] ?? $default;
    }

    private function checkIdleTimeout(): void
    {
        $maxIdle = (int)Env::get('SESSION_LIFETIME', '120') * 60;
        $lastActivity = $_SESSION['_last_activity'] ?? 0;

        if ($lastActivity > 0 && (time() - $lastActivity) > $maxIdle) {
            $_SESSION = [];
            session_destroy();
            throw new \RuntimeException('Session expired');
        }

        $_SESSION['_last_activity'] = time();
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $flash = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $flash;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    public function keepFlash(string $key): void
    {
        if (isset($_SESSION['_flash'][$key])) {
            $_SESSION['_flash_keep'][$key] = $_SESSION['_flash'][$key];
        }
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public function validateCsrf(string $token): bool
    {
        return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
    }

    public function regenerateCsrf(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    public function setRememberMe(string $token): void
    {
        $_SESSION['_remember_me'] = $token;
    }

    public function getRememberMe(): ?string
    {
        return $_SESSION['_remember_me'] ?? null;
    }

    public function hasRememberMe(): bool
    {
        return isset($_SESSION['_remember_me']);
    }

    public function clearRememberMe(): void
    {
        unset($_SESSION['_remember_me']);
    }
}
