<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class CapacitacionSesionRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT s.*, c.codigo AS cap_codigo, c.nombre AS cap_nombre, c.vigencia_meses,
                    e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social
             FROM bio_capacitacion_sesion s
             JOIN bio_capacitacion c ON c.id = s.capacitacion_id
             LEFT JOIN bio_empresa e ON e.id = s.empresa_id
             WHERE s.id = ?'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function participantes(int $sesionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT sp.*, p.dni, p.nombres, p.apellidos, p.cargo, p.estado AS personal_estado
             FROM bio_capacitacion_sesion_personal sp
             JOIN bio_personal p ON p.id = sp.personal_id
             WHERE sp.sesion_id = ?
             ORDER BY p.apellidos, p.nombres'
        );
        $st->execute([$sesionId]);

        return $st->fetchAll();
    }

    public function participante(int $sesionId, int $personalId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_capacitacion_sesion_personal WHERE sesion_id = ? AND personal_id = ?');
        $st->execute([$sesionId, $personalId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /**
     * @param array{empresa_id?: int|null, desde: string, hasta: string, capacitacion_id?: int, estado?: string} $f
     * @return list<array<string, mixed>>
     */
    public function listar(array $f): array
    {
        $where = ['s.fecha BETWEEN ? AND ?'];
        $params = [$f['desde'], $f['hasta']];
        if (!empty($f['empresa_id'])) {
            $where[] = 's.empresa_id = ?';
            $params[] = (int) $f['empresa_id'];
        }
        if (!empty($f['capacitacion_id'])) {
            $where[] = 's.capacitacion_id = ?';
            $params[] = (int) $f['capacitacion_id'];
        }
        if (!empty($f['estado'])) {
            $where[] = 's.estado = ?';
            $params[] = (string) $f['estado'];
        }
        $st = $this->pdo->prepare(
            'SELECT s.*, c.codigo AS cap_codigo, c.nombre AS cap_nombre,
                    e.codigo AS empresa_codigo, e.razon_social AS empresa_razon_social,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id) AS num_programados,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id AND sp.asistio = 1) AS num_asistieron,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id AND sp.asistio = 1 AND sp.resultado <> \'PENDIENTE\') AS num_evaluados,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id AND sp.resultado = \'APROBADO\') AS num_aprobados,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id AND sp.resultado = \'DESAPROBADO\') AS num_desaprobados
             FROM bio_capacitacion_sesion s
             JOIN bio_capacitacion c ON c.id = s.capacitacion_id
             LEFT JOIN bio_empresa e ON e.id = s.empresa_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.fecha DESC, s.hora_inicio, s.id DESC'
        );
        $st->execute($params);

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listarEmpresaMes(int $empresaId, string $mes): array
    {
        $desde = $mes . '-01';
        $hasta = (new \DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        return $this->listar(['empresa_id' => $empresaId, 'desde' => $desde, 'hasta' => $hasta]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $personalIds
     */
    public function crear(int $empresaId, array $data, array $personalIds, string $usuario): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_capacitacion_sesion (empresa_id, capacitacion_id, fecha, hora_inicio, lugar, nota_minima, observacion, usuario_registro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $empresaId,
            (int) $data['capacitacion_id'],
            $data['fecha'],
            $data['hora_inicio'] ?? null,
            $data['lugar'] ?? '',
            $data['nota_minima'] ?? 14,
            $data['observacion'] ?? '',
            $usuario,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->agregarParticipantes($id, $personalIds);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int>|null $personalIds
     */
    public function actualizar(int $id, array $data, ?array $personalIds): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_capacitacion_sesion SET capacitacion_id = ?, fecha = ?, hora_inicio = ?, lugar = ?, nota_minima = ?, observacion = ? WHERE id = ?'
        );
        $st->execute([
            (int) $data['capacitacion_id'],
            $data['fecha'],
            $data['hora_inicio'] ?? null,
            $data['lugar'] ?? '',
            $data['nota_minima'] ?? 14,
            $data['observacion'] ?? '',
            $id,
        ]);
        if ($personalIds !== null) {
            $this->syncParticipantes($id, $personalIds);
        }
    }

    public function setEstado(int $id, string $estado): void
    {
        $this->pdo->prepare('UPDATE bio_capacitacion_sesion SET estado = ? WHERE id = ?')->execute([$estado, $id]);
    }

    /**
     * Agrega participantes sin duplicar. Devuelve cuántos se agregaron.
     *
     * @param list<int> $personalIds
     */
    public function agregarParticipantes(int $sesionId, array $personalIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $personalIds))));
        $stCur = $this->pdo->prepare('SELECT personal_id FROM bio_capacitacion_sesion_personal WHERE sesion_id = ?');
        $stCur->execute([$sesionId]);
        $actuales = array_map('intval', array_column($stCur->fetchAll(), 'personal_id'));
        $ins = $this->pdo->prepare('INSERT INTO bio_capacitacion_sesion_personal (sesion_id, personal_id) VALUES (?, ?)');
        $n = 0;
        foreach (array_diff($ids, $actuales) as $pid) {
            $ins->execute([$sesionId, $pid]);
            $n++;
        }

        return $n;
    }

    public function quitarParticipante(int $sesionId, int $personalId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM bio_capacitacion_sesion_personal WHERE sesion_id = ? AND personal_id = ?');
        $st->execute([$sesionId, $personalId]);

        return $st->rowCount() > 0;
    }

    /** @param list<int> $personalIds */
    private function syncParticipantes(int $sesionId, array $personalIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $personalIds))));
        $stCur = $this->pdo->prepare('SELECT personal_id FROM bio_capacitacion_sesion_personal WHERE sesion_id = ?');
        $stCur->execute([$sesionId]);
        $actuales = array_map('intval', array_column($stCur->fetchAll(), 'personal_id'));
        $quitar = array_diff($actuales, $ids);
        if ($quitar !== []) {
            $marks = implode(',', array_fill(0, count($quitar), '?'));
            $this->pdo->prepare(
                "DELETE FROM bio_capacitacion_sesion_personal WHERE sesion_id = ? AND personal_id IN ({$marks})"
            )->execute(array_merge([$sesionId], array_values($quitar)));
        }
        $this->agregarParticipantes($sesionId, $ids);
    }

    public function marcarAsistencia(int $sesionId, int $personalId, bool $asistio, ?int $registroCapId): void
    {
        $this->pdo->prepare(
            'UPDATE bio_capacitacion_sesion_personal SET asistio = ?, registro_cap_id = ? WHERE sesion_id = ? AND personal_id = ?'
        )->execute([$asistio ? 1 : 0, $registroCapId, $sesionId, $personalId]);
        if (!$asistio) {
            $this->pdo->prepare(
                "UPDATE bio_capacitacion_sesion_personal SET nota = NULL, resultado = 'PENDIENTE', fecha_evaluacion = NULL
                 WHERE sesion_id = ? AND personal_id = ?"
            )->execute([$sesionId, $personalId]);
        }
    }

    public function guardarEvaluacion(int $sesionId, int $personalId, ?float $nota, string $resultado, ?string $fecha, string $observacion): void
    {
        $this->pdo->prepare(
            'UPDATE bio_capacitacion_sesion_personal SET nota = ?, resultado = ?, fecha_evaluacion = ?, observacion = ?
             WHERE sesion_id = ? AND personal_id = ?'
        )->execute([$nota, $resultado, $fecha, $observacion, $sesionId, $personalId]);
    }

    /** Actualiza el registro en la ficha del trabajador (bio_personal_capacitacion). */
    public function actualizarRegistroFicha(int $registroId, ?float $nota, string $resultado, ?string $fechaEvaluacion): void
    {
        $this->pdo->prepare(
            'UPDATE bio_personal_capacitacion SET nota = ?, resultado = ?, fecha_evaluacion = ? WHERE id = ?'
        )->execute([$nota, $resultado, $fechaEvaluacion, $registroId]);
    }

    public function existeRegistroFicha(int $registroId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM bio_personal_capacitacion WHERE id = ?');
        $st->execute([$registroId]);

        return (bool) $st->fetchColumn();
    }

    public function eliminarRegistroFicha(int $registroId): void
    {
        $this->pdo->prepare('DELETE FROM bio_personal_capacitacion WHERE id = ?')->execute([$registroId]);
    }
}
