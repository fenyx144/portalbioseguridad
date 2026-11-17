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
                  LEFT JOIN bio_insumo i ON (m.frente = \'INSUMOS\' OR m.frente = \'IMPLEMENTOS\') AND i.id = m.item
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

    /** @return string IMPLEMENTO, INSUMO o vacío */
    public function claseInsumo(int $id): string
    {
        $st = $this->pdo->prepare('SELECT categoria FROM bio_insumo WHERE id = ? AND estado = \'ACTIVO\' LIMIT 1');
        $st->execute([$id]);
        $cat = $st->fetchColumn();
        if ($cat === false) {
            return '';
        }
        if ($cat === 'EPP' || $cat === 'INDUMENTARIA') {
            return 'IMPLEMENTO';
        }
        if ($cat === 'DESINFECTANTE' || $cat === 'OTRO') {
            return 'INSUMO';
        }

        return '';
    }

    /**
     * Granjas del personal de la empresa (base o asignación).
     *
     * @return list<string>
     */
    public function granjasEmpresa(int $empresaId): array
    {
        $sql = 'SELECT DISTINCT codigo FROM (
                    SELECT TRIM(granja_base_cod) AS codigo
                      FROM bio_personal
                     WHERE empresa_id = ? AND estado = \'ACTIVO\' AND TRIM(granja_base_cod) <> \'\'
                    UNION
                    SELECT TRIM(pt.granja_cod) AS codigo
                      FROM bio_asignacion a
                      INNER JOIN bio_personal p ON p.id = a.personal_id
                      INNER JOIN bio_puntos pt ON pt.id = a.punto_id
                     WHERE p.empresa_id = ? AND p.estado = \'ACTIVO\' AND TRIM(pt.granja_cod) <> \'\'
                ) g
                WHERE codigo <> \'\'
                ORDER BY codigo';
        $st = $this->pdo->prepare($sql);
        $st->execute([$empresaId, $empresaId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = (string) $row['codigo'];
        }

        return $out;
    }

    /**
     * @return array{id: int, dni: string, nombre: string}|null
     */
    public function personalDeGranja(string $granja, int $empresaId, int $id, string $dni): ?array
    {
        if ($id <= 0 && $dni === '') {
            return null;
        }
        $sql = 'SELECT p.id, p.dni, TRIM(CONCAT(p.nombres, \' \', p.apellidos)) AS nombre
                  FROM bio_personal p
                 WHERE p.estado = \'ACTIVO\'
                   AND (? = 0 OR p.empresa_id = ?)
                   AND ((? > 0 AND p.id = ?) OR (? <> \'\' AND p.dni = ?))
                   AND (
                        TRIM(p.granja_base_cod) = ?
                        OR p.id IN (
                            SELECT a.personal_id FROM bio_asignacion a
                              INNER JOIN bio_puntos pt ON pt.id = a.punto_id
                             WHERE TRIM(pt.granja_cod) = ?
                        )
                   )
                 LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$empresaId, $empresaId, $id, $id, $dni, $dni, $granja, $granja]);
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
     * @param list<string>|null $codigos null = todas
     * @return list<array<string, mixed>>
     */
    public function movimientos(string $granja, ?array $codigos): array
    {
        $where = '1=1';
        $params = [];
        if ($granja !== '') {
            $where = 'm.granja_cod = ?';
            $params = [$granja];
        } elseif (is_array($codigos)) {
            if ($codigos === []) {
                return [];
            }
            $marks = implode(',', array_fill(0, count($codigos), '?'));
            $where = 'm.granja_cod IN (' . $marks . ')';
            $params = $codigos;
        }
        $sql = 'SELECT m.id, m.frente, m.tipo, m.granja_cod, m.item, m.cantidad, m.operacion,
                       m.par_id, m.personal_id, m.dni, m.usuario_registro, m.origen, m.fecha, m.observacion,
                       IFNULL(i.codigo, \'\') AS codigo,
                       IFNULL(i.nombre, \'\') AS insumo_nombre,
                       IFNULL(i.unidad, \'\') AS unidad,
                       IFNULL(TRIM(CONCAT(p.nombres, \' \', p.apellidos)), \'\') AS trabajador
                  FROM bio_almacen_mov m
                  LEFT JOIN bio_insumo i ON (m.frente = \'INSUMOS\' OR m.frente = \'IMPLEMENTOS\') AND i.id = m.item
                  LEFT JOIN bio_personal p ON p.id = m.personal_id
                 WHERE ' . $where . '
                 ORDER BY m.fecha, m.id';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

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

    public function fijarPar(int $id): void
    {
        $st = $this->pdo->prepare('UPDATE bio_almacen_mov SET par_id = ? WHERE id = ?');
        $st->execute([$id, $id]);
    }
}
