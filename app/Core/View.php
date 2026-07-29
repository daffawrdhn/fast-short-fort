<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    private static ?View $instance = null;
    private string $basePath;
    private string $extension = '.php';
    private array $sections = [];
    private ?string $currentSection = null;
    private ?string $layoutFile = null;

    private function __construct()
    {
        $this->basePath = dirname(__DIR__, 2) . '/resources/views';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render(string $template, array $data = []): Response
    {
        $content = $this->renderString($template, $data);
        $response = new Response();
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->status(200);

        // Need to use a different approach since we can't modify body after echo
        echo $content;
        return $response;
    }

    public function renderString(string $template, array $data = []): string
    {
        $this->sections = [];
        $this->layoutFile = null;

        $file = $this->basePath . '/' . str_replace('.', '/', $template) . $this->extension;

        if (!file_exists($file)) {
            throw new RuntimeException("View template not found: {$file}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        if ($this->layoutFile !== null) {
            $data['content'] = $content;
            extract($data, EXTR_SKIP);
            ob_start();
            include $this->layoutFile;
            $content = ob_get_clean();
        }

        return $content;
    }

    public function extend(string $layout): void
    {
        $layoutPath = str_replace('.', '/', $layout);
        $file = $this->basePath . '/layouts/' . $layoutPath . $this->extension;
        if (!file_exists($file)) {
            $file = $this->basePath . '/' . $layoutPath . $this->extension;
        }

        $this->layoutFile = $file;
    }

    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if ($this->currentSection === null) {
            return;
        }
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    public function getSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function insert(string $template, array $data = []): string
    {
        $file = $this->basePath . '/' . str_replace('.', '/', $template) . $this->extension;
        if (!file_exists($file)) {
            throw new RuntimeException("Partial view not found: {$file}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    public function include(string $template, array $data = []): void
    {
        echo $this->insert($template, $data);
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function exists(string $template): bool
    {
        $file = $this->basePath . '/' . str_replace('.', '/', $template) . $this->extension;
        return file_exists($file);
    }

    public function setBasePath(string $path): void
    {
        $this->basePath = rtrim($path, '/\\');
    }
}
