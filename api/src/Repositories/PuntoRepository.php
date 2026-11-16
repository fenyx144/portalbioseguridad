<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class PuntoRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listar(?string $granjaCod, ?string $estado, ?string $tipo): array
    {
        $where = ['1=1'];
        $params = [];
        if ($granjaCod !== null && $granjaCod !== '') {
            $where[] = 'granja_cod = ?';
            $params[] = $granjaCod;
        }
        if ($estado !== null && $estado !== '') {
            $where[] = 'estado = ?';
            $params[] = $estado;
        }
        if ($tipo !== null && $tipo !== '') {
            $where[] = 'tipo_cod = ?';
            $params[] = $tipo;
        }
        $sql = 'SELECT * FROM bio_puntos WHERE ' . implode(' AND ', $where) . ' ORDER BY granja_cod, nombre';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function attachRequeridosTurno(array $items): array
    {
        if ($items === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($items), '?'));
        $ids = array_map(static function ($p) {
            return (int) $p['id'];
        }, $items);
        $st = $this->pdo->prepare(
            "SELECT punto_id, turno_cod, personal_requerido FROM bio_punto_turno
             WHERE punto_id IN ({$marks})"
        );
        $st->execute($ids);
        $map = [];
        foreach ($st->fetchAll() as $row) {
            $pid = (int) $row['punto_id'];
            if (!isset($map[$pid])) {
                $map[$pid] = ['personal_dia' => 0, 'personal_noche' => 0];
            }
            if ($row['turno_cod'] === 'DIA') {
                $map[$pid]['personal_dia'] = (int) $row['personal_requerido'];
            }
            if ($row['turno_cod'] === 'NOCHE') {
                $map[$pid]['personal_noche'] = (int) $row['personal_requerido'];
            }
        }
        $stC = $this->pdo->prepare(
            "SELECT punto_id, turno_cod, cargo, cantidad FROM bio_punto_cargo_req
             WHERE punto_id IN ({$marks}) ORDER BY cargo"
        );
        $stC->execute($ids);
        $cargosMap = [];
        foreach ($stC->fetchAll() as $row) {
            $pid = (int) $row['punto_id'];
            $tc = (string) $row['turno_cod'];
            if (!isset($cargosMap[$pid])) {
                $cargosMap[$pid] = ['DIA' => [], 'NOCHE' => []];
            }
            $cargosMap[$pid][$tc][] = (string) $row['cargo'] . '×' . (int) $row['cantidad'];
        }
        foreach ($items as &$p) {
            $pid = (int) $p['id'];
            $p['personal_dia'] = $map[$pid]['personal_dia'] ?? 0;
            $p['personal_noche'] = $map[$pid]['personal_noche'] ?? 0;
            $p['cargos_dia'] = isset($cargosMap[$pid]['DIA']) ? implode(', ', $cargosMap[$pid]['DIA']) : '';
            $p['cargos_noche'] = isset($cargosMap[$pid]['NOCHE']) ? implode(', ', $cargosMap[$pid]['NOCHE']) : '';
        }
        unset($p);

        return $items;
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_puntos WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function crear(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_puntos (granja_cod, codigo, nombre, tipo_cod, ubicacion, estado)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['granja_cod'],
            $data['codigo'],
            $data['nombre'],
            $data['tipo_cod'],
            $data['ubicacion'] ?? '',
            $data['estado'] ?? 'ACTIVO',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function actualizar(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_puntos SET codigo = ?, nombre = ?, tipo_cod = ?, ubicacion = ?, estado = ? WHERE id = ?'
        );
        $st->execute([
            $data['codigo'],
            $data['nombre'],
            $data['tipo_cod'],
            $data['ubicacion'] ?? '',
            $data['estado'],
            $id,
        ]);
    }

    public function desactivar(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE bio_puntos SET estado = 'INACTIVO' WHERE id = ?");
        $st->execute([$id]);
    }

    public function countAsignaciones(int $puntoId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM bio_asignacion WHERE punto_id = ?');
        $st->execute([$puntoId]);

        return (int) $st->fetchColumn();
    }

    public function countAsistenciasPorPunto(int $puntoId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bio_asistencia ba
             JOIN bio_asignacion a ON a.id = ba.asignacion_id WHERE a.punto_id = ?'
        );
        $st->execute([$puntoId]);

        return (int) $st->fetchColumn();
    }

    public function existeNombre(string $granjaCod, string $nombre, ?int $excludeId): bool
    {
        $sql = 'SELECT id FROM bio_puntos WHERE granja_cod = ? AND nombre = ?';
        $params = [$granjaCod, $nombre];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool) $st->fetch();
    }

    public function siguienteCodigo(string $granjaCod, string $tipoCod): string
    {
        $prefijo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $tipoCod), 0, 3));
        if ($prefijo === '') {
            $prefijo = 'PTO';
        }
        $st = $this->pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)) FROM bio_puntos
             WHERE granja_cod = ? AND codigo REGEXP ?"
        );
        $st->execute([strlen($prefijo) + 2, $granjaCod, '^' . $prefijo . '-[0-9]+$']);
        $n = (int) $st->fetchColumn() + 1;

        return $prefijo . '-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    public function existeCodigoNombre(int $granjaCodIgnored, string $granjaCod, string $codigo, string $nombre, ?int $excludeId): bool
    {
        $sql = 'SELECT id FROM bio_puntos WHERE granja_cod = ? AND (codigo = ? OR nombre = ?)';
        $params = [$granjaCod, $codigo, $nombre];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool) $st->fetch();
    }

    /** @return list<array<string, mixed>> */
    public function turnos(int $puntoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT pt.*, tc.nombre AS turno_nombre, tc.hora_inicio, tc.hora_fin, tc.cruza_medianoche
             FROM bio_punto_turno pt
             JOIN bio_turno_catalogo tc ON tc.codigo = pt.turno_cod
             WHERE pt.punto_id = ?'
        );
        $st->execute([$puntoId]);

        return $st->fetchAll();
    }

    /**
     * Turnos de varios puntos en una sola consulta (mismo contenido que turnos()).
     *
     * @param list<int> $puntoIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function turnosPorPuntos(array $puntoIds): array
    {
        $ids = [];
        foreach ($puntoIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare(
            "SELECT pt.*, tc.nombre AS turno_nombre, tc.hora_inicio, tc.hora_fin, tc.cruza_medianoche
             FROM bio_punto_turno pt
             JOIN bio_turno_catalogo tc ON tc.codigo = pt.turno_cod
             WHERE pt.punto_id IN ({$marks})
             ORDER BY pt.punto_id, pt.id"
        );
        $st->execute(array_values($ids));
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int) $row['punto_id']][] = $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function cargosRequeridos(int $puntoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT turno_cod, cargo, cantidad FROM bio_punto_cargo_req WHERE punto_id = ? ORDER BY turno_cod, cargo'
        );
        $st->execute([$puntoId]);

        return $st->fetchAll();
    }

    /** @param list<array<string, mixed>> $rows */
    public function syncCargosRequeridos(int $puntoId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM bio_punto_cargo_req WHERE punto_id = ?')->execute([$puntoId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO bio_punto_cargo_req (punto_id, turno_cod, cargo, cantidad) VALUES (?, ?, ?, ?)'
        );
        $sums = ['DIA' => 0, 'NOCHE' => 0];
        foreach ($rows as $r) {
            $cargo = trim((string) ($r['cargo'] ?? ''));
            if ($cargo === '') {
                continue;
            }
            $turno = (string) ($r['turno_cod'] ?? '');
            if (!in_array($turno, ['DIA', 'NOCHE'], true)) {
                continue;
            }
            $cantidad = max(1, min(99, (int) ($r['cantidad'] ?? 1)));
            $ins->execute([$puntoId, $turno, $cargo, $cantidad]);
            $sums[$turno] += $cantidad;
        }
        $this->ensureTurnoCupos($puntoId, $sums);
    }

    /**
     * Reemplaza el detalle opcional de cargos sin tocar los cupos por turno.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function syncSoloCargos(int $puntoId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM bio_punto_cargo_req WHERE punto_id = ?')->execute([$puntoId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO bio_punto_cargo_req (punto_id, turno_cod, cargo, cantidad) VALUES (?, ?, ?, ?)'
        );
        foreach ($rows as $r) {
            $cargo = trim((string) ($r['cargo'] ?? ''));
            $turno = (string) ($r['turno_cod'] ?? '');
            if ($cargo === '' || !in_array($turno, ['DIA', 'NOCHE'], true)) {
                continue;
            }
            $ins->execute([$puntoId, $turno, $cargo, max(1, min(99, (int) ($r['cantidad'] ?? 1)))]);
        }
    }

    /** @param array<string, int> $sums */
    private function ensureTurnoCupos(int $puntoId, array $sums): void
    {
        foreach (['DIA', 'NOCHE'] as $turno) {
            $req = (int) ($sums[$turno] ?? 0);
            $st = $this->pdo->prepare('SELECT id FROM bio_punto_turno WHERE punto_id = ? AND turno_cod = ?');
            $st->execute([$puntoId, $turno]);
            if ($st->fetch()) {
                $this->pdo->prepare(
                    'UPDATE bio_punto_turno SET personal_requerido = ? WHERE punto_id = ? AND turno_cod = ?'
                )->execute([$req, $puntoId, $turno]);
            } else {
                $this->pdo->prepare(
                    'INSERT INTO bio_punto_turno (punto_id, turno_cod, personal_requerido) VALUES (?, ?, ?)'
                )->execute([$puntoId, $turno, $req]);
            }
        }
    }

    /** @param list<array<string, mixed>> $turnos */
    public function syncTurnos(int $puntoId, array $turnos): void
    {
        $this->pdo->prepare('DELETE FROM bio_punto_turno WHERE punto_id = ?')->execute([$puntoId]);
        $st = $this->pdo->prepare(
            'INSERT INTO bio_punto_turno (punto_id, turno_cod, personal_requerido, hora_inicio_override, hora_fin_override)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($turnos as $t) {
            $st->execute([
                $puntoId,
                $t['turno_cod'],
                (int) $t['personal_requerido'],
                $t['hora_inicio_override'] ?? null,
                $t['hora_fin_override'] ?? null,
            ]);
        }
    }

    /** @return list<int> */
    public function capacitacionIds(int $puntoId): array
    {
        $st = $this->pdo->prepare('SELECT capacitacion_id FROM bio_punto_capacitacion WHERE punto_id = ?');
        $st->execute([$puntoId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $ids */
    public function syncCapacitaciones(int $puntoId, array $ids): void
    {
        $this->pdo->prepare('DELETE FROM bio_punto_capacitacion WHERE punto_id = ?')->execute([$puntoId]);
        $st = $this->pdo->prepare('INSERT INTO bio_punto_capacitacion (punto_id, capacitacion_id) VALUES (?, ?)');
        foreach ($ids as $id) {
            $st->execute([$puntoId, $id]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function tipos(): array
    {
        $st = $this->pdo->query("SELECT codigo, nombre FROM bio_punto_tipo WHERE codigo <> 'BALANZA' ORDER BY nombre");

        return $st ? $st->fetchAll() : [];
    }

    /** @return list<int> */
    public function idsPorGranja(string $granjaCod): array
    {
        $st = $this->pdo->prepare('SELECT id FROM bio_puntos WHERE granja_cod = ?');
        $st->execute([$granjaCod]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
