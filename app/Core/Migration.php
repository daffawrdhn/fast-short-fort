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

            $sql = $this->processDriverSyntax($sql);
            $statements = $this->splitStatements($sql);

            try {
                $this->pdo->beginTransaction();

                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $this->pdo->exec($statement);
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
        $driver = $this->driver;

        $statement = preg_replace_callback('/\/\*\s*driver:\s*(\w+)\s*\*\/(.*?)(?=\/\*\s*enddriver\s*\*\/|$)/s', function ($m) use ($driver) {
            return strtolower(trim($m[1])) === $driver ? trim($m[2]) : '';
        }, $statement);

        $inBlock = false;
        $blockDriver = '';
        $blockContent = '';
        $output = [];

        foreach (explode("\n", $statement) as $raw) {
            $trimmed = trim($raw);

            if (preg_match('/^--\s*\{\{DRIVER:(\w+)\}\}\s*$/i', $trimmed, $m)) {
                $inBlock = true;
                $blockDriver = strtolower($m[1]);
                $blockContent = '';
                continue;
            }

            if ($inBlock && preg_match('/^--\s*\{\{END\}\}\s*$/i', $trimmed)) {
                if ($blockDriver === $driver) {
                    $output[] = $blockContent;
                }
                $inBlock = false;
                continue;
            }

            if ($inBlock) {
                $blockContent .= $raw . "\n";
                continue;
            }

            if (preg_match('/^--\s*(pgsql|sqlite):\s*(.+)$/i', $trimmed, $m)) {
                if (strtolower($m[1]) === $driver) {
                    $output[] = $m[2];
                }
                continue;
            }

            if (preg_match('/^--\s*(pgsql|sqlite)\b/i', $trimmed)) {
                continue;
            }

            $output[] = $raw;
        }

        return trim(implode("\n", $output));
    }

    public function rollback(int $steps = 1): void
    {
        $steps = max(1, (int) $steps);
        $stmt = $this->pdo->prepare('SELECT migration FROM migrations ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $steps, \PDO::PARAM_INT);
        $stmt->execute();
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
