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

$st = $pdo->prepare("SELECT id, codigo, razon_social FROM bio_empresa WHERE codigo = ? AND estado = 'ACTIVO' LIMIT 1");
$st->execute(['EMP001']);
$emp = $st->fetch();
if (!$emp) {
    fwrite(STDERR, "No existe empresa activa con codigo EMP001\n");
    exit(1);
}
$empresaId = (int) $emp['id'];
echo "Empresa {$emp['codigo']} (id={$empresaId}) — {$emp['razon_social']}\n";

$granja = '';
$r = $pdo->query('SELECT TRIM(codgranja) AS codigo FROM lk_granjas ORDER BY codgranja LIMIT 1');
if ($r) {
    $row = $r->fetch();
    if ($row) {
        $granja = (string) $row['codigo'];
    }
}

$ejemplos = [
    ['dni' => '70990001', 'nombres' => 'Carlos', 'apellidos' => 'Mendoza Quispe', 'cargo' => 'Vigilante'],
    ['dni' => '70990002', 'nombres' => 'Rosa', 'apellidos' => 'Huaman Torres', 'cargo' => 'Vigilante'],
    ['dni' => '70990003', 'nombres' => 'Jorge', 'apellidos' => 'Rivas Palomino', 'cargo' => 'Supervisor de campo'],
    ['dni' => '70990004', 'nombres' => 'Lucia', 'apellidos' => 'Diaz Fernandez', 'cargo' => 'Asistente bioseguridad'],
];

$ins = $pdo->prepare(
    'INSERT INTO bio_personal (dni, nombres, apellidos, cargo, empresa, empresa_id, fecha_contratacion, estado, granja_base_cod, foto_ruta)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$hoy = date('Y-m-d');
$creados = 0;
foreach ($ejemplos as $e) {
    $chk = $pdo->prepare('SELECT id FROM bio_personal WHERE dni = ?');
    $chk->execute([$e['dni']]);
    if ($chk->fetch()) {
        $up = $pdo->prepare(
            "UPDATE bio_personal SET empresa = 'TERCERO', empresa_id = ?, estado = 'ACTIVO', cargo = ? WHERE dni = ?"
        );
        $up->execute([$empresaId, $e['cargo'], $e['dni']]);
        echo "Actualizado DNI {$e['dni']} → empresa_id {$empresaId}\n";
        continue;
    }
    $ins->execute([
        $e['dni'],
        $e['nombres'],
        $e['apellidos'],
        $e['cargo'],
        'TERCERO',
        $empresaId,
        $hoy,
        'ACTIVO',
        $granja,
        '',
    ]);
    echo "Creado {$e['apellidos']}, {$e['nombres']} (DNI {$e['dni']})\n";
    $creados++;
}

echo "Listo. Nuevos: {$creados}\n";
