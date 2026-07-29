<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use App\Core\Migration;

try {
    echo "Running migrations...\n";
    $migration = new Migration();
    $migration->run();
    echo "Migrations successfully completed!\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
