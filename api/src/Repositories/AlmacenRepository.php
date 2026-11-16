<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class AlmacenRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    // ---------- Insumos ----------

    /** @return list<array<string, mixed>> */
    public function insumos(bool $soloActivos): array
    {
        $sql = 'SELECT * FROM bio_insumo';
        if ($soloActivos) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY categoria, nombre';
        $st = $this->pdo->query($sql);

        return $st ? $st->fetchAll() : [];
    }

    public function insumo(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_insumo WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function existeCodigoInsumo(string $codigo, ?int $excludeId): bool
    {
        $sql = 'SELECT id FROM bio_insumo WHERE codigo = ?';
        $params = [$codigo];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool) $st->fetch();
    }

    /** @param array<string, string> $d */
    public function crearInsumo(array $d): int
    {
        $this->pdo->prepare(
            'INSERT INTO bio_insumo (codigo, nombre, categoria, unidad, estado) VALUES (?, ?, ?, ?, ?)'
        )->execute([$d['codigo'], $d['nombre'], $d['categoria'], $d['unidad'], $d['estado']]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string> $d */
    public function actualizarInsumo(int $id, array $d): void
    {
        $this->pdo->prepare(
            'UPDATE bio_insumo SET codigo = ?, nombre = ?, categoria = ?, unidad = ?, estado = ? WHERE id = ?'
        )->execute([$d['codigo'], $d['nombre'], $d['categoria'], $d['unidad'], $d['estado'], $id]);
    }

    // ---------- Saldos iniciales ----------

    /** @return list<array<string, mixed>> */
    public function saldos(?int $empresaId): array
    {
        $sql = 'SELECT s.empresa_id, s.insumo_id, s.saldo_inicial FROM bio_almacen_saldo s';
        $params = [];
        if ($empresaId !== null) {
            $sql .= ' WHERE s.empresa_id = ?';
            $params[] = $empresaId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }

    public function guardarSaldo(int $empresaId, int $insumoId, float $saldo): void
    {
        $this->pdo->prepare(
            'INSERT INTO bio_almacen_saldo (empresa_id, insumo_id, saldo_inicial) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE saldo_inicial = VALUES(saldo_inicial)'
        )->execute([$empresaId, $insumoId, $saldo]);
    }

    // ---------- Entregas ----------

    /**
     * @param array{empresa_id?: int|null, estado?: string, desde?: string, hasta?: string} $f
     * @return list<array<string, mixed>>
     */
    public function entregas(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['empresa_id'])) {
            $where[] = 'en.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        if (!empty($f['estado'])) {
            $where[] = 'en.estado = ?';
            $params[] = $f['estado'];
        }
        if (!empty($f['desde'])) {
            $where[] = 'en.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[] = 'en.fecha <= ?';
            $params[] = $f['hasta'];
        }
        $st = $this->pdo->prepare(
            'SELECT en.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social,
                    (SELECT COUNT(*) FROM bio_almacen_entrega_det d WHERE d.entrega_id = en.id) AS num_items,
                    (SELECT COALESCE(SUM(d.cantidad), 0) FROM bio_almacen_entrega_det d WHERE d.entrega_id = en.id) AS total_cantidad
             FROM bio_almacen_entrega en
             LEFT JOIN bio_empresa e ON e.id = en.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY en.fecha DESC, en.id DESC
             LIMIT 500'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    public function entrega(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT en.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_almacen_entrega en
             LEFT JOIN bio_empresa e ON e.id = en.empresa_id
             WHERE en.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function entregaDetalle(int $entregaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT d.*, i.codigo AS insumo_codigo, i.nombre AS insumo_nombre, i.categoria, i.unidad
             FROM bio_almacen_entrega_det d
             JOIN bio_insumo i ON i.id = d.insumo_id
             WHERE d.entrega_id = ?
             ORDER BY i.categoria, i.nombre'
        );
        $st->execute([$entregaId]);

        return $st->fetchAll();
    }

    /**
     * @param array<string, mixed> $cab
     * @param list<array{insumo_id: int, cantidad: float}> $items
     */
    public function crearEntrega(array $cab, array $items, string $usuario): int
    {
        $this->pdo->prepare(
            'INSERT INTO bio_almacen_entrega (empresa_id, granja_cod, fecha, observacion, usuario_registro) VALUES (?, ?, ?, ?, ?)'
        )->execute([(int) $cab['empresa_id'], $cab['granja_cod'], $cab['fecha'], $cab['observacion'], $usuario]);
        $id = (int) $this->pdo->lastInsertId();
        $ins = $this->pdo->prepare('INSERT INTO bio_almacen_entrega_det (entrega_id, insumo_id, cantidad) VALUES (?, ?, ?)');
        foreach ($items as $it) {
            $ins->execute([$id, $it['insumo_id'], $it['cantidad']]);
        }

        return $id;
    }

    /** @param array<int, float> $recibidas detalle_id => cantidad */
    public function confirmarRecepcion(int $entregaId, array $recibidas, string $estado, string $anomalia, string $usuario): void
    {
        $up = $this->pdo->prepare('UPDATE bio_almacen_entrega_det SET cantidad_recibida = ? WHERE id = ? AND entrega_id = ?');
        foreach ($recibidas as $detId => $cant) {
            $up->execute([$cant, $detId, $entregaId]);
        }
        $this->pdo->prepare(
            'UPDATE bio_almacen_entrega SET estado = ?, anomalia = ?, recibido_en = NOW(), recibido_por = ? WHERE id = ?'
        )->execute([$estado, $anomalia, $usuario, $entregaId]);
    }

    public function setEstadoEntrega(int $id, string $estado): void
    {
        $this->pdo->prepare('UPDATE bio_almacen_entrega SET estado = ? WHERE id = ?')->execute([$estado, $id]);
    }

    // ---------- Consumos ----------

    /**
     * @param array{empresa_id?: int|null, desde?: string, hasta?: string} $f
     * @return list<array<string, mixed>>
     */
    public function consumos(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['empresa_id'])) {
            $where[] = 'c.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        if (!empty($f['desde'])) {
            $where[] = 'c.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[] = 'c.fecha <= ?';
            $params[] = $f['hasta'];
        }
        $st = $this->pdo->prepare(
            'SELECT c.*, i.codigo AS insumo_codigo, i.nombre AS insumo_nombre, i.unidad, i.categoria,
                    e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_almacen_consumo c
             JOIN bio_insumo i ON i.id = c.insumo_id
             LEFT JOIN bio_empresa e ON e.id = c.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY c.fecha DESC, c.id DESC
             LIMIT 500'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @param array<string, mixed> $d */
    public function crearConsumo(array $d, string $usuario): int
    {
        $this->pdo->prepare(
            'INSERT INTO bio_almacen_consumo (empresa_id, insumo_id, granja_cod, fecha, cantidad, observacion, usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$d['empresa_id'], $d['insumo_id'], $d['granja_cod'], $d['fecha'], $d['cantidad'], $d['observacion'], $usuario]);

        return (int) $this->pdo->lastInsertId();
    }

    public function consumo(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_almacen_consumo WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function eliminarConsumo(int $id): void
    {
        $this->pdo->prepare('DELETE FROM bio_almacen_consumo WHERE id = ?')->execute([$id]);
    }

    // ---------- Stock ----------

    /**
     * Totales por empresa e insumo: entregado (no anulado), recibido (confirmado), pendiente y consumido.
     *
     * @return list<array<string, mixed>>
     */
    public function movimientos(?int $empresaId): array
    {
        $params = [];
        $filtroE = '';
        $filtroC = '';
        if ($empresaId !== null) {
            $filtroE = ' AND en.empresa_id = ?';
            $filtroC = ' AND c.empresa_id = ?';
        }
        $sqlE = "SELECT en.empresa_id, d.insumo_id,
                        SUM(d.cantidad) AS entregado,
                        SUM(CASE WHEN en.estado IN ('RECIBIDA','OBSERVADA') THEN COALESCE(d.cantidad_recibida, d.cantidad) ELSE 0 END) AS recibido,
                        SUM(CASE WHEN en.estado = 'PENDIENTE' THEN d.cantidad ELSE 0 END) AS pendiente,
                        0 AS consumido
                 FROM bio_almacen_entrega en
                 JOIN bio_almacen_entrega_det d ON d.entrega_id = en.id
                 WHERE en.estado <> 'ANULADA'{$filtroE}
                 GROUP BY en.empresa_id, d.insumo_id";
        $sqlC = "SELECT c.empresa_id, c.insumo_id, 0 AS entregado, 0 AS recibido, 0 AS pendiente, SUM(c.cantidad) AS consumido
                 FROM bio_almacen_consumo c WHERE 1=1{$filtroC}
                 GROUP BY c.empresa_id, c.insumo_id";
        if ($empresaId !== null) {
            $params = [$empresaId, $empresaId];
        }
        $st = $this->pdo->prepare($sqlE . ' UNION ALL ' . $sqlC);
        $st->execute($params);

        return $st->fetchAll();
    }

    // ---------- Relevo de turno ----------

    /**
     * @param array{empresa_id?: int|null, desde?: string, hasta?: string} $f
     * @return list<array<string, mixed>>
     */
    public function relevos(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['empresa_id'])) {
            $where[] = 'r.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        if (!empty($f['desde'])) {
            $where[] = 'r.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where[] = 'r.fecha <= ?';
            $params[] = $f['hasta'];
        }
        $st = $this->pdo->prepare(
            'SELECT r.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_almacen_relevo r
             LEFT JOIN bio_empresa e ON e.id = r.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.fecha DESC, r.id DESC
             LIMIT 300'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    public function relevo(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT r.*, e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_almacen_relevo r
             LEFT JOIN bio_empresa e ON e.id = r.empresa_id WHERE r.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function relevoDetalle(int $relevoId): array
    {
        $st = $this->pdo->prepare(
            'SELECT d.*, i.codigo AS insumo_codigo, i.nombre AS insumo_nombre, i.unidad
             FROM bio_almacen_relevo_det d
             JOIN bio_insumo i ON i.id = d.insumo_id
             WHERE d.relevo_id = ?
             ORDER BY i.categoria, i.nombre'
        );
        $st->execute([$relevoId]);

        return $st->fetchAll();
    }

    /**
     * @param array<string, mixed> $cab
     * @param list<array{insumo_id: int, cantidad_sistema: float, cantidad_contada: float}> $items
     */
    public function crearRelevo(array $cab, array $items, string $usuario): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO bio_almacen_relevo (empresa_id, granja_cod, fecha, turno_cod, responsable_salida, responsable_entrada, observacion, usuario_registro)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $cab['empresa_id'],
                $cab['granja_cod'],
                $cab['fecha'],
                $cab['turno_cod'],
                $cab['responsable_salida'],
                $cab['responsable_entrada'],
                $cab['observacion'],
                $usuario,
            ]);
            $rid = (int) $this->pdo->lastInsertId();
            $ins = $this->pdo->prepare(
                'INSERT INTO bio_almacen_relevo_det (relevo_id, insumo_id, cantidad_sistema, cantidad_contada) VALUES (?, ?, ?, ?)'
            );
            foreach ($items as $it) {
                $ins->execute([$rid, $it['insumo_id'], $it['cantidad_sistema'], $it['cantidad_contada']]);
            }
            $this->pdo->commit();

            return $rid;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
