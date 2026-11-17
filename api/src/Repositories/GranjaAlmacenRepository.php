<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class GranjaAlmacenRepository
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
    public function saldosUniforme(string $granja): array
    {
        $sql = 'SELECT granja_cod, ropa_tipo, almacen,
                       SUM(CASE WHEN tipo_mov = \'S\' THEN -cantidad ELSE cantidad END) AS stock
                  FROM bio_uniforme_mov
                 WHERE (? = \'\' OR granja_cod = ?)
                 GROUP BY granja_cod, ropa_tipo, almacen';
        $st = $this->pdo->prepare($sql);
        $st->execute([$granja, $granja]);

        return $st->fetchAll();
    }

    public function saldoUniforme(string $granja, int $ropa, string $almacen): float
    {
        $st = $this->pdo->prepare(
            'SELECT IFNULL(SUM(CASE WHEN tipo_mov = \'S\' THEN -cantidad ELSE cantidad END), 0)
               FROM bio_uniforme_mov
              WHERE granja_cod = ? AND ropa_tipo = ? AND almacen = ?'
        );
        $st->execute([$granja, $ropa, $almacen]);

        return round((float) $st->fetchColumn(), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function saldosInsumo(string $granja): array
    {
        $sql = 'SELECT m.granja_cod, m.insumo_id,
                       IFNULL(i.codigo, \'\') AS codigo,
                       IFNULL(i.nombre, \'\') AS nombre,
                       IFNULL(i.unidad, \'\') AS unidad,
                       IFNULL(i.categoria, \'\') AS categoria,
                       SUM(CASE WHEN m.tipo_mov = \'S\' THEN -m.cantidad ELSE m.cantidad END) AS stock
                  FROM bio_insumo_mov m
                  LEFT JOIN bio_insumo i ON i.id = m.insumo_id
                 WHERE (? = \'\' OR m.granja_cod = ?)
                 GROUP BY m.granja_cod, m.insumo_id, i.codigo, i.nombre, i.unidad, i.categoria
                 ORDER BY i.nombre, m.insumo_id';
        $st = $this->pdo->prepare($sql);
        $st->execute([$granja, $granja]);

        return $st->fetchAll();
    }

    public function saldoInsumo(string $granja, int $insumoId): float
    {
        $st = $this->pdo->prepare(
            'SELECT IFNULL(SUM(CASE WHEN tipo_mov = \'S\' THEN -cantidad ELSE cantidad END), 0)
               FROM bio_insumo_mov
              WHERE granja_cod = ? AND insumo_id = ?'
        );
        $st->execute([$granja, $insumoId]);

        return round((float) $st->fetchColumn(), 2);
    }

    public function insumoExiste(int $id): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM bio_insumo WHERE id = ? LIMIT 1');
        $st->execute([$id]);

        return (bool) $st->fetchColumn();
    }

    /**
     * @return array{id: int, dni: string, nombre: string}|null
     */
    public function personal(int $id, string $dni): ?array
    {
        if ($id <= 0 && $dni === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT id, dni, TRIM(CONCAT(nombres, \' \', apellidos)) AS nombre
               FROM bio_personal
              WHERE (? > 0 AND id = ?) OR (? <> \'\' AND dni = ?)
              LIMIT 1'
        );
        $st->execute([$id, $id, $dni, $dni]);
        $row = $st->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'dni' => (string) $row['dni'],
            'nombre' => (string) $row['nombre'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movimientosUniforme(string $granja): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, granja_cod, ropa_tipo, almacen, tipo_mov, cantidad, concepto,
                    personal_id, dni, trabajador, fecha, observacion, usuario_registro, par_id
               FROM bio_uniforme_mov
              WHERE granja_cod = ?
              ORDER BY fecha, id'
        );
        $st->execute([$granja]);

        return $st->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movimientosInsumo(string $granja): array
    {
        $st = $this->pdo->prepare(
            'SELECT m.id, m.granja_cod, m.insumo_id, m.tipo_mov, m.cantidad, m.concepto,
                    m.fecha, m.observacion, m.usuario_registro,
                    IFNULL(i.codigo, \'\') AS codigo,
                    IFNULL(i.nombre, \'\') AS nombre,
                    IFNULL(i.unidad, \'\') AS unidad
               FROM bio_insumo_mov m
               LEFT JOIN bio_insumo i ON i.id = m.insumo_id
              WHERE m.granja_cod = ?
              ORDER BY m.fecha, m.id'
        );
        $st->execute([$granja]);

        return $st->fetchAll();
    }

    /**
     * @param array<string, mixed> $r
     */
    public function insertarUniforme(array $r): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_uniforme_mov
                (granja_cod, ropa_tipo, almacen, tipo_mov, cantidad, concepto, personal_id, dni, trabajador, fecha, observacion, usuario_registro, par_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $r['granja_cod'],
            $r['ropa_tipo'],
            $r['almacen'],
            $r['tipo_mov'],
            $r['cantidad'],
            $r['concepto'],
            $r['personal_id'],
            $r['dni'],
            $r['trabajador'],
            $r['fecha'],
            $r['observacion'],
            $r['usuario_registro'],
            $r['par_id'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function marcarPar(int $a, int $b): void
    {
        $st = $this->pdo->prepare('UPDATE bio_uniforme_mov SET par_id = ? WHERE id = ? OR id = ?');
        $st->execute([$a, $a, $b]);
    }

    /**
     * @param array<string, mixed> $r
     */
    public function insertarInsumo(array $r): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_insumo_mov
                (granja_cod, insumo_id, tipo_mov, cantidad, concepto, fecha, observacion, usuario_registro)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $r['granja_cod'],
            $r['insumo_id'],
            $r['tipo_mov'],
            $r['cantidad'],
            $r['concepto'],
            $r['fecha'],
            $r['observacion'],
            $r['usuario_registro'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
