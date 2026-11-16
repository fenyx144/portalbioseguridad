<?php

declare(strict_types=1);

namespace Bio\Api\Support;

final class SanidadEmpresaBridge
{
    /**
     * Crea usuario SIP tipo proveedor (clave inicial = RUC), al estilo roles_permisos.
     *
     * @return array{ok: bool, message?: string, codigo?: string, nombre?: string}
     */
    public static function crearUsuarioEmpresa(string $codigo, string $ruc, string $razonSocial, string $codigoSesion): array
    {
        $lib = dirname(__DIR__, 4) . '/sanidad/modules/configuracion/roles_permisos/roles_permisos_lib.php';
        $connFile = dirname(__DIR__, 4) . '/conexion_grs/conexion.php';
        if (!is_file($lib) || !is_file($connFile)) {
            return ['ok' => false, 'message' => 'No se encontró la librería de usuarios Sanidad'];
        }
        require_once $connFile;
        require_once $lib;

        $conn = conectar_joya_mysqli();
        if (!$conn instanceof \mysqli) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos'];
        }

        if (!function_exists('rp_usuario_proveedor_create')) {
            return ['ok' => false, 'message' => 'Función de proveedor no disponible en Sanidad'];
        }

        $r = rp_usuario_proveedor_create($conn, [
            'codigo' => $codigo,
            'ruc' => $ruc,
            'razonsocial' => $razonSocial,
            'procesos' => [],
            'proceso_otro' => 'Bioseguridad',
        ], $codigoSesion);

        if (empty($r['success'])) {
            return ['ok' => false, 'message' => (string) ($r['message'] ?? 'No se pudo crear el usuario')];
        }

        return [
            'ok' => true,
            'codigo' => (string) ($r['codigo'] ?? $codigo),
            'nombre' => (string) ($r['nombre'] ?? $razonSocial),
        ];
    }
}
