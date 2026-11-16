<?php

declare(strict_types=1);

namespace Bio\Api\Support;

final class SanidadAuthBridge
{
    /**
     * @return array{codigo: string, nombre: string}|null
     */
    public static function authenticate(string $usuario, string $clave): ?array
    {
        $lib = dirname(__DIR__, 4) . '/sanidad/includes/usuario_auth_lib.php';
        $connFile = dirname(__DIR__, 4) . '/conexion_grs/conexion.php';
        if (!is_file($lib) || !is_file($connFile)) {
            return null;
        }
        require_once $connFile;
        require_once $lib;

        $conn = conectar_joya_mysqli();
        if (!$conn instanceof \mysqli) {
            return null;
        }

        return sip_password_authenticate($conn, $usuario, $clave);
    }

    /**
     * Usuario activo en `usuario` (misma tabla para GRS y terceros).
     *
     * @return array{codigo: string, nombre: string}|null
     */
    public static function userByCodigo(string $codigo): ?array
    {
        $lib = dirname(__DIR__, 4) . '/sanidad/includes/usuario_auth_lib.php';
        $connFile = dirname(__DIR__, 4) . '/conexion_grs/conexion.php';
        if (!is_file($lib) || !is_file($connFile)) {
            return null;
        }
        require_once $connFile;
        require_once $lib;

        $conn = conectar_joya_mysqli();
        if (!$conn instanceof \mysqli) {
            return null;
        }

        $dbCodigo = sip_usuario_codigo_db($conn, $codigo);
        if ($dbCodigo === null) {
            return null;
        }

        $norm = sip_usuario_codigo_norm($codigo);
        $st = $conn->prepare(
            "SELECT codigo, nombre FROM usuario WHERE UPPER(TRIM(codigo)) = ? AND estado = 'A' LIMIT 1"
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('s', $norm);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return null;
        }

        return [
            'codigo' => (string) $row['codigo'],
            'nombre' => (string) $row['nombre'],
        ];
    }
}
