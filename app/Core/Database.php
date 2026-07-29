<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    private string $driver;

    private function __construct()
    {
        $dbDriver = Env::get('DB_DRIVER', 'sqlite');

        if ($dbDriver === 'pgsql') {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '5432');
            $name = Env::get('DB_NAME', 'fort');
            $user = Env::get('DB_USER', 'fort');
            $pass = Env::get('DB_PASSWORD', 'secret');

            $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->driver = 'pgsql';
        } else {
            $path = Env::get('DB_SQLITE_PATH', 'storage/fort.sqlite');
            $path = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
            $this->pdo = new PDO("sqlite:{$path}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
            $this->driver = 'sqlite';
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function connection(): PDO
    {
        return self::getInstance()->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
