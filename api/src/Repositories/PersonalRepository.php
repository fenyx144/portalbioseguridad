<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class PersonalRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @param array<string, string> $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listar(array $filters, int $page, int $perPage): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR p.cargo LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params = array_merge($params, [$q, $q, $q, $q]);
        }
        if (($filters['empresa'] ?? '') !== '') {
            $where[] = 'p.empresa = ?';
            $params[] = $filters['empresa'];
        }
        if (($filters['estado'] ?? '') !== '') {
            $where[] = 'p.estado = ?';
            $params[] = $filters['estado'];
        }
        if (($filters['cargo'] ?? '') !== '') {
            $where[] = 'p.cargo LIKE ?';
            $params[] = '%' . $filters['cargo'] . '%';
        }
        if (($filters['granja_base'] ?? '') !== '') {
            $where[] = 'p.granja_base_cod = ?';
            $params[] = $filters['granja_base'];
        }
        if (($filters['empresa_id'] ?? '') !== '') {
            $where[] = 'p.empresa_id = ?';
            $params[] = (int) $filters['empresa_id'];
        }

        $sqlWhere = implode(' AND ', $where);
        $from = 'bio_personal p LEFT JOIN bio_empresa e ON e.id = p.empresa_id';
        $stCount = $this->pdo->prepare("SELECT COUNT(*) FROM {$from} WHERE {$sqlWhere}");
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $st = $this->pdo->prepare(
            "SELECT p.id, p.dni, p.nombres, p.apellidos, p.cargo, p.empresa, p.empresa_id,
                    p.fecha_contratacion, p.estado, p.granja_base_cod, p.foto_ruta, p.usuario_cod,
                    e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM {$from} WHERE {$sqlWhere}
             ORDER BY p.apellidos, p.nombres LIMIT {$perPage} OFFSET {$offset}"
        );
        $st->execute($params);

        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    /**
     * Cargos distintos del personal activo (opcionalmente de una empresa) con su cantidad.
     *
     * @return list<array{cargo: string, total: int}>
     */
    public function cargos(?int $empresaId): array
    {
        $sql = "SELECT TRIM(cargo) AS cargo, COUNT(*) AS total FROM bio_personal WHERE estado = 'ACTIVO' AND TRIM(cargo) <> ''";
        $params = [];
        if ($empresaId !== null) {
            $sql .= ' AND empresa_id = ?';
            $params[] = $empresaId;
        }
        $sql .= ' GROUP BY TRIM(cargo) ORDER BY cargo';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return array_map(static function (array $r): array {
            return ['cargo' => (string) $r['cargo'], 'total' => (int) $r['total']];
        }, $st->fetchAll());
    }

    /**
     * IDs del personal activo de una empresa con alguno de los cargos indicados.
     *
     * @param list<string> $cargos
     * @return list<int>
     */
    public function idsPorCargos(int $empresaId, array $cargos): array
    {
        $cargos = array_values(array_filter(array_map('trim', $cargos), 'strlen'));
        if ($cargos === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($cargos), '?'));
        $st = $this->pdo->prepare(
            "SELECT id FROM bio_personal WHERE estado = 'ACTIVO' AND empresa_id = ? AND TRIM(cargo) IN ({$marks})"
        );
        $st->execute(array_merge([$empresaId], $cargos));

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT p.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_personal p
             LEFT JOIN bio_empresa e ON e.id = p.empresa_id
             WHERE p.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function findByDni(string $dni, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id FROM bio_personal WHERE dni = ?';
        $params = [$dni];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function findFichaPorDni(string $dni): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT p.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
               FROM bio_personal p
               LEFT JOIN bio_empresa e ON e.id = p.empresa_id
              WHERE p.dni = ? LIMIT 1'
        );
        $st->execute([trim($dni)]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function findPorUsuario(string $codigo): ?array
    {
        $cod = strtoupper(trim($codigo));
        if ($cod === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT * FROM bio_personal WHERE UPPER(TRIM(usuario_cod)) = ? AND estado = 'ACTIVO' LIMIT 1"
        );
        $st->execute([$cod]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT p.* FROM bio_personal p
                   INNER JOIN usuario u ON TRIM(u.libtri) = TRIM(p.dni)
                  WHERE UPPER(TRIM(u.codigo)) = ? AND p.estado = 'ACTIVO' LIMIT 1"
            );
            $st->execute([$cod]);
            $row = $st->fetch();

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setUsuarioCod(int $id, string $codigo): void
    {
        $st = $this->pdo->prepare('UPDATE bio_personal SET usuario_cod = ? WHERE id = ?');
        $st->execute([substr(strtoupper(trim($codigo)), 0, 10), $id]);
    }

    /** @param array<string, mixed> $data */
    public function crear(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_personal (dni, nombres, apellidos, cargo, empresa, empresa_id, fecha_contratacion, estado, granja_base_cod, foto_ruta)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['dni'],
            $data['nombres'],
            $data['apellidos'],
            $data['cargo'],
            $data['empresa'],
            $data['empresa_id'] ?? null,
            $data['fecha_contratacion'],
            $data['estado'],
            $data['granja_base_cod'] ?? '',
            $data['foto_ruta'] ?? '',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function actualizar(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_personal SET dni = ?, nombres = ?, apellidos = ?, cargo = ?, empresa = ?, empresa_id = ?,
             fecha_contratacion = ?, estado = ?, granja_base_cod = ?, foto_ruta = ? WHERE id = ?'
        );
        $st->execute([
            $data['dni'],
            $data['nombres'],
            $data['apellidos'],
            $data['cargo'],
            $data['empresa'],
            $data['empresa_id'] ?? null,
            $data['fecha_contratacion'],
            $data['estado'],
            $data['granja_base_cod'] ?? '',
            $data['foto_ruta'] ?? '',
            $id,
        ]);
    }

    public function actualizarFoto(int $id, string $ruta): void
    {
        $st = $this->pdo->prepare('UPDATE bio_personal SET foto_ruta = ? WHERE id = ?');
        $st->execute([$ruta, $id]);
    }

    /** @return list<array<string, mixed>> */
    /**
     * @param list<int> $personalIds
     * @return list<array<string, mixed>>
     */
    public function capacitacionesDe(array $personalIds): array
    {
        if ($personalIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($personalIds), '?'));
        $st = $this->pdo->prepare(
            "SELECT personal_id, capacitacion_id, fecha_vence, resultado FROM bio_personal_capacitacion WHERE personal_id IN ({$marks})"
        );
        $st->execute($personalIds);

        return $st->fetchAll();
    }

    public function capacitaciones(int $personalId): array
    {
        $st = $this->pdo->prepare(
            'SELECT pc.id, pc.fecha_realizada, pc.fecha_vence, pc.resultado, pc.fecha_evaluacion, pc.observacion,
                    c.id AS capacitacion_id, c.codigo, c.nombre, c.vigencia_meses, c.tipo
             FROM bio_personal_capacitacion pc
             JOIN bio_capacitacion c ON c.id = pc.capacitacion_id
             WHERE pc.personal_id = ? ORDER BY pc.fecha_realizada DESC'
        );
        $st->execute([$personalId]);

        return $st->fetchAll();
    }

  /** @return list<array<string, mixed>> */
    public function historialAsignaciones(int $personalId, int $limit = 50): array
    {
        $st = $this->pdo->prepare(
            'SELECT a.id, a.fecha, a.turno_cod, a.es_reemplazo, p.nombre AS punto_nombre, p.granja_cod, p.tipo_cod
             FROM bio_asignacion a
             JOIN bio_puntos p ON p.id = a.punto_id
             WHERE a.personal_id = ?
             ORDER BY a.fecha DESC, a.turno_cod LIMIT ' . (int) $limit
        );
        $st->execute([$personalId]);

        return $st->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function addCapacitacion(int $personalId, array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_personal_capacitacion (personal_id, capacitacion_id, fecha_realizada, fecha_vence, resultado, fecha_evaluacion, observacion)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $personalId,
            $data['capacitacion_id'],
            $data['fecha_realizada'],
            $data['fecha_vence'] ?? null,
            $data['resultado'] ?? 'APROBADO',
            $data['fecha_evaluacion'] ?? null,
            $data['observacion'] ?? '',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteCapacitacion(int $personalId, int $rowId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM bio_personal_capacitacion WHERE id = ? AND personal_id = ?');
        $st->execute([$rowId, $personalId]);

        return $st->rowCount() > 0;
    }
}
