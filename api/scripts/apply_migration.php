<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php apply_migration.php <file.sql>\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Env.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Database.php';

use Bio\Api\Bootstrap\Database;
use Bio\Api\Bootstrap\Env;

Env::load(dirname(__DIR__));
$pdo = Database::pdo();
$path = $argv[1];
if (!is_file($path)) {
    fwrite(STDERR, "Missing {$path}\n");
    exit(1);
}
$sql = file_get_contents($path);
if ($sql === false) {
    exit(1);
}
foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $stmt) {
    $stmt = preg_replace('/^\s*--[^\n]*\n+/m', '', $stmt) ?? $stmt;
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    try {
        $pdo->exec($stmt);
        echo "OK: " . substr(str_replace("\n", ' ', $stmt), 0, 60) . "...\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
    }
}
