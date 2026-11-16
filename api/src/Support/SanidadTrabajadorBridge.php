<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Bootstrap\Database;
use PDO;
use Throwable;

/**
 * Alta del trabajador en la tabla `usuario` (login de la app) y vínculo auxiliar.
 * La clave inicial es el DNI cuando la librería Sanidad está disponible.
 */
final class SanidadTrabajadorBridge
{
    /**
     * @return array{ok:bool, usuario_cod?:string, message?:string, clave_inicial?:string}
     */
    public static function asegurar(string $dni, string $nombre, int $personalId, ?int $empresaId): array
    {
        $dni = trim($dni);
        $nombre = trim($nombre);
        if ($dni === '' || $nombre === '' || $personalId < 1) {
            return ['ok' => false, 'message' => 'DNI y nombre obligatorios'];
        }

        $via = self::viaSanidad($dni, $nombre, $personalId);
        $result = $via ?? self::viaPdo($dni, $nombre, $personalId);
        if (!empty($result['ok']) && !empty($result['usuario_cod']) && $empresaId) {
            self::vincularAcceso((int) $empresaId, (string) $result['usuario_cod'], $personalId);
        }

        return $result;
    }

    /** @return array{ok:bool, usuario_cod?:string, message?:string, clave_inicial?:string}|null */
    private static function viaSanidad(string $dni, string $nombre, int $personalId): ?array
    {
        $lib = dirname(__DIR__, 4) . '/sanidad/includes/usuario_auth_lib.php';
        $roles = dirname(__DIR__, 4) . '/sanidad/modules/configuracion/roles_permisos/roles_permisos_lib.php';
        $connFile = dirname(__DIR__, 4) . '/conexion_grs/conexion.php';
        if (!is_file($lib) || !is_file($roles) || !is_file($connFile)) {
            return null;
        }
        require_once $connFile;
        require_once $roles;
        require_once $lib;
        if (!function_exists('conectar_joya_mysqli')) {
            return null;
        }
        $conn = conectar_joya_mysqli();
        if (!$conn instanceof \mysqli) {
            return ['ok' => false, 'message' => 'Sin conexión a usuarios'];
        }

        $existente = self::codigoPorLibtriMysqli($conn, $dni);
        if ($existente !== null) {
            self::marcarLibtri($conn, $existente, $dni);

            return ['ok' => true, 'usuario_cod' => $existente, 'clave_inicial' => ''];
        }

        $codigo = self::codigoLibre(static function (string $cod) use ($conn): bool {
            if (function_exists('rp_usuario_existe')) {
                return (bool) rp_usuario_existe($conn, $cod);
            }
            $st = $conn->prepare('SELECT 1 FROM usuario WHERE UPPER(TRIM(codigo)) = ? LIMIT 1');
            if (!$st) {
                return false;
            }
            $st->bind_param('s', $cod);
            $st->execute();
            $ok = (bool) $st->get_result()->fetch_row();
            $st->close();

            return $ok;
        }, $personalId);

        if (!function_exists('rp_usuario_insert_sin_dni')) {
            return ['ok' => false, 'message' => 'No se pudo crear el usuario (función Sanidad ausente)'];
        }
        $ins = rp_usuario_insert_sin_dni($conn, $codigo, $nombre, $dni, null, false, '');
        if (empty($ins['success'])) {
            return ['ok' => false, 'message' => (string) ($ins['message'] ?? 'No se pudo crear el usuario')];
        }
        if (function_exists('sip_password_set')) {
            sip_password_set($conn, $codigo, $dni);
        }
        self::marcarLibtri($conn, $codigo, $dni);

        return ['ok' => true, 'usuario_cod' => $codigo, 'clave_inicial' => $dni];
    }

