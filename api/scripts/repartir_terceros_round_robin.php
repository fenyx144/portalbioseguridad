<?php

declare(strict_types=1);

/**
 * Reparte todo el personal TERCERO entre empresas activas (round-robin por id).
 */
require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bio\\Api\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Bio\Api\Bootstrap\Database;
use Bio\Api\Bootstrap\Env;

Env::load(dirname(__DIR__));
$pdo = Database::pdo();

$empresas = $pdo->query(
    "SELECT id, codigo FROM bio_empresa WHERE estado = 'ACTIVO' ORDER BY codigo"
)->fetchAll();
if ($empresas === []) {
    fwrite(STDERR, "No hay empresas activas\n");
    exit(1);
}
$nEmp = count($empresas);
echo 'Contratistas: ' . implode(', ', array_column($empresas, 'codigo')) . " (n={$nEmp})\n";

$st = $pdo->query(
    "SELECT id, dni, apellidos, nombres, empresa_id
     FROM bio_personal
     WHERE empresa = 'TERCERO'
     ORDER BY id"
);
$rows = $st ? $st->fetchAll() : [];
if ($rows === []) {
    echo "No hay personal TERCERO.\n";
    exit(0);
}

$up = $pdo->prepare('UPDATE bio_personal SET empresa_id = ? WHERE id = ?');
$resumen = array_fill_keys(array_column($empresas, 'codigo'), 0);

foreach ($rows as $i => $r) {
    $slot = $i % $nEmp;
    $eid = (int) $empresas[$slot]['id'];
    $cod = (string) $empresas[$slot]['codigo'];
    $up->execute([$eid, (int) $r['id']]);
    $resumen[$cod]++;
}

echo 'Total: ' . count($rows) . "\n";
foreach ($resumen as $cod => $cnt) {
    echo "  {$cod}: {$cnt}\n";
}
