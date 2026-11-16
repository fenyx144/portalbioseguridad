<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Env.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Database.php';

use Bio\Api\Bootstrap\Database;
use Bio\Api\Bootstrap\Env;

Env::load(dirname(__DIR__));
$pdo = Database::pdo();

foreach (['schema.sql', 'seed.sql'] as $file) {
    $path = dirname(__DIR__, 2) . '/db/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$path}\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        exit(1);
    }
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                fwrite(STDERR, $e->getMessage() . "\nStmt: " . substr($stmt, 0, 80) . "...\n");
            }
        }
    }
    echo "Applied {$file}\n";
}
