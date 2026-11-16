<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Bootstrap\Database;
use Bio\Api\Support\UserContext;
use Bio\Api\Repositories\CapacitacionRepository;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Repositories\PlanificacionRepository;
use Bio\Api\Repositories\PuntoRepository;
use PDO;

final class PlanificacionConflictService
{
    const AVISO_CAPACITACIONES = true;

    /** @var array<int, array<string, mixed>|null> */
    private $puntoCache = [];
    /** @var array<int, list<int>> */
    private $capsPuntoCache = [];
    /** @var array<string, array<string, mixed>>|null */
    private $catalogCache = null;
    /** @var array<string, array<string, mixed>> */
    private $puntoTurnoCache = [];
    /** @var array<int, array<string, mixed>> */
    private $capMetaCache = [];
    /** @var CapacitacionRepository */
    private $capRepo;
    /** @var array<int, bool> */
    private $puntosTurnoCargados = [];
    /** @var array<string, string>|null */
    private $configCache = null;
    /** @var PlanificacionRepository */
    private $plan;
    /** @var PersonalRepository */
    private $personal;
    /** @var PuntoRepository */
    private $puntos;
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->plan = new PlanificacionRepository();
        $this->personal = new PersonalRepository();
        $this->puntos = new PuntoRepository();
        $this->capRepo = new CapacitacionRepository();
        $this->pdo = Database::pdo();
    }

    /**
     * @return array{ok: bool, blockers: list<string>, warnings: list<string>}
     */
    public function validar(int $personalId, int $puntoId, string $fecha, string $turnoCod, ?int $excludeAsignacionId): array
    {
        $per = $this->personal->find($personalId);
        if ($per === null) {
            return ['ok' => false, 'blockers' => ['Personal no existe'], 'warnings' => []];
        }
        $punto = $this->punto($puntoId);
        if ($punto === null) {
            return ['ok' => false, 'blockers' => ['Punto no existe'], 'warnings' => []];
        }
        list($desde, $hasta) = $this->rango($fecha);
        $otras = $this->plan->asignacionesPersonaEnRango($personalId, $desde, $hasta, $excludeAsignacionId);
        $this->precargarTurnos(array_merge([$puntoId], array_map(function ($a) {
            return (int) $a['punto_id'];
        }, $otras)));
        $caps = null;
        if (self::debeValidarCapacitaciones()) {
            $caps = $this->personal->capacitaciones($personalId);
        }

        return $this->evaluar($per, $punto, $fecha, $turnoCod, $otras, $caps, $excludeAsignacionId);
    }

    /**
     * @param list<array<string, mixed>> $personas
     * @return array<int, array{ok: bool, blockers: list<string>, warnings: list<string>}>
     */
    public function validarLote(array $personas, int $puntoId, string $fecha, string $turnoCod): array
    {
        $out = [];
        $punto = $this->punto($puntoId);
        if ($punto === null || $personas === []) {
            return $out;
        }
        $ids = array_map(function ($p) {
            return (int) $p['id'];
        }, $personas);
        list($desde, $hasta) = $this->rango($fecha);
        $porPersona = [];
        $puntoIds = [$puntoId];
        foreach ($this->plan->asignacionesPersonasEnRango($ids, $desde, $hasta) as $a) {
            $porPersona[(int) $a['personal_id']][] = $a;
            $puntoIds[] = (int) $a['punto_id'];
        }
        $this->precargarTurnos($puntoIds);
        $capsPorPersona = [];
        if (self::debeValidarCapacitaciones()) {
            foreach ($this->personal->capacitacionesDe($ids) as $c) {
                $capsPorPersona[(int) $c['personal_id']][] = $c;
            }
        }
        foreach ($personas as $per) {
            $pid = (int) $per['id'];
            $out[$pid] = $this->evaluar(
                $per,
                $punto,
                $fecha,
                $turnoCod,
                $porPersona[$pid] ?? [],
                self::debeValidarCapacitaciones() ? ($capsPorPersona[$pid] ?? []) : null,
                null
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $per
     * @param array<string, mixed> $punto
     * @param list<array<string, mixed>> $otras
     * @param list<array<string, mixed>>|null $caps
     * @return array{ok: bool, blockers: list<string>, warnings: list<string>}
     */
    private function evaluar(array $per, array $punto, string $fecha, string $turnoCod, array $otras, ?array $caps, ?int $excludeAsignacionId): array
    {
        $blockers = [];
        $warnings = [];
        $puntoId = (int) $punto['id'];

        if (($per['estado'] ?? '') === 'CESADO') {
            $blockers[] = 'El personal esta cesado';
        }

        $catalog = $this->catalogo();
        $nuevo = $this->intervalo($fecha, $turnoCod, $puntoId, $catalog);
        if ($nuevo === null) {
            $blockers[] = 'Turno no configurado para el punto';
        } else {
            $cupoMsg = $this->validarCupoCelda($puntoId, $fecha, $turnoCod, $excludeAsignacionId);
            if ($cupoMsg !== null) {
                $blockers[] = $cupoMsg;
            }
        }

        if (!UserContext::isGrs()) {
            foreach ($otras as $a) {
                if ($a['fecha'] !== $fecha || $a['turno_cod'] !== $turnoCod) {
                    continue;
                }
                $puntoNom = trim((string) ($a['punto_nombre'] ?? ''));
                if ($puntoNom !== '') {
                    $blockers[] = 'Cruce de turno: ya asignado en «' . $puntoNom . '» el ' . self::dmy($fecha) . ' (' . $turnoCod . ')';
                } else {
                    $blockers[] = 'Cruce de turno: ya tiene otra asignación el ' . self::dmy($fecha) . ' (' . $turnoCod . ')';
                }
            }
        } else {
            foreach ($otras as $a) {
                $iv = $this->intervalo($a['fecha'], $a['turno_cod'], (int) $a['punto_id'], $catalog);
                if ($nuevo !== null && $iv !== null && $this->solapan($nuevo, $iv)) {
                    $blockers[] = 'Solapamiento con otra asignación el ' . self::dmy((string) $a['fecha']) . ' (' . $a['turno_cod'] . ')';
                }
            }
        }

        if (self::debeValidarCapacitaciones() && $caps !== null) {
            $reqCaps = $this->capsPunto($puntoId);
            $hoy = date('Y-m-d');
            foreach ($reqCaps as $capId) {
                $ok = false;
                foreach ($caps as $c) {
                    if ((int) $c['capacitacion_id'] !== $capId) {
                        continue;
                    }
                    if (strtoupper((string) ($c['resultado'] ?? 'APROBADO')) === 'DESAPROBADO') {
                        continue;
                    }
                    $vence = $c['fecha_vence'] ?? null;
                    if ($vence === null || $vence === '' || $vence >= $hoy) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    $meta = $this->capMeta($capId);
                    $warnings[] = 'Falta capacitacion vigente para el punto: ' . ($meta['codigo'] ?? '') . ' — ' . ($meta['nombre'] ?? '');
                }
            }
        }

        return ['ok' => $blockers === [], 'blockers' => $blockers, 'warnings' => $warnings];
    }

    /** @return array{0: string, 1: string} */
    private function rango(string $fecha): array
    {
        $d = new \DateTimeImmutable($fecha);

        return [$d->modify('-1 day')->format('Y-m-d'), $d->modify('+1 day')->format('Y-m-d')];
    }

    private function punto(int $puntoId): ?array
    {
        if (!array_key_exists($puntoId, $this->puntoCache)) {
            $this->puntoCache[$puntoId] = $this->puntos->find($puntoId);
        }

        return $this->puntoCache[$puntoId];
    }

    private static function debeValidarCapacitaciones(): bool
    {
        // Capacitaciones: registro y reportes; no bloquea la planificación (GRS por cargo, empresa por nombre).
        return false;
    }

    /** @return list<int> */
    private function capsPunto(int $puntoId): array
    {
        if (!isset($this->capsPuntoCache[$puntoId])) {
            $this->capsPuntoCache[$puntoId] = $this->puntos->capacitacionIds($puntoId);
        }

        return $this->capsPuntoCache[$puntoId];
    }

    /** @return array<string, mixed> */
    private function capMeta(int $capId): array
    {
        if (!isset($this->capMetaCache[$capId])) {
            $this->capMetaCache[$capId] = $this->capRepo->find($capId) ?? ['codigo' => '', 'nombre' => ''];
        }

        return $this->capMetaCache[$capId];
    }

    /** @return array<string, array<string, mixed>> */
    private function catalogo(): array
    {
        if ($this->catalogCache === null) {
            $this->catalogCache = $this->indexTurnos($this->plan->turnoCatalogo());
        }

        return $this->catalogCache;
    }

    /** @param list<int> $puntoIds */
    private function precargarTurnos(array $puntoIds): void
    {
        $faltan = [];
        foreach (array_unique($puntoIds) as $id) {
            if (!isset($this->puntosTurnoCargados[$id])) {
                $faltan[] = $id;
            }
        }
        if ($faltan === []) {
            return;
        }
        foreach ($this->plan->puntoTurnos($faltan) as $r) {
            $this->puntoTurnoCache[(int) $r['punto_id'] . '|' . $r['turno_cod']] = $r;
        }
        foreach ($faltan as $id) {
            $this->puntosTurnoCargados[$id] = true;
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function indexTurnos(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['codigo']] = $r;
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
     */
    private function intervalo(string $fecha, string $turnoCod, int $puntoId, array $catalog): ?array
    {
        $this->precargarTurnos([$puntoId]);
        $pt = $this->puntoTurnoCache[$puntoId . '|' . $turnoCod] ?? null;
        if ($pt === null) {
            return null;
        }
        $cat = $catalog[$turnoCod] ?? null;
        if ($cat === null) {
            return null;
        }
        $hi = $pt['hora_inicio_override'] ?? $cat['hora_inicio'];
        $hf = $pt['hora_fin_override'] ?? $cat['hora_fin'];
        $start = new \DateTimeImmutable($fecha . ' ' . $hi);
        $end = new \DateTimeImmutable($fecha . ' ' . $hf);
        if ((int) ($cat['cruza_medianoche'] ?? 0) === 1 && $end <= $start) {
            $end = $end->modify('+1 day');
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $a
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $b
     */
    private function solapan(array $a, array $b): bool
    {
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
    }

    /**
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $a
     * @param array{start: \DateTimeImmutable, end: \DateTimeImmutable} $b
     */
    private function gapHours(array $a, array $b): float
    {
        if ($a['end'] <= $b['start']) {
            return ($b['start']->getTimestamp() - $a['end']->getTimestamp()) / 3600;
        }
        if ($b['end'] <= $a['start']) {
            return ($a['start']->getTimestamp() - $b['end']->getTimestamp()) / 3600;
        }

        return -1;
    }

    private function validarCupoCelda(int $puntoId, string $fecha, string $turnoCod, ?int $excludeAsignacionId): ?string
    {
        $this->precargarTurnos([$puntoId]);
        $pt = $this->puntoTurnoCache[$puntoId . '|' . $turnoCod] ?? null;
        if ($pt === null) {
            return null;
        }
        $req = (int) ($pt['personal_requerido'] ?? 0);
        if ($req < 1) {
            return null;
        }
        $ocupados = $this->plan->contarAsignacionesCelda($puntoId, $fecha, $turnoCod, $excludeAsignacionId);
        if ($ocupados >= $req) {
            return 'El cupo de este turno ya está completo (incluye otras empresas o asignaciones GRS)';
        }

        return null;
    }

    /** @return array<string, string> */
    private function config(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }
        $st = $this->pdo->query('SELECT clave, valor FROM bio_config');

        $out = [];
        if ($st) {
            foreach ($st->fetchAll() as $r) {
                $out[(string) $r['clave']] = (string) $r['valor'];
            }
        }
        $this->configCache = $out;

        return $out;
    }

    /** YYYY-MM-DD → dd/mm/yyyy para mensajes al usuario. */
    private static function dmy(string $iso): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : $iso;
    }
}
