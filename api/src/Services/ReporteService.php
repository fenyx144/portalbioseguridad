<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaRepository;

use Bio\Api\Bootstrap\Database;
use PDO;

final class ReporteService
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @param array<string, string> $query */
    public function personalRegistrado(array $query): array
    {
        $where = ['1=1'];
        $params = [];
        foreach (['empresa', 'estado'] as $k) {
            if (($query[$k] ?? '') !== '') {
                $where[] = "p.{$k} = ?";
                $params[] = $query[$k];
            }
        }
        if (($query['granja_base'] ?? '') !== '') {
            $where[] = 'p.granja_base_cod = ?';
            $params[] = $query['granja_base'];
        }
        if (($query['q'] ?? '') !== '') {
            $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
            $q = '%' . $query['q'] . '%';
            $params = array_merge($params, [$q, $q, $q]);
        }
        if (($query['empresa_id'] ?? '') !== '') {
            $where[] = 'p.empresa_id = ?';
            $params[] = (int) $query['empresa_id'];
        }
        $orden = ($query['orden'] ?? '') === 'antiguedad'
            ? 'p.fecha_contratacion ASC, p.apellidos, p.nombres'
            : 'p.apellidos, p.nombres';
        $sql = 'SELECT p.id, p.dni, p.nombres, p.apellidos, p.cargo, p.empresa, p.empresa_id,
                       p.fecha_contratacion, p.estado, p.granja_base_cod, p.usuario_cod,
                       DATEDIFF(CURDATE(), p.fecha_contratacion) AS antiguedad_dias,
                       e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
                FROM bio_personal p
                LEFT JOIN bio_empresa e ON e.id = p.empresa_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orden;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll() ?: [];
        foreach ($items as &$row) {
            $row['empresa_etiqueta'] = \Bio\Api\Support\EmpresaEtiqueta::personal($row);
        }
        unset($row);

        return ['ok' => true, 'data' => ['items' => $items]];
    }

    /** @param array<string, string> $query */
    public function personalAsignado(array $query): array
    {
        $desde = $query['fecha_desde'] ?? date('Y-m-d');
        $hasta = $query['fecha_hasta'] ?? $desde;
        $granjas = array_filter(array_map('trim', explode(',', $query['granja_cod'] ?? '')));
        $where = ['a.fecha BETWEEN ? AND ?'];
        $params = [$desde, $hasta];
        if ($granjas !== []) {
            $marks = implode(',', array_fill(0, count($granjas), '?'));
            $where[] = "p.granja_cod IN ({$marks})";
            $params = array_merge($params, $granjas);
        }
        if (($query['empresa_id'] ?? '') !== '') {
            $where[] = 'per.empresa_id = ?';
            $params[] = (int) $query['empresa_id'];
        }
        $sql = 'SELECT a.fecha, a.turno_cod, p.granja_cod, p.nombre AS punto_nombre, p.tipo_cod,
                       per.dni, per.nombres, per.apellidos, per.cargo, per.empresa, be.razon_social AS empresa_nombre, a.es_reemplazo
                FROM bio_asignacion a
                JOIN bio_puntos p ON p.id = a.punto_id
                JOIN bio_personal per ON per.id = a.personal_id
                LEFT JOIN bio_empresa be ON be.id = per.empresa_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.fecha, p.granja_cod, p.nombre, a.turno_cod';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $nombres = [];
        foreach ((new GranjaRepository())->listar() as $g) {
            $nombres[$g['codigo']] = $g['nombre'];
        }
        $items = array_map(static function (array $r) use ($nombres): array {
            $cod = trim((string) ($r['granja_cod'] ?? ''));
            $r['granja_nombre'] = $nombres[$cod] ?? '';
            return $r;
        }, $st->fetchAll());

        return ['ok' => true, 'data' => ['items' => $items, 'desde' => $desde, 'hasta' => $hasta]];
    }

    /** @param array<string, string> $query */
    public function capacitacionesPersonal(array $query): array
    {
        $where = ['1=1'];
        $params = [];
        if (($query['empresa_id'] ?? '') !== '') {
            $where[] = 'p.empresa_id = ?';
            $params[] = (int) $query['empresa_id'];
        }
        if (($query['estado_personal'] ?? '') !== '') {
            $where[] = 'p.estado = ?';
            $params[] = $query['estado_personal'];
        }
        if (($query['capacitacion_id'] ?? '') !== '') {
            $where[] = 'c.id = ?';
            $params[] = (int) $query['capacitacion_id'];
        }
        if (($query['resultado'] ?? '') !== '') {
            $where[] = 'pc.resultado = ?';
            $params[] = $query['resultado'];
        }
        if (($query['q'] ?? '') !== '') {
            $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
            $q = '%' . $query['q'] . '%';
            $params = array_merge($params, [$q, $q, $q]);
        }
        $vigencia = $query['estado_vigencia'] ?? '';
        $sql = 'SELECT p.dni, p.nombres, p.apellidos, p.cargo, p.estado AS estado_personal,
                       be.codigo AS empresa_codigo, be.razon_social AS empresa_nombre,
                       c.codigo AS cap_codigo, c.nombre AS cap_nombre,
                       pc.fecha_realizada, pc.fecha_vence, pc.fecha_evaluacion, pc.resultado, pc.observacion,
                       CASE
                         WHEN pc.fecha_vence IS NULL OR pc.fecha_vence = \'\' THEN \'SIN_VENCIMIENTO\'
                         WHEN pc.fecha_vence < CURDATE() THEN \'VENCIDA\'
                         ELSE \'VIGENTE\'
                       END AS estado_vigencia
                FROM bio_personal_capacitacion pc
                JOIN bio_personal p ON p.id = pc.personal_id
                JOIN bio_capacitacion c ON c.id = pc.capacitacion_id
                LEFT JOIN bio_empresa be ON be.id = p.empresa_id
                WHERE ' . implode(' AND ', $where);
        if ($vigencia === 'VENCIDA') {
            $sql .= ' AND pc.fecha_vence IS NOT NULL AND pc.fecha_vence <> \'\' AND pc.fecha_vence < CURDATE()';
        } elseif ($vigencia === 'VIGENTE') {
            $sql .= ' AND (pc.fecha_vence IS NULL OR pc.fecha_vence = \'\' OR pc.fecha_vence >= CURDATE())';
        }
        $sql .= ' ORDER BY be.codigo, p.apellidos, p.nombres, c.nombre, pc.fecha_realizada DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return ['ok' => true, 'data' => ['items' => $st->fetchAll()]];
    }
}
