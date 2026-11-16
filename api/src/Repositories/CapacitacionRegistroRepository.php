<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class CapacitacionRegistroRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /**
     * @return array{eventos: list<array<string, mixed>>, personal: list<array<string, mixed>>}
     */
    public function resumenEmpresa(int $empresaId, string $mes): array
    {
        $desde = $mes . '-01';
        $hasta = (new \DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');

        $st = $this->pdo->prepare(
            'SELECT pc.id AS registro_id, pc.fecha_realizada, pc.fecha_vence, pc.fecha_evaluacion, pc.resultado,
                    c.codigo AS cap_codigo, c.nombre AS cap_nombre,
                    p.id AS personal_id, p.dni, p.nombres, p.apellidos, p.cargo
             FROM bio_personal_capacitacion pc
             JOIN bio_personal p ON p.id = pc.personal_id
             JOIN bio_capacitacion c ON c.id = pc.capacitacion_id
             WHERE p.empresa_id = ?
             ORDER BY pc.fecha_realizada DESC'
        );
        $st->execute([$empresaId]);
        $rows = $st->fetchAll();
        $eventos = [];
        foreach ($rows as $row) {
            foreach ($this->eventosDeFila($row) as $ev) {
                if ($ev['fecha'] >= $desde && $ev['fecha'] <= $hasta) {
                    $eventos[] = $ev;
                }
            }
        }
        usort($eventos, static function (array $a, array $b): int {
            return strcmp($a['fecha'], $b['fecha']);
        });

        foreach ($this->eventosSesiones($empresaId, $desde, $hasta) as $ev) {
            $eventos[] = $ev;
        }
        usort($eventos, static function (array $a, array $b): int {
            return strcmp($a['fecha'], $b['fecha']);
        });

        $stP = $this->pdo->prepare(
            'SELECT p.id, p.dni, p.nombres, p.apellidos, p.cargo, p.estado,
                    COUNT(pc.id) AS num_registros,
                    MAX(pc.fecha_realizada) AS ultima_realizada
             FROM bio_personal p
             LEFT JOIN bio_personal_capacitacion pc ON pc.personal_id = p.id
             WHERE p.empresa_id = ?
             GROUP BY p.id
             HAVING num_registros > 0
             ORDER BY p.apellidos, p.nombres'
        );
        $stP->execute([$empresaId]);

        return [
            'eventos' => $eventos,
            'personal' => $stP->fetchAll(),
            'sesiones' => $this->sesionesMes($empresaId, $desde, $hasta),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sesionesMes(int $empresaId, string $desde, string $hasta): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.id, s.fecha, s.estado, s.lugar,
                    c.codigo AS cap_codigo, c.nombre AS cap_nombre,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id) AS num_programados,
                    (SELECT COUNT(*) FROM bio_capacitacion_sesion_personal sp WHERE sp.sesion_id = s.id AND sp.asistio = 1) AS num_asistieron
             FROM bio_capacitacion_sesion s
             JOIN bio_capacitacion c ON c.id = s.capacitacion_id
             WHERE s.empresa_id = ? AND s.fecha BETWEEN ? AND ?
             ORDER BY s.fecha, s.id'
        );
        $st->execute([$empresaId, $desde, $hasta]);
        $sesiones = $st->fetchAll();
        if ($sesiones === []) {
            return [];
        }

        $ids = array_map(static function (array $row): int {
            return (int) $row['id'];
        }, $sesiones);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stP = $this->pdo->prepare(
            "SELECT sp.sesion_id, sp.asistio,
                    p.id AS personal_id, p.dni, p.nombres, p.apellidos, p.cargo
             FROM bio_capacitacion_sesion_personal sp
             JOIN bio_personal p ON p.id = sp.personal_id
             WHERE sp.sesion_id IN ({$marks})
             ORDER BY sp.sesion_id, p.apellidos, p.nombres"
        );
        $stP->execute($ids);
        $porSesion = [];
        foreach ($stP->fetchAll() as $row) {
            $sid = (int) $row['sesion_id'];
            $porSesion[$sid][] = [
                'personal_id' => (int) $row['personal_id'],
                'dni' => $row['dni'],
                'nombres' => $row['nombres'],
                'apellidos' => $row['apellidos'],
                'cargo' => $row['cargo'],
                'asistio' => (int) ($row['asistio'] ?? 0),
            ];
        }

        foreach ($sesiones as &$s) {
            $s['programados'] = $porSesion[(int) $s['id']] ?? [];
        }
        unset($s);

        return $sesiones;
    }

    /** @return list<array<string, mixed>> */
    private function eventosSesiones(int $empresaId, string $desde, string $hasta): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.id AS sesion_id, s.fecha, s.estado, c.codigo AS cap_codigo, c.nombre AS cap_nombre,
                    p.id AS personal_id, p.dni, p.nombres, p.apellidos, p.cargo, sp.asistio
             FROM bio_capacitacion_sesion s
             JOIN bio_capacitacion c ON c.id = s.capacitacion_id
             JOIN bio_capacitacion_sesion_personal sp ON sp.sesion_id = s.id
             JOIN bio_personal p ON p.id = sp.personal_id
             WHERE s.empresa_id = ? AND s.fecha BETWEEN ? AND ?
             ORDER BY s.fecha, p.apellidos'
        );
        $st->execute([$empresaId, $desde, $hasta]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            if (($row['estado'] ?? '') === 'CANCELADA') {
                continue;
            }
            $tipo = 'programada';
            if (($row['estado'] ?? '') === 'REALIZADA') {
                $tipo = ((int) ($row['asistio'] ?? 0)) === 1 ? 'participo' : 'convocado';
            }
            $out[] = [
                'fecha' => $row['fecha'],
                'tipo' => $tipo,
                'sesion_id' => (int) $row['sesion_id'],
                'personal_id' => (int) $row['personal_id'],
                'dni' => $row['dni'],
                'nombres' => $row['nombres'],
                'apellidos' => $row['apellidos'],
                'cargo' => $row['cargo'],
                'cap_codigo' => $row['cap_codigo'],
                'cap_nombre' => $row['cap_nombre'],
                'resultado' => $tipo === 'participo' ? 'ASISTIO' : '',
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $row
     * @return list<array<string, mixed>>
     */
    private function eventosDeFila(array $row): array
    {
        $base = [
            'registro_id' => (int) $row['registro_id'],
            'personal_id' => (int) $row['personal_id'],
            'dni' => $row['dni'],
            'nombres' => $row['nombres'],
            'apellidos' => $row['apellidos'],
            'cargo' => $row['cargo'],
            'cap_codigo' => $row['cap_codigo'],
            'cap_nombre' => $row['cap_nombre'],
            'resultado' => $row['resultado'],
        ];
        $out = [];
        if (!empty($row['fecha_realizada'])) {
            $out[] = array_merge($base, ['fecha' => $row['fecha_realizada'], 'tipo' => 'realizada']);
        }
        if (!empty($row['fecha_evaluacion'])) {
            $out[] = array_merge($base, ['fecha' => $row['fecha_evaluacion'], 'tipo' => 'evaluacion']);
        }
        if (!empty($row['fecha_vence'])) {
            $out[] = array_merge($base, ['fecha' => $row['fecha_vence'], 'tipo' => 'vence']);
        }

        return $out;
    }
}
