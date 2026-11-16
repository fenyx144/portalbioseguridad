<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class PlanificacionRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @param list<string> $granjas */
    public function puntosActivos(array $granjas, ?string $tipo): array
    {
        if ($granjas === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($granjas), '?'));
        $params = $granjas;
        $sql = "SELECT p.* FROM bio_puntos p WHERE p.estado = 'ACTIVO' AND p.granja_cod IN ({$marks})";
        if ($tipo !== null && $tipo !== '') {
            $sql .= ' AND p.tipo_cod = ?';
            $params[] = $tipo;
        }
        $sql .= ' ORDER BY p.granja_cod, p.nombre';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @param list<string> $granjas */
    public function asignacionesRango(string $desde, string $hasta, array $granjas): array
    {
        if ($granjas === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($granjas), '?'));
        $params = array_merge([$desde, $hasta], $granjas);
        $st = $this->pdo->prepare(
            "SELECT a.*, a.origen, p.granja_cod, p.nombre AS punto_nombre, p.tipo_cod,
                    per.nombres, per.apellidos, per.cargo, per.dni, per.empresa, per.empresa_id,
                    per.estado AS personal_estado, per.granja_base_cod,
                    be.codigo AS empresa_codigo, be.razon_social AS empresa_razon_social,
                    r.id AS reemplazo_registro_id, r.motivo AS reemplazo_motivo, r.personal_original_id,
                    po.nombres AS original_nombres, po.apellidos AS original_apellidos, po.cargo AS original_cargo
             FROM bio_asignacion a
             JOIN bio_puntos p ON p.id = a.punto_id
             JOIN bio_personal per ON per.id = a.personal_id
             LEFT JOIN bio_empresa be ON be.id = per.empresa_id
             LEFT JOIN bio_reemplazo r ON r.id = a.reemplazo_id
             LEFT JOIN bio_personal po ON po.id = r.personal_original_id
             WHERE a.fecha BETWEEN ? AND ? AND p.granja_cod IN ({$marks})
             ORDER BY a.fecha, a.turno_cod"
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    public function contarAsignacionesCelda(int $puntoId, string $fecha, string $turnoCod, ?int $excludeId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM bio_asignacion WHERE punto_id = ? AND fecha = ? AND turno_cod = ?';
        $params = [$puntoId, $fecha, $turnoCod];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }

    public function findAsignacion(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, a.origen, per.empresa_id, per.empresa AS personal_empresa
             FROM bio_asignacion a
             JOIN bio_personal per ON per.id = a.personal_id
             WHERE a.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function crearAsignacion(int $personalId, int $puntoId, string $fecha, string $turnoCod, string $usuario, string $origen = 'EMPRESA'): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_asignacion (personal_id, punto_id, fecha, turno_cod, origen, usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$personalId, $puntoId, $fecha, $turnoCod, $origen, $usuario]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarAsignacion(int $id, int $personalId, int $puntoId, string $fecha, string $turnoCod): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_asignacion SET personal_id = ?, punto_id = ?, fecha = ?, turno_cod = ? WHERE id = ?'
        );
        $st->execute([$personalId, $puntoId, $fecha, $turnoCod, $id]);
    }

    public function eliminarAsignacion(int $id): void
    {
        $this->pdo->prepare('DELETE FROM bio_asignacion WHERE id = ?')->execute([$id]);
    }

    /** @return list<array<string, mixed>> */
    public function asignacionesPersonaEnRango(int $personalId, string $desde, string $hasta, ?int $excludeId = null): array
    {
        $sql = 'SELECT a.*, p.granja_cod, p.nombre AS punto_nombre FROM bio_asignacion a JOIN bio_puntos p ON p.id = a.punto_id
                WHERE a.personal_id = ? AND a.fecha BETWEEN ? AND ?';
        $params = [$personalId, $desde, $hasta];
        if ($excludeId !== null) {
            $sql .= ' AND a.id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /**
     * @param list<int> $personalIds
     * @return list<array<string, mixed>>
     */
    public function asignacionesPersonasEnRango(array $personalIds, string $desde, string $hasta): array
    {
        if ($personalIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($personalIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT a.*, p.granja_cod, p.nombre AS punto_nombre FROM bio_asignacion a JOIN bio_puntos p ON p.id = a.punto_id
             WHERE a.personal_id IN ({$marks}) AND a.fecha BETWEEN ? AND ?"
        );
        $st->execute(array_merge($personalIds, [$desde, $hasta]));

        return $st->fetchAll();
    }

    /**
     * @param list<int> $puntoIds
     * @return list<array<string, mixed>>
     */
    public function puntoTurnos(array $puntoIds): array
    {
        if ($puntoIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($puntoIds), '?'));
        $st = $this->pdo->prepare("SELECT * FROM bio_punto_turno WHERE punto_id IN ({$marks})");
        $st->execute($puntoIds);

        return $st->fetchAll();
    }

    public function turnoCatalogo(): array
    {
        $st = $this->pdo->query('SELECT * FROM bio_turno_catalogo');

        return $st ? $st->fetchAll() : [];
    }

    public function puntoTurno(int $puntoId, string $turnoCod): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_punto_turno WHERE punto_id = ? AND turno_cod = ?');
        $st->execute([$puntoId, $turnoCod]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function copiarSemana(string $granjaCod, string $origen, string $destino, string $usuario): int
    {
        $st = $this->pdo->prepare(
            'SELECT a.personal_id, a.punto_id, a.turno_cod, a.fecha, a.origen
             FROM bio_asignacion a
             JOIN bio_puntos p ON p.id = a.punto_id
             WHERE p.granja_cod = ? AND a.fecha >= ? AND a.fecha < DATE_ADD(?, INTERVAL 7 DAY)'
        );
        $st->execute([$granjaCod, $origen, $origen]);
        $rows = $st->fetchAll();
        $count = 0;
        $diffDays = (new \DateTimeImmutable($destino))->diff(new \DateTimeImmutable($origen))->days;
        $ins = $this->pdo->prepare(
            'INSERT INTO bio_asignacion (personal_id, punto_id, fecha, turno_cod, origen, usuario_registro) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $r) {
            $newDate = (new \DateTimeImmutable($r['fecha']))->modify("+{$diffDays} days")->format('Y-m-d');
            $origenAsig = $r['origen'] ?? 'EMPRESA';
            $ins->execute([$r['personal_id'], $r['punto_id'], $newDate, $r['turno_cod'], $origenAsig, $usuario]);
            $count++;
        }
        $log = $this->pdo->prepare(
            'INSERT INTO bio_plan_semana_copia (granja_cod, semana_origen, semana_destino, usuario) VALUES (?, ?, ?, ?)'
        );
        $log->execute([$granjaCod, $origen, $destino, $usuario]);

        return $count;
    }

    public function existeAsignacion(int $personalId, int $puntoId, string $fecha, string $turnoCod): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM bio_asignacion WHERE personal_id = ? AND punto_id = ? AND fecha = ? AND turno_cod = ? LIMIT 1'
        );
        $st->execute([$personalId, $puntoId, $fecha, $turnoCod]);

        return (bool) $st->fetchColumn();
    }

    public function registrarCopiaSemana(string $granjaCod, string $origen, string $destino, string $usuario): void
    {
        $this->pdo->prepare(
            'INSERT INTO bio_plan_semana_copia (granja_cod, semana_origen, semana_destino, usuario) VALUES (?, ?, ?, ?)'
        )->execute([substr($granjaCod, 0, 20), $origen, $destino, $usuario]);
    }

    public function crearReemplazo(int $asignacionId, int $originalId, int $reemplazoId, string $motivo, string $usuario): int
    {
        $this->pdo->prepare(
            'UPDATE bio_asignacion SET personal_id = ?, es_reemplazo = 1 WHERE id = ?'
        )->execute([$reemplazoId, $asignacionId]);
        $st = $this->pdo->prepare(
            'INSERT INTO bio_reemplazo (asignacion_id, personal_original_id, personal_reemplazo_id, motivo, usuario_registro)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$asignacionId, $originalId, $reemplazoId, $motivo, $usuario]);
        $rid = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE bio_asignacion SET reemplazo_id = ? WHERE id = ?')->execute([$rid, $asignacionId]);

        return $rid;
    }
}
