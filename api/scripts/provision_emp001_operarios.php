<?php

declare(strict_types=1);

/**
 * Operarios EMP001: solo tabla `usuario` + libtri = DNI (bio_personal.empresa_id).
 * Uso: php provision_emp001_operarios.php
 */
$root = dirname(__DIR__, 3);
require_once $root . '/conexion_grs/conexion.php';
require_once $root . '/sanidad/modules/configuracion/roles_permisos/roles_permisos_lib.php';
require_once $root . '/sanidad/includes/usuario_auth_lib.php';

$conn = conectar_joya_mysqli();
if (!$conn instanceof mysqli) {
    fwrite(STDERR, "Sin conexión\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

$operarios = [
    ['usuario' => 'EMPT01', 'nombre' => 'Tomas Reyes Almacen', 'dni' => '42222333'],
    ['usuario' => 'EMPT02', 'nombre' => 'Julio Talledo Almacen', 'dni' => '43777888'],
];

foreach ($operarios as $op) {
    $cod = $op['usuario'];
    $clave = $op['dni'];
    if (!rp_usuario_existe($conn, $cod)) {
        $ins = rp_usuario_insert_sin_dni($conn, $cod, $op['nombre'], $clave, null, false, '');
        if (empty($ins['success'])) {
            fwrite(STDERR, "Usuario $cod: " . ($ins['message'] ?? 'error') . "\n");
            exit(1);
        }
        echo "Usuario creado: $cod\n";
    } else {
        sip_password_set($conn, $cod, $clave);
        echo "Usuario existente: $cod (clave = DNI)\n";
    }
    $st = $conn->prepare('UPDATE usuario SET libtri = ? WHERE UPPER(TRIM(codigo)) = ? AND estado = ?');
    $estado = 'A';
    $st->bind_param('sss', $op['dni'], $cod, $estado);
    $st->execute();
    $st->close();
    echo "  libtri = {$op['dni']} (personal EMP001)\n";
}

echo "Listo.\n";