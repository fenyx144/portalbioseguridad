<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class OperacionRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function puestos(): array
    {
        $st = $this->pdo->query(
            "SELECT codigo, nombre, turno, orden FROM bio_puesto WHERE estado = 'ACTIVO' ORDER BY orden, nombre"
        );

        return $st ? $st->fetchAll() : [];
    }

    /** @return list<array<string, mixed>> */
    public function requerimientos(): array
    {
        $st = $this->pdo->query(
            'SELECT id, zona, granja_nombre, granja_cod, puesto_cod, cantidad, orden
               FROM bio_requerimiento_puesto ORDER BY orden, zona, granja_nombre, puesto_cod'
        );

        return $st ? $st->fetchAll() : [];
    }

    /** @param list<array{id:int,cantidad:float}> $items */
    public function guardarCantidades(array $items): void
    {
        $st = $this->pdo->prepare('UPDATE bio_requerimiento_puesto SET cantidad = ? WHERE id = ?');
        foreach ($items as $it) {
            $st->execute([round((float) $it['cantidad'], 2), (int) $it['id']]);
        }
    }

    public function agregarGranjaRequerimiento(string $zona, string $granja): void
    {
        $puestos = $this->puestos();
        $orden = (int) $this->pdo->query('SELECT COALESCE(MAX(orden),0)+1 FROM bio_requerimiento_puesto')->fetchColumn();
        $st = $this->pdo->prepare(
            'INSERT IGNORE INTO bio_requerimiento_puesto (zona, granja_nombre, granja_cod, puesto_cod, cantidad, orden)
             VALUES (?, ?, \'\', ?, 0, ?)'
        );
        foreach ($puestos as $p) {
            $st->execute([$zona, $granja, $p['codigo'], $orden]);
        }
    }

    /** @param array<string, mixed> $d */
    public function crearAsistencia(array $d): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_asistencia
                (asignacion_id, personal_id, empresa_id, granja_cod, puesto_cod, tipo, marcado_en, origen, usuario_registro, observacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $d['asignacion_id'],
            $d['personal_id'],
            $d['empresa_id'],
            $d['granja_cod'],
            $d['puesto_cod'],
            $d['tipo'],
            $d['marcado_en'],
            $d['origen'],
            $d['usuario_registro'],
            $d['observacion'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string> $f @return list<array<string, mixed>> */
    public function listarAsistencia(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        $desde = $f['desde'] ?? date('Y-m-d');
        $hasta = $f['hasta'] ?? $desde;
        $where[] = 'DATE(s.marcado_en) BETWEEN ? AND ?';
        $params[] = $desde;
        $params[] = $hasta;
        if (($f['tipo'] ?? '') !== '') {
            $where[] = 's.tipo = ?';
            $params[] = $f['tipo'];
        }
        if (($f['empresa_id'] ?? '') !== '') {
            $where[] = 'p.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        if (($f['granja_cod'] ?? '') !== '') {
            $where[] = 's.granja_cod = ?';
            $params[] = $f['granja_cod'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(p.dni LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
            $q = '%' . $f['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
        $sql = 'SELECT s.id, s.personal_id, s.asignacion_id, s.granja_cod, s.puesto_cod, s.tipo,
                       s.marcado_en, s.origen, s.observacion, s.usuario_registro,
                       p.dni, p.nombres, p.apellidos, p.cargo, p.empresa_id,
                       e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
                  FROM bio_asistencia s
                  JOIN bio_personal p ON p.id = s.personal_id
                  LEFT JOIN bio_empresa e ON e.id = p.empresa_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY s.marcado_en DESC, p.apellidos
                 LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $d */
    public function crearAlcoholemia(array $d): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_alcoholemia
                (personal_id, empresa_id, granja_cod, fecha, hora, resultado, valor, observacion, accion, origen, usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $d['personal_id'], $d['empresa_id'], $d['granja_cod'], $d['fecha'], $d['hora'],
            $d['resultado'], $d['valor'], $d['observacion'], $d['accion'], $d['origen'], $d['usuario_registro'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string> $f @return list<array<string, mixed>> */
    public function listarAlcoholemia(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['desde'] ?? '') !== '') {
            $where[] = 'a.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (($f['hasta'] ?? '') !== '') {
            $where[] = 'a.fecha <= ?';
            $params[] = $f['hasta'];
        }
        if (($f['resultado'] ?? '') !== '') {
            $where[] = 'a.resultado = ?';
            $params[] = $f['resultado'];
        }
        if (($f['empresa_id'] ?? '') !== '') {
            $where[] = 'p.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        $sql = 'SELECT a.*, p.dni, p.nombres, p.apellidos, p.cargo,
                       e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
                  FROM bio_alcoholemia a
                  JOIN bio_personal p ON p.id = a.personal_id
                  LEFT JOIN bio_empresa e ON e.id = p.empresa_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY a.fecha DESC, a.hora DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $d */
    public function crearAnomalia(array $d): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_anomalia
                (ambito, personal_id, empresa_id, granja_cod, granja_nombre, puesto_cod, fecha, descripcion, accion, estado, usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $d['ambito'], $d['personal_id'], $d['empresa_id'], $d['granja_cod'], $d['granja_nombre'],
            $d['puesto_cod'], $d['fecha'], $d['descripcion'], $d['accion'], $d['estado'], $d['usuario_registro'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $d */
    public function actualizarAnomalia(int $id, array $d): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_anomalia SET ambito = ?, descripcion = ?, accion = ?, estado = ?, granja_cod = ?, granja_nombre = ?, puesto_cod = ?
              WHERE id = ?'
        );
        $st->execute([
            $d['ambito'], $d['descripcion'], $d['accion'], $d['estado'],
            $d['granja_cod'], $d['granja_nombre'], $d['puesto_cod'], $id,
        ]);
    }

    public function findAnomalia(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, p.empresa_id AS personal_empresa_id
               FROM bio_anomalia a
               LEFT JOIN bio_personal p ON p.id = a.personal_id
              WHERE a.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @param array<string, string> $f @return list<array<string, mixed>> */
    public function listarAnomalias(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (($f['ambito'] ?? '') !== '') {
            $where[] = 'a.ambito = ?';
            $params[] = $f['ambito'];
        }
        if (($f['estado'] ?? '') !== '') {
            $where[] = 'a.estado = ?';
            $params[] = $f['estado'];
        }
        if (($f['desde'] ?? '') !== '') {
            $where[] = 'a.fecha >= ?';
            $params[] = $f['desde'];
        }
        if (($f['hasta'] ?? '') !== '') {
            $where[] = 'a.fecha <= ?';
            $params[] = $f['hasta'];
        }
        if (($f['empresa_id'] ?? '') !== '') {
            $where[] = '(a.empresa_id = ? OR p.empresa_id = ?)';
            $params[] = (int) $f['empresa_id'];
            $params[] = (int) $f['empresa_id'];
        }
        $sql = 'SELECT a.*, p.dni, p.nombres, p.apellidos,
                       e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
                  FROM bio_anomalia a
                  LEFT JOIN bio_personal p ON p.id = a.personal_id
                  LEFT JOIN bio_empresa e ON e.id = COALESCE(a.empresa_id, p.empresa_id)
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY a.fecha DESC, a.id DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function faltantes(string $fecha, string $empresaId): array
    {
        $params = [$fecha];
        $extra = '';
        if ($empresaId !== '') {
            $extra = ' AND per.empresa_id = ?';
            $params[] = (int) $empresaId;
        }
        $sql = "SELECT a.id, a.fecha, a.turno_cod, pu.granja_cod, pu.nombre AS punto_nombre,
                       per.dni, per.nombres, per.apellidos, per.cargo,
                       e.razon_social AS empresa_razon_social, e.codigo AS empresa_codigo
                  FROM bio_asignacion a
                  JOIN bio_personal per ON per.id = a.personal_id
                  JOIN bio_puntos pu ON pu.id = a.punto_id
                  LEFT JOIN bio_empresa e ON e.id = per.empresa_id
                 WHERE a.fecha = ? {$extra}
                   AND NOT EXISTS (
                     SELECT 1 FROM bio_asistencia s
                      WHERE s.tipo = 'ENTRADA'
                        AND (s.personal_id = a.personal_id OR s.asignacion_id = a.id)
                        AND DATE(s.marcado_en) = a.fecha
                   )
                 ORDER BY pu.granja_cod, a.turno_cod, per.apellidos";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function marcacionesConTurno(string $desde, string $hasta, string $empresaId): array
    {
        $params = [$desde, $hasta];
        $extra = '';
        if ($empresaId !== '') {
            $extra = ' AND per.empresa_id = ?';
            $params[] = (int) $empresaId;
        }
        $sql = "SELECT s.id, s.tipo, s.marcado_en, s.origen, s.puesto_cod,
                       COALESCE(asg.turno_cod, IF(pu.turno = 'NOCHE', 'NOCHE', 'DIA')) AS turno_cod,
                       t.hora_inicio, t.hora_fin, t.cruza_medianoche,
                       per.dni, per.nombres, per.apellidos, per.cargo,
                       e.razon_social AS empresa_razon_social
                  FROM bio_asistencia s
                  JOIN bio_personal per ON per.id = s.personal_id
                  LEFT JOIN bio_asignacion asg ON asg.id = s.asignacion_id
                  LEFT JOIN bio_puesto pu ON pu.codigo = s.puesto_cod
                  LEFT JOIN bio_turno_catalogo t ON t.codigo = COALESCE(asg.turno_cod, IF(pu.turno = 'NOCHE', 'NOCHE', 'DIA'))
                  LEFT JOIN bio_empresa e ON e.id = per.empresa_id
                 WHERE DATE(s.marcado_en) BETWEEN ? AND ? {$extra}
                 ORDER BY s.marcado_en";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll() ?: [];
    }

    public function toleranciaMinutos(): int
    {
        $st = $this->pdo->query("SELECT valor FROM bio_config WHERE clave = 'asistencia_tolerancia_min' LIMIT 1");
        $v = $st ? $st->fetchColumn() : false;

        return $v !== false && $v !== null && $v !== '' ? max(0, (int) $v) : 15;
    }
}