    /** @return array{ok:bool, usuario_cod?:string, message?:string, clave_inicial?:string} */
    private static function viaPdo(string $dni, string $nombre, int $personalId): array
    {
        try {
            $pdo = Database::pdo();
            $st = $pdo->prepare("SELECT codigo FROM usuario WHERE TRIM(libtri) = ? AND estado = 'A' LIMIT 1");
            $st->execute([$dni]);
            $row = $st->fetch();
            if ($row) {
                return ['ok' => true, 'usuario_cod' => strtoupper(trim((string) $row['codigo']))];
            }
            $cols = [];
            foreach ($pdo->query('SHOW COLUMNS FROM usuario') ?: [] as $c) {
                $cols[strtolower((string) $c['Field'])] = $c;
            }
            if (!isset($cols['codigo'])) {
                return ['ok' => false, 'message' => 'Tabla usuario sin columna codigo'];
            }
            $codigo = self::codigoLibre(static function (string $cod) use ($pdo): bool {
                $q = $pdo->prepare('SELECT 1 FROM usuario WHERE UPPER(TRIM(codigo)) = ? LIMIT 1');
                $q->execute([$cod]);

                return (bool) $q->fetch();
            }, $personalId);
            $fields = ['codigo' => $codigo];
            if (isset($cols['nombre'])) {
                $fields['nombre'] = substr($nombre, 0, 80);
            }
            if (isset($cols['estado'])) {
                $fields['estado'] = 'A';
            }
            if (isset($cols['libtri'])) {
                $fields['libtri'] = $dni;
            }
            $names = array_keys($fields);
            $marks = implode(',', array_fill(0, count($names), '?'));
            $sql = 'INSERT INTO usuario (' . implode(',', $names) . ') VALUES (' . $marks . ')';
            $pdo->prepare($sql)->execute(array_values($fields));

            return [
                'ok' => true,
                'usuario_cod' => $codigo,
                'message' => 'Usuario creado sin clave Sanidad (librería no disponible en este entorno)',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo registrar en usuario: ' . $e->getMessage()];
        }
    }

    private static function codigoPorLibtriMysqli(\mysqli $conn, string $dni): ?string
    {
        $st = $conn->prepare("SELECT codigo FROM usuario WHERE TRIM(libtri) = ? AND estado = 'A' LIMIT 1");
        if (!$st) {
            return null;
        }
        $st->bind_param('s', $dni);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) {
            return null;
        }

        return strtoupper(trim((string) $row['codigo']));
    }

    private static function marcarLibtri(\mysqli $conn, string $codigo, string $dni): void
    {
        $st = $conn->prepare("UPDATE usuario SET libtri = ? WHERE UPPER(TRIM(codigo)) = ? AND estado = 'A'");
        if (!$st) {
            return;
        }
        $cod = strtoupper(trim($codigo));
        $st->bind_param('ss', $dni, $cod);
        $st->execute();
        $st->close();
    }

    /** @param callable(string):bool $ocupado */
    private static function codigoLibre(callable $ocupado, int $personalId): string
    {
        $base = $personalId % 10000;
        for ($i = 0; $i < 10000; $i++) {
            $cod = 'EMPT' . str_pad((string) (($base + $i) % 10000), 4, '0', STR_PAD_LEFT);
            if (!$ocupado($cod)) {
                return $cod;
            }
        }

        return 'EMPT' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    private static function vincularAcceso(int $empresaId, string $usuarioCod, int $personalId): void
    {
        try {
            $pdo = Database::pdo();
            $pdo->prepare(
                'INSERT INTO bio_empresa_acceso (empresa_id, usuario_cod, personal_id, estado)
                 VALUES (?, ?, ?, \'ACTIVO\')
                 ON DUPLICATE KEY UPDATE empresa_id = VALUES(empresa_id), personal_id = VALUES(personal_id), estado = \'ACTIVO\''
            )->execute([$empresaId, strtoupper(substr($usuarioCod, 0, 10)), $personalId]);
        } catch (Throwable $e) {
            // Tabla auxiliar ausente: el vínculo principal sigue siendo usuario.libtri = dni.
        }
    }
}
