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
    public function saldos(string $granja): array
    {
        $sql = 'SELECT m.granja_cod, m.frente, m.item,
                       SUM(CASE WHEN m.tipo = \'S\' THEN -m.cantidad ELSE m.cantidad END) AS stock,
                       IFNULL(i.codigo, \'\') AS codigo,
                       IFNULL(i.nombre, \'\') AS nombre,
                       IFNULL(i.unidad, \'\') AS unidad,
                       IFNULL(i.categoria, \'\') AS categoria
                  FROM bio_almacen_mov m
                  LEFT JOIN bio_insumo i ON m.frente = \'INSUMOS\' AND i.id = m.item
                 WHERE (? = \'\' OR m.granja_cod = ?)
                 GROUP BY m.granja_cod, m.frente, m.item, i.codigo, i.nombre, i.unidad, i.categoria';
        $st = $this->pdo->prepare($sql);
        $st->execute([$granja, $granja]);

        return $st->fetchAll();
    }

    public function saldo(string $granja, string $frente, string $item): float
    {
        $st = $this->pdo->prepare(
            'SELECT IFNULL(SUM(CASE WHEN tipo = \'S\' THEN -cantidad ELSE cantidad END), 0)
               FROM bio_almacen_mov
              WHERE granja_cod = ? AND frente = ? AND item = ?'
        );
        $st->execute([$granja, $frente, $item]);

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
    public function movimientos(string $granja): array
    {
        $st = $this->pdo->prepare(
            'SELECT m.id, m.frente, m.tipo, m.granja_cod, m.item, m.cantidad, m.operacion,
                    m.par_id, m.personal_id, m.dni, m.usuario_registro, m.origen, m.fecha, m.observacion,
                    IFNULL(i.codigo, \'\') AS codigo,
                    IFNULL(i.nombre, \'\') AS insumo_nombre,
                    IFNULL(i.unidad, \'\') AS unidad,
                    IFNULL(TRIM(CONCAT(p.nombres, \' \', p.apellidos)), \'\') AS trabajador
               FROM bio_almacen_mov m
               LEFT JOIN bio_insumo i ON m.frente = \'INSUMOS\' AND i.id = m.item
               LEFT JOIN bio_personal p ON p.id = m.personal_id
              WHERE m.granja_cod = ?
              ORDER BY m.fecha, m.id'
        );
        $st->execute([$granja]);

        return $st->fetchAll();
    }

    /**
     * @param array<string, mixed> $r
     */
    public function insertar(array $r): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_almacen_mov
                (frente, tipo, granja_cod, item, cantidad, operacion, par_id, personal_id, dni, usuario_registro, origen, fecha, observacion)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $r['frente'],
            $r['tipo'],
            $r['granja_cod'],
            $r['item'],
            $r['cantidad'],
            $r['operacion'],
            $r['par_id'],
            $r['personal_id'],
            $r['dni'],
            $r['usuario_registro'],
            $r['origen'],
            $r['fecha'],
            $r['observacion'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function marcarPar(int $a, int $b): void
    {
        $st = $this->pdo->prepare('UPDATE bio_almacen_mov SET par_id = ? WHERE id = ? OR id = ?');
        $st->execute([$a, $a, $b]);
    }
}
