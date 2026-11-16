<?php

declare(strict_types=1);

/**
 * Pasa todo el personal con origen GRS a TERCERO y lo reparte entre empresas activas.
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
    fwrite(STDERR, "No hay empresas activas en bio_empresa\n");
    exit(1);
}
echo 'Empresas: ' . implode(', ', array_column($empresas, 'codigo')) . "\n";

$st = $pdo->query("SELECT id, dni, nombres, apellidos FROM bio_personal WHERE empresa = 'GRS' ORDER BY id");
$rows = $st ? $st->fetchAll() : [];
if ($rows === []) {
    echo "No hay personal con origen GRS.\n";
    exit(0);
}

$up = $pdo->prepare(
    "UPDATE bio_personal SET empresa = 'TERCERO', empresa_id = ? WHERE id = ?"
);
$n = count($empresas);
foreach ($rows as $i => $r) {
    $eid = (int) $empresas[$i % $n]['id'];
    $cod = (string) $empresas[$i % $n]['codigo'];
    $up->execute([$eid, (int) $r['id']]);
    echo "{$r['dni']} → {$cod}\n";
}
echo 'Actualizados ' . count($rows) . " trabajadores.\n";
