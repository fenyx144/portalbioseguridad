<?php

declare(strict_types=1);

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

$ids = [];
foreach (['EMP002', 'EMP003'] as $cod) {
    $st = $pdo->prepare("SELECT id FROM bio_empresa WHERE codigo = ? AND estado = 'ACTIVO' LIMIT 1");
    $st->execute([$cod]);
    $row = $st->fetch();
    if ($row) {
        $ids[$cod] = (int) $row['id'];
    }
}
if ($ids === []) {
    fwrite(STDERR, "No hay EMP002 ni EMP003 activas\n");
    exit(1);
}

$st = $pdo->query(
    "SELECT id FROM bio_personal
     WHERE empresa = 'TERCERO' AND (empresa_id IS NULL OR empresa_id NOT IN (
       SELECT id FROM bio_empresa WHERE codigo = 'EMP001'
     ))
     ORDER BY id"
);
$rows = $st ? $st->fetchAll() : [];
$alternar = array_values($ids);
$up = $pdo->prepare('UPDATE bio_personal SET empresa_id = ? WHERE id = ?');
$n = 0;
foreach ($rows as $i => $r) {
    $eid = $alternar[$i % count($alternar)];
    $up->execute([$eid, (int) $r['id']]);
    $n++;
}
echo "Asignados {$n} trabajadores terceros a EMP002/EMP003 (alternando).\n";
