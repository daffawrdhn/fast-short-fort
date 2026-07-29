<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

class Migration
{
    private PDO $pdo;
    private string $driver;
    private string $migrationsDir;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
        $this->driver = Env::get('DB_DRIVER', 'sqlite');
        $this->migrationsDir = dirname(__DIR__, 2) . '/database/migrations';

        if (!is_dir($this->migrationsDir)) {
            mkdir($this->migrationsDir, 0755, true);
        }

        $this->ensureTrackingTable();
    }

    private function ensureTrackingTable(): void
    {
        $sql = match ($this->driver) {
            'pgsql' => 'CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )',
            'sqlite' => 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                executed_at TEXT DEFAULT (datetime(\'now\'))
            )',
            default => throw new RuntimeException("Unsupported driver: {$this->driver}"),
        };

        $this->pdo->exec($sql);
    }

    public function run(): void
    {
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $executed = $this->getExecutedMigrations();

        foreach ($files as $file) {
            $filename = basename($file);

            if (in_array($filename, $executed, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                continue;
            }

            $statements = $this->splitStatements($sql);

            try {
                $this->pdo->beginTransaction();

                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $processed = $this->processDriverSyntax($statement);
                        if ($processed !== '') {
                            $this->pdo->exec($processed);
                        }
                    }
                }

                $this->markExecuted($filename);
                $this->pdo->commit();

                echo "Migrated: {$filename}" . PHP_EOL;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw new RuntimeException("Migration {$filename} failed: " . $e->getMessage());
            }
        }
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function markExecuted(string $filename): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $filename]);
    }

    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = explode(';', $sql);
        return array_filter(array_map('trim', $statements));
    }

    private function processDriverSyntax(string $statement): string
    {
        $pattern = '/\/\*\s*driver:\s*(\w+)\s*\*\/(.+?)(?=\/\*\s*enddriver\s*\*\/|$)/s';
        $statement = preg_replace_callback($pattern, function ($matches) {
            $driver = trim($matches[1]);
            $content = trim($matches[2]);
            return $driver === $this->driver ? $content : '';
        }, $statement);

        $lines = explode("\n", $statement);
        $filtered = array_filter($lines, function (string $line) {
            $trimmed = trim($line);
            return !str_starts_with($trimmed, '-- ' . $this->driver)
                && !preg_match('/^--\s*(pgsql|sqlite)/i', $trimmed);
        });

        $lines = [];
        foreach (explode("\n", $statement) as $line) {
            $trimmed = trim($line);
            if (preg_match('/^--\s*(pgsql|sqlite):\s*(.+)$/i', $trimmed, $m)) {
                if (strtolower($m[1]) === $this->driver) {
                    $lines[] = $m[2];
                }
            } elseif (!preg_match('/^--\s*(pgsql|sqlite)\b/i', $trimmed)) {
                $lines[] = $line;
            }
        }

        return trim(implode("\n", $lines));
    }

    public function rollback(int $steps = 1): void
    {
        $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY id DESC LIMIT ' . $steps);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($migrations as $migration) {
            $downFile = str_replace('.sql', '.down.sql', $this->migrationsDir . '/' . $migration);
            if (file_exists($downFile)) {
                $sql = file_get_contents($downFile);
                if ($sql !== false && trim($sql) !== '') {
                    $this->pdo->exec($this->processDriverSyntax($sql));
                }
            }

            $deleteStmt = $this->pdo->prepare('DELETE FROM migrations WHERE migration = :migration');
            $deleteStmt->execute(['migration' => $migration]);
            echo "Rolled back: {$migration}" . PHP_EOL;
        }
    }

    public function reset(): void
    {
        $stmt = $this->pdo->query('SELECT migration FROM migrations ORDER BY id DESC');
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($migrations as $migration) {
            $downFile = str_replace('.sql', '.down.sql', $this->migrationsDir . '/' . $migration);
            if (file_exists($downFile)) {
                $sql = file_get_contents($downFile);
                if ($sql !== false && trim($sql) !== '') {
                    $this->pdo->exec($sql);
                }
            }
        }

        $this->pdo->exec('DELETE FROM migrations');
        echo "All migrations reset." . PHP_EOL;
    }
}
