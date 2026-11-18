<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Bootstrap\Database;
use Bio\Api\Repositories\EmpresaRepository;
use PDO;
use Throwable;

/**
 * Quién puede usar el portal: GRS (sin empresa) o una empresa de bioseguridad
 * (bio_empresa, acceso ACTIVO o el mismo RUC en san_usuario_proveedor).
 */
final class BioseguridadAcceso
{
    /**
     * @param array{codigo: string, nombre: string} $base
     * @return array{ok: bool, message: string, profile: array<string, mixed>}
     */
    public static function admitir(array $base): array
    {
        $codigo = strtoupper(trim((string) ($base['codigo'] ?? '')));
        $empresa = null;
        try {
            $empresa = (new EmpresaRepository())->findByUsuarioCod($codigo);
        } catch (Throwable $e) {
            $empresa = null;
        }
        if ($empresa === null) {
            $empresa = self::empresaPorRucProveedor($codigo);
        }
        if ($empresa !== null) {
            return [
                'ok' => true,
                'message' => '',
                'profile' => array_merge($base, [
                    'actor' => 'empresa',
                    'empresa_id' => (int) $empresa['id'],
                    'empresa_codigo' => (string) $empresa['codigo'],
                    'empresa_nombre' => (string) $empresa['razon_social'],
                    'empresa_ruc' => self::digitos((string) ($empresa['ruc'] ?? '')),
                ]),
            ];
        }
        if (self::esProveedorSanidad($codigo)) {
            return [
                'ok' => false,
                'message' => 'Su usuario no pertenece a bioseguridad.',
                'profile' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'profile' => array_merge($base, [
                'actor' => 'grs',
                'empresa_id' => null,
                'empresa_codigo' => null,
                'empresa_nombre' => null,
                'empresa_ruc' => '',
            ]),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function empresaPorRucProveedor(string $codigo): ?array
    {
        $prov = self::proveedor($codigo);
        if ($prov === null || $prov['ruc'] === '') {
            return null;
        }
        try {
            $st = Database::pdo()->query("SELECT * FROM bio_empresa WHERE estado = 'ACTIVO'");
            if ($st === false) {
                return null;
            }
            foreach ($st->fetchAll() as $row) {
                if (self::digitos((string) ($row['ruc'] ?? '')) === $prov['ruc']) {
                    return $row;
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private static function esProveedorSanidad(string $codigo): bool
    {
        return self::proveedor($codigo) !== null;
    }

    /** @return array{ruc: string, razonsocial: string, proceso: string}|null */
    public static function proveedor(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        try {
            $st = Database::pdo()->prepare(
                'SELECT TRIM(ruc) AS ruc, TRIM(razonsocial) AS razonsocial, TRIM(proceso) AS proceso
                   FROM san_usuario_proveedor
                  WHERE UPPER(TRIM(codigo)) = ?
                  LIMIT 1'
            );
            $st->execute([$codigo]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        if ($row === false) {
            return null;
        }
        $ruc = self::digitos((string) ($row['ruc'] ?? ''));
        if (strlen($ruc) !== 11) {
            return ['ruc' => '', 'razonsocial' => trim((string) $row['razonsocial']), 'proceso' => trim((string) $row['proceso'])];
        }

        return [
            'ruc' => $ruc,
            'razonsocial' => trim((string) $row['razonsocial']),
            'proceso' => trim((string) $row['proceso']),
        ];
    }

    public static function digitos(string $raw): string
    {
        return preg_replace('/\D/', '', $raw) ?? '';
    }
}
