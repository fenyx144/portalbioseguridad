<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;
use Throwable;

/** Lee y escribe las tablas de planificación de proveedor que ya usa Sanidad. */
final class ProveedorPlanRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(string $ruc, string $desde, string $hasta, string $granja3): array
    {
        $sql = 'SELECT id, fecha, granja, campania, numPersonas, motivo, observacion,
                       proveedorRuc, proveedor, usuarioRegistrador
                  FROM san_fact_planvisita_cab
                 WHERE (? = \'\' OR TRIM(proveedorRuc) = ?)
                   AND (? <> \'\' OR TRIM(proveedorRuc) <> \'\')
                   AND (? = \'\' OR fecha >= ?)
                   AND (? = \'\' OR fecha <= ?)
                   AND (? = \'\' OR LPAD(TRIM(granja), 3, \'0\') = ?)
                 ORDER BY fecha DESC, id DESC
                 LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute([$ruc, $ruc, $ruc, $desde, $desde, $hasta, $hasta, $granja3, $granja3]);

        return $st->fetchAll();
    }

    /** @param list<int> $ids @return array<int, list<array{dni: string, nombre: string}>> */
    public function personasDe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT idCab, TRIM(dni) AS dni, TRIM(nombre) AS nombre
               FROM san_fact_planvisita_det WHERE idCab IN ({$marks}) ORDER BY posicion, id"
        );
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['idCab']][] = ['dni' => (string) $r['dni'], 'nombre' => (string) $r['nombre']];
        }

        return $out;
    }

    /** @param list<int> $ids @return array<int, list<array{placa: string, ingresa: int}>> */
    public function vehiculosDe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $this->pdo->prepare(
                "SELECT idCab, TRIM(placa) AS placa, ingresa
                   FROM san_fact_planvisita_veh WHERE idCab IN ({$marks}) ORDER BY posicion, id"
            );
            $st->execute($ids);
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['idCab']][] = [
                'placa' => (string) $r['placa'],
                'ingresa' => (int) ($r['ingresa'] ?? 1) === 0 ? 0 : 1,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function cabecera(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, fecha, granja, campania, numPersonas, motivo, observacion, proveedorRuc, proveedor
               FROM san_fact_planvisita_cab WHERE id = ? LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row === false ? null : $row;
    }

    public function campania(string $granja3): string
    {
        $st = $this->pdo->prepare(
            "SELECT TRIM(codigo) AS codigo FROM ccos
              WHERE swac = 'A' AND LEFT(TRIM(codigo), 3) = ?
              ORDER BY codigo DESC LIMIT 1"
        );
        $st->execute([$granja3]);
        $cod = $st->fetchColumn();
        if ($cod === false) {
            return '';
        }
        $cod = trim((string) $cod);

        return strlen($cod) >= 6 ? substr($cod, 3, 3) : '';
    }

    public function siguienteId(): int
    {
        $n = $this->pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM san_fact_planvisita_cab');
        $row = $n ? $n->fetch() : false;

        return max(1, (int) ($row['n'] ?? 1));
    }

    /**
     * @param array<string, mixed> $cab
     * @param list<array{dni: string, nombre: string}> $personas
     * @param list<array{placa: string, ingresa: int}> $vehiculos
     */
    public function insertar(array $cab, array $personas, array $vehiculos): int
    {
        $id = $this->siguienteId();
        $st = $this->pdo->prepare(
            'INSERT INTO san_fact_planvisita_cab
                (id, fecha, granja, campania, numPersonas, proveedorRuc, proveedor, areaProveedor, motivo, observacion, usuarioRegistrador, fechaHoraRegistro)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $id,
            $cab['fecha'],
            $cab['granja'],
            $cab['campania'],
            count($personas),
            $cab['ruc'],
            $cab['razon'],
            'PROVEEDOR',
            $cab['motivo'],
            $cab['observacion'],
            $cab['usuario'],
            date('Y-m-d H:i:s'),
        ]);
        $det = $this->pdo->prepare(
            'INSERT INTO san_fact_planvisita_det (id, idCab, posicion, dni, nombre) VALUES (?,?,?,?,?)'
        );
        $pos = 0;
        foreach ($personas as $p) {
            $pos++;
            $det->execute([self::uuid(), $id, $pos, $p['dni'], $p['nombre']]);
        }
        if ($vehiculos !== []) {
            $veh = $this->pdo->prepare(
                'INSERT INTO san_fact_planvisita_veh (id, idCab, posicion, placa, ingresa) VALUES (?,?,?,?,?)'
            );
            $pos = 0;
            foreach ($vehiculos as $v) {
                $pos++;
                $veh->execute([self::uuid(), $id, $pos, $v['placa'], $v['ingresa']]);
            }
        }

        return $id;
    }

    /** @param list<array{dni: string, nombre: string}> $personas @param list<array{placa: string, ingresa: int}> $vehiculos */
    public function reemplazarDetalle(int $id, string $motivo, array $personas, array $vehiculos): void
    {
        $st = $this->pdo->prepare(
            'UPDATE san_fact_planvisita_cab SET motivo = ?, observacion = ?, numPersonas = ? WHERE id = ?'
        );
        $st->execute([$motivo, $motivo, count($personas), $id]);
        $this->pdo->prepare('DELETE FROM san_fact_planvisita_det WHERE idCab = ?')->execute([$id]);
        $det = $this->pdo->prepare(
            'INSERT INTO san_fact_planvisita_det (id, idCab, posicion, dni, nombre) VALUES (?,?,?,?,?)'
        );
        $pos = 0;
        foreach ($personas as $p) {
            $pos++;
            $det->execute([self::uuid(), $id, $pos, $p['dni'], $p['nombre']]);
        }
        try {
            $this->pdo->prepare('DELETE FROM san_fact_planvisita_veh WHERE idCab = ?')->execute([$id]);
            $veh = $this->pdo->prepare(
                'INSERT INTO san_fact_planvisita_veh (id, idCab, posicion, placa, ingresa) VALUES (?,?,?,?,?)'
            );
            $pos = 0;
            foreach ($vehiculos as $v) {
                $pos++;
                $veh->execute([self::uuid(), $id, $pos, $v['placa'], $v['ingresa']]);
            }
        } catch (Throwable $e) {
        }
    }

    public function eliminar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM san_fact_planvisita_det WHERE idCab = ?')->execute([$id]);
        try {
            $this->pdo->prepare('DELETE FROM san_fact_planvisita_veh WHERE idCab = ?')->execute([$id]);
        } catch (Throwable $e) {
        }
        $this->pdo->prepare('DELETE FROM san_fact_planvisita_cab WHERE id = ?')->execute([$id]);
    }

    /** @return list<array<string, mixed>> */
    public function personal(string $ruc): array
    {
        $st = $this->pdo->prepare(
            'SELECT TRIM(dni) AS dni, TRIM(nombre) AS nombre, activo
               FROM san_personal_proveedor WHERE TRIM(ruc) = ? ORDER BY nombre, dni'
        );
        $st->execute([$ruc]);

        return $st->fetchAll();
    }

    public function guardarPersonal(string $ruc, string $dni, string $nombre, string $usuario): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO san_personal_proveedor (ruc, dni, nombre, activo, usuarioModificacion, fechaHoraModificacion)
             VALUES (?,?,?,1,?,NOW())
             ON DUPLICATE KEY UPDATE
                nombre = IF(VALUES(nombre) = \'\', nombre, VALUES(nombre)),
                activo = 1,
                usuarioModificacion = VALUES(usuarioModificacion),
                fechaHoraModificacion = NOW()'
        );
        $st->execute([$ruc, $dni, $nombre, substr($usuario, 0, 25)]);
    }

    public function bajaPersonal(string $ruc, string $dni, string $usuario): void
    {
        $st = $this->pdo->prepare(
            'UPDATE san_personal_proveedor SET activo = 0, usuarioModificacion = ?, fechaHoraModificacion = NOW()
              WHERE TRIM(ruc) = ? AND TRIM(dni) = ?'
        );
        $st->execute([substr($usuario, 0, 25), $ruc, $dni]);
    }

    /** @return list<array<string, mixed>> */
    public function vehiculos(string $ruc): array
    {
        $st = $this->pdo->prepare(
            'SELECT TRIM(placa) AS placa, TRIM(descripcion) AS descripcion, activo
               FROM san_vehiculo_proveedor WHERE TRIM(ruc) = ? ORDER BY placa'
        );
        $st->execute([$ruc]);

        return $st->fetchAll();
    }

    public function guardarVehiculo(string $ruc, string $placa, string $descripcion, string $usuario): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO san_vehiculo_proveedor (ruc, placa, descripcion, activo, usuarioModificacion, fechaHoraModificacion)
             VALUES (?,?,?,1,?,NOW())
             ON DUPLICATE KEY UPDATE
                descripcion = IF(VALUES(descripcion) = \'\', descripcion, VALUES(descripcion)),
                activo = 1,
                usuarioModificacion = VALUES(usuarioModificacion),
                fechaHoraModificacion = NOW()'
        );
        $st->execute([$ruc, $placa, $descripcion, substr($usuario, 0, 25)]);
    }

    public function bajaVehiculo(string $ruc, string $placa, string $usuario): void
    {
        $st = $this->pdo->prepare(
            'UPDATE san_vehiculo_proveedor SET activo = 0, usuarioModificacion = ?, fechaHoraModificacion = NOW()
              WHERE TRIM(ruc) = ? AND TRIM(placa) = ?'
        );
        $st->execute([substr($usuario, 0, 25), $ruc, $placa]);
    }

    /** @return array{ruc: string, razonsocial: string, proceso: string}|null */
    public function proveedorDeUsuario(string $codigo): ?array
    {
        return \Bio\Api\Support\BioseguridadAcceso::proveedor($codigo);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }
}
