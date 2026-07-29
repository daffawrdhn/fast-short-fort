<?php
return [
    'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
    'sqlite' => [
        'path' => __DIR__ . '/../' . ltrim($_ENV['DB_SQLITE_PATH'] ?? 'storage/fort.sqlite', '/'),
    ],
    'pgsql' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'database' => $_ENV['DB_NAME'] ?? 'fort',
        'username' => $_ENV['DB_USER'] ?? 'fort',
        'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
    ],
];
