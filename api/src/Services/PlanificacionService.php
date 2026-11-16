<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Support\UserContext;
use Bio\Api\Repositories\PlanificacionRepository;
use Bio\Api\Repositories\PuntoRepository;

final class PlanificacionService
{
    /** @var PlanificacionRepository */
    private $repo;
    /** @var PuntoRepository */
    private $puntos;
    /** @var PersonalRepository */
    private $personal;
    /** @var PlanificacionConflictService */
    private $conflicts;

    public function __construct()
    {
        $this->repo = new PlanificacionRepository();
        $this->puntos = new PuntoRepository();
        $this->personal = new PersonalRepository();
        $this->conflicts = new PlanificacionConflictService();
    }

    /** @param list<string> $granjas */
    public function semana(string $fechaInicio, array $granjas, ?string $tipo, bool $soloIncompletos, ?int $empresaId = null): array
    {
        $inicio = new \DateTimeImmutable($fechaInicio);
        $fin = $inicio->modify('+6 days');
        $puntos = $this->repo->puntosActivos($granjas, $tipo);
        $asignaciones = $this->repo->asignacionesRango($inicio->format('Y-m-d'), $fin->format('Y-m-d'), $granjas);
        if ($empresaId !== null && $empresaId > 0 && UserContext::isGrs()) {
            // Vista consolidada GRS filtrada por contratista
            $asignaciones = array_values(array_filter($asignaciones, static function (array $a) use ($empresaId): bool {
                return (int) ($a['empresa_id'] ?? 0) === $empresaId;
            }));
        }
        $grid = $this->buildGrid($puntos, $asignaciones, $inicio, $fin, $soloIncompletos);
        $resumen = $this->resumenCobertura($grid);

        return ['ok' => true, 'data' => ['grid' => $grid, 'resumen' => $resumen, 'turnos' => $this->repo->turnoCatalogo()]];
    }

    public function dia(string $fecha, array $granjas): array
    {
        $puntos = $this->repo->puntosActivos($granjas, null);
        $asignaciones = $this->repo->asignacionesRango($fecha, $fecha, $granjas);
        $turnos = $this->repo->turnoCatalogo();
        $lines = [];
        foreach ($puntos as $p) {
            $pid = (int) $p['id'];
            $turnosPunto = $this->puntos->turnos($pid);
            $segments = [];
            foreach ($turnosPunto as $tp) {
                $segs = array_values(array_filter($asignaciones, function ($a) use ($pid, $fecha, $tp) {
                    return (int) $a['punto_id'] === $pid && $a['fecha'] === $fecha && $a['turno_cod'] === $tp['turno_cod'];
                }));
                $req = (int) $tp['personal_requerido'];
                $count = count($segs);
                $estado = $count === 0 ? 'vacio' : ($count < $req ? 'incompleto' : 'completo');
                $packed = $this->packCelda($segs, $req, $estado);
                $segments[] = array_merge([
                    'turno_cod' => $tp['turno_cod'],
                    'hora_inicio' => $tp['hora_inicio_override'] ?? $tp['hora_inicio'],
                    'hora_fin' => $tp['hora_fin_override'] ?? $tp['hora_fin'],
                ], $packed);
            }
            $lines[] = ['punto' => $p, 'segmentos' => $segments];
        }

        return ['ok' => true, 'data' => ['fecha' => $fecha, 'lineas' => $lines, 'turnos' => $turnos]];
    }

    /** @param array<string, mixed> $body */
    public function guardarAsignacion(array $body, string $usuario, bool $forceWarnings = false): array
    {
        $personalId = (int) ($body['personal_id'] ?? 0);
        $puntoId = (int) ($body['punto_id'] ?? 0);
        $fecha = (string) ($body['fecha'] ?? '');
        $turno = (string) ($body['turno_cod'] ?? '');
        $id = isset($body['id']) ? (int) $body['id'] : null;
        if ($personalId < 1 || $puntoId < 1 || $fecha === '' || $turno === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Datos incompletos']];
        }
        $persona = $this->personal->find($personalId);
        if ($persona === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Personal no encontrado']];
        }
        UserContext::assertPersonalOwned($persona);
        if ($id !== null) {
            $prev = $this->repo->findAsignacion($id);
            UserContext::assertAsignacionOwned($prev);
        }
        $val = $this->conflicts->validar($personalId, $puntoId, $fecha, $turno, $id);
        if (!$val['ok']) {
            return ['ok' => false, 'error' => ['code' => 'conflict', 'message' => implode('; ', $val['blockers']), 'fields' => $val]];
        }
        if (!$forceWarnings && $val['warnings'] !== []) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'warnings',
                    'message' => implode('; ', $val['warnings']),
                    'warnings' => $val['warnings'],
                ],
            ];
        }
        $origen = UserContext::isGrs() ? 'GRS' : 'EMPRESA';
        if ($id !== null) {
            $this->repo->actualizarAsignacion($id, $personalId, $puntoId, $fecha, $turno);
            $newId = $id;
        } else {
            $newId = $this->repo->crearAsignacion($personalId, $puntoId, $fecha, $turno, $usuario, $origen);
        }

        return ['ok' => true, 'data' => ['id' => $newId, 'warnings' => $val['warnings']]];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardarAsignacionesLote(array $body, string $usuario, bool $forceWarnings = false): array
    {
        $personalId = (int) ($body['personal_id'] ?? 0);
        $items = $body['items'] ?? [];
        if ($personalId < 1 || !is_array($items) || $items === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique personal y al menos una celda']];
        }
        $persona = $this->personal->find($personalId);
        if ($persona === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Personal no encontrado']];
        }
        UserContext::assertPersonalOwned($persona);

        $creadas = [];
        $fallidas = [];
        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }
            $sub = [
                'personal_id' => $personalId,
                'punto_id' => (int) ($item['punto_id'] ?? 0),
                'fecha' => (string) ($item['fecha'] ?? ''),
                'turno_cod' => (string) ($item['turno_cod'] ?? ''),
            ];
            $r = $this->guardarAsignacion($sub, $usuario, $forceWarnings);
            if ($r['ok']) {
                $creadas[] = array_merge($sub, ['id' => $r['data']['id'] ?? null]);
            } else {
                $fallidas[] = [
                    'index' => $idx,
                    'punto_id' => $sub['punto_id'],
                    'fecha' => $sub['fecha'],
                    'turno_cod' => $sub['turno_cod'],
                    'code' => $r['error']['code'] ?? 'error',
                    'message' => $r['error']['message'] ?? 'Error',
                ];
            }
        }

        if ($creadas === [] && $fallidas === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Sin celdas válidas']];
        }

        return [
            'ok' => true,
            'data' => [
                'creadas' => count($creadas),
                'fallidas' => count($fallidas),
                'detalle_creadas' => $creadas,
                'detalle_fallidas' => $fallidas,
            ],
        ];
    }

    public function eliminarAsignacion(int $id): array
    {
        $asig = $this->repo->findAsignacion($id);
        if ($asig === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        UserContext::assertAsignacionOwned($asig);
        $this->repo->eliminarAsignacion($id);

        return ['ok' => true, 'data' => null];
    }

    /**
     * Reemplazo de personal. Alcance:
     *  - 'dia' (por defecto): solo la asignación indicada.
     *  - 'rango': todas las asignaciones del titular entre fecha_desde y fecha_hasta
     *    (opcionalmente solo en el mismo punto y turno si mismo_punto = true).
     *
     * @param array<string, mixed> $body
     */
    public function reemplazo(array $body, string $usuario): array
    {
        if (UserContext::isGrs()) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'El reemplazo lo gestiona la empresa sobre personas asignadas']];
        }
        $asignacionId = (int) ($body['asignacion_id'] ?? 0);
        $reemplazoId = (int) ($body['personal_reemplazo_id'] ?? 0);
        $motivo = trim((string) ($body['motivo'] ?? ''));
        if ($asignacionId < 1 || $reemplazoId < 1 || $motivo === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique asignación, reemplazo y motivo']];
        }
        $asig = $this->repo->findAsignacion($asignacionId);
        if ($asig === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Asignacion no encontrada']];
        }
        UserContext::assertAsignacionOwned($asig);
        $reemplazo = $this->personal->find($reemplazoId);
        if ($reemplazo === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Personal no encontrado']];
        }
        UserContext::assertPersonalOwned($reemplazo);
        if ((int) ($asig['empresa_id'] ?? 0) !== (int) ($reemplazo['empresa_id'] ?? 0)) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'El reemplazo debe ser de su misma empresa']];
        }
        $originalId = (int) $asig['personal_id'];
        if ($originalId === $reemplazoId) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'El reemplazo debe ser otra persona']];
        }

        $alcance = (string) ($body['alcance'] ?? 'dia');
        $objetivos = [$asig];
        if ($alcance === 'rango') {
            $desde = (string) ($body['fecha_desde'] ?? $asig['fecha']);
            $hasta = (string) ($body['fecha_hasta'] ?? $asig['fecha']);
            if (!self::esFecha($desde) || !self::esFecha($hasta) || $hasta < $desde) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Rango de fechas inválido']];
            }
            if ((new \DateTimeImmutable($desde))->diff(new \DateTimeImmutable($hasta))->days > 62) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'El rango no puede superar 62 días']];
            }
            $mismoPunto = !empty($body['mismo_punto']);
            $objetivos = array_values(array_filter(
                $this->repo->asignacionesPersonaEnRango($originalId, $desde, $hasta),
                static function (array $a) use ($asig, $mismoPunto): bool {
                    if (!$mismoPunto) {
                        return true;
                    }

                    return (int) $a['punto_id'] === (int) $asig['punto_id'] && $a['turno_cod'] === $asig['turno_cod'];
                }
            ));
            if ($objetivos === []) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'El titular no tiene asignaciones en ese rango']];
            }
        }

        $hechos = [];
        $fallidos = [];
        foreach ($objetivos as $obj) {
            $oid = (int) $obj['id'];
            $val = $this->conflicts->validar($reemplazoId, (int) $obj['punto_id'], (string) $obj['fecha'], (string) $obj['turno_cod'], $oid);
            if (!$val['ok']) {
                $fallidos[] = ['asignacion_id' => $oid, 'fecha' => $obj['fecha'], 'turno_cod' => $obj['turno_cod'], 'message' => implode('; ', $val['blockers'])];
                continue;
            }
            $rid = $this->repo->crearReemplazo($oid, (int) $obj['personal_id'], $reemplazoId, $motivo, $usuario);
            $hechos[] = ['asignacion_id' => $oid, 'fecha' => $obj['fecha'], 'turno_cod' => $obj['turno_cod'], 'reemplazo_id' => $rid];
        }
        if ($hechos === []) {
            return ['ok' => false, 'error' => [
                'code' => 'conflict',
                'message' => $fallidos[0]['message'] ?? 'No se pudo registrar el reemplazo',
                'fields' => ['fallidos' => $fallidos],
            ]];
        }

        return ['ok' => true, 'data' => [
            'reemplazo_id' => $hechos[0]['reemplazo_id'],
            'reemplazadas' => count($hechos),
            'fallidas' => count($fallidos),
            'detalle_fallidas' => $fallidos,
        ]];
    }

    /**
     * Modo rápido: varias personas al mismo punto y turno en varios días (por defecto toda la semana).
     *
     * @param array<string, mixed> $body
     */
    public function asignarRapido(array $body, string $usuario, bool $forceWarnings = false): array
    {
        $puntoId = (int) ($body['punto_id'] ?? 0);
        $turno = (string) ($body['turno_cod'] ?? '');
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($body['personal_ids'] ?? null) ? $body['personal_ids'] : []))));
        $fechas = array_values(array_unique(array_filter(
            is_array($body['fechas'] ?? null) ? array_map('strval', $body['fechas']) : [],
            static function (string $f): bool {
                return self::esFecha($f);
            }
        )));
        sort($fechas);
        if ($puntoId < 1 || $turno === '' || $ids === [] || $fechas === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione punto, turno, al menos una persona y un día']];
        }
        if (count($ids) * count($fechas) > 400) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Demasiadas asignaciones en una sola operación']];
        }
        $creadas = [];
        $fallidas = [];
        $conAdvertencia = 0;
        foreach ($ids as $pid) {
            foreach ($fechas as $fecha) {
                $sub = ['personal_id' => $pid, 'punto_id' => $puntoId, 'fecha' => $fecha, 'turno_cod' => $turno];
                $r = $this->guardarAsignacion($sub, $usuario, $forceWarnings);
                if ($r['ok']) {
                    $creadas[] = array_merge($sub, ['id' => $r['data']['id'] ?? null]);
                    continue;
                }
                $code = (string) ($r['error']['code'] ?? 'error');
                if ($code === 'warnings') {
                    $conAdvertencia++;
                }
                $fallidas[] = array_merge($sub, [
                    'code' => $code,
                    'message' => (string) ($r['error']['message'] ?? 'Error'),
                ]);
            }
        }

        return [
            'ok' => true,
            'data' => [
                'creadas' => count($creadas),
                'fallidas' => count($fallidas),
                'con_advertencias' => $conAdvertencia,
                'detalle_creadas' => $creadas,
                'detalle_fallidas' => $fallidas,
            ],
        ];
    }

    /**
     * Copia la semana anterior (u otra) a la semana destino validando cruces y cupos.
     * La empresa solo copia asignaciones de su propio personal.
     *
     * @param list<string> $granjas
     */
    public function copiarSemana(array $granjas, string $origen, string $destino, string $usuario): array
    {
        if (!self::esFecha($origen) || !self::esFecha($destino) || $granjas === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Datos incompletos']];
        }
        $offset = (int) round(((new \DateTimeImmutable($destino))->getTimestamp() - (new \DateTimeImmutable($origen))->getTimestamp()) / 86400);
        $hastaOrigen = (new \DateTimeImmutable($origen))->modify('+6 days')->format('Y-m-d');
        $fuente = $this->repo->asignacionesRango($origen, $hastaOrigen, $granjas);
        $isGrs = UserContext::isGrs();
        $eid = UserContext::empresaId();
        $copiadas = 0;
        $omitidas = [];
        $vistos = [];
        foreach ($fuente as $a) {
            if (!$isGrs) {
                if ($eid === null || (int) ($a['empresa_id'] ?? 0) !== $eid || ($a['origen'] ?? 'EMPRESA') === 'GRS') {
                    continue;
                }
            }
            // Si fue reemplazo, se copia al titular original
            $pid = (int) $a['personal_id'];
            if ((int) ($a['es_reemplazo'] ?? 0) === 1 && !empty($a['personal_original_id'])) {
                $pid = (int) $a['personal_original_id'];
            }
            $fecha = (new \DateTimeImmutable((string) $a['fecha']))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
            $k = $pid . '|' . $a['punto_id'] . '|' . $fecha . '|' . $a['turno_cod'];
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            if ($this->repo->existeAsignacion($pid, (int) $a['punto_id'], $fecha, (string) $a['turno_cod'])) {
                continue;
            }
            $val = $this->conflicts->validar($pid, (int) $a['punto_id'], $fecha, (string) $a['turno_cod'], null);
            if (!$val['ok']) {
                $omitidas[] = [
                    'personal' => trim(($a['apellidos'] ?? '') . ', ' . ($a['nombres'] ?? '')),
                    'punto' => (string) ($a['punto_nombre'] ?? ''),
                    'fecha' => $fecha,
                    'turno_cod' => $a['turno_cod'],
                    'message' => implode('; ', $val['blockers']),
                ];
                continue;
            }
            $this->repo->crearAsignacion($pid, (int) $a['punto_id'], $fecha, (string) $a['turno_cod'], $usuario, $isGrs ? (string) ($a['origen'] ?? 'GRS') : 'EMPRESA');
            $copiadas++;
        }
        $this->repo->registrarCopiaSemana(count($granjas) === 1 ? $granjas[0] : 'VARIAS', $origen, $destino, $usuario);

        return ['ok' => true, 'data' => ['copiadas' => $copiadas, 'omitidas' => count($omitidas), 'detalle_omitidas' => array_slice($omitidas, 0, 50)]];
    }

    private static function esFecha(string $f): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
    }

    public function personalDisponible(string $fecha, string $turnoCod, int $puntoId, array $query): array
    {
        $punto = $this->puntos->find($puntoId);
        $granjaPunto = $punto['granja_cod'] ?? '';
        $filters = [
            'q' => $query['q'] ?? '',
            'empresa' => $query['empresa'] ?? '',
            'estado' => 'ACTIVO',
            'cargo' => $query['cargo'] ?? '',
            'granja_base' => $query['granja_base'] ?? '',
        ];
        UserContext::applyPersonalScope($filters);
        $list = $this->personal->listar($filters, 1, 200);
        $sugeridos = [];
        $otros = [];
        $validaciones = $this->conflicts->validarLote($list['items'], $puntoId, $fecha, $turnoCod);
        foreach ($list['items'] as $p) {
            $pid = (int) $p['id'];
            $val = $validaciones[$pid] ?? null;
            if ($val === null || !$val['ok']) {
                continue;
            }
            $row = array_merge($p, ['warnings' => $val['warnings'], 'fuera_granja_base' => ($p['granja_base_cod'] ?? '') !== '' && ($p['granja_base_cod'] ?? '') !== $granjaPunto]);
            if (($p['granja_base_cod'] ?? '') === $granjaPunto) {
                $sugeridos[] = $row;
            } else {
                $otros[] = $row;
            }
        }

        return ['ok' => true, 'data' => ['misma_granja' => $sugeridos, 'otras' => $otros]];
    }

    /**
     * @param list<array<string, mixed>> $puntos
     * @param list<array<string, mixed>> $asignaciones
     */
    private function buildGrid(array $puntos, array $asignaciones, \DateTimeImmutable $inicio, \DateTimeImmutable $fin, bool $soloIncompletos): array
    {
        $days = [];
        for ($d = $inicio; $d <= $fin; $d = $d->modify('+1 day')) {
            $days[] = $d->format('Y-m-d');
        }
        // Índice punto|fecha|turno -> asignaciones (evita filtrar la lista completa por cada celda)
        $idx = [];
        foreach ($asignaciones as $a) {
            $idx[(int) $a['punto_id'] . '|' . $a['fecha'] . '|' . $a['turno_cod']][] = $a;
        }
        $rows = [];
        $turnosPorPunto = $this->puntos->turnosPorPuntos(array_map(static function (array $p): int {
            return (int) $p['id'];
        }, $puntos));
        foreach ($puntos as $p) {
            $pid = (int) $p['id'];
            $turnos = $turnosPorPunto[$pid] ?? [];
            // Turnos sin cupo (0 personas) y sin asignaciones no se muestran
            $turnos = array_values(array_filter($turnos, function (array $tp) use ($idx, $pid, $days): bool {
                if ((int) $tp['personal_requerido'] > 0) {
                    return true;
                }
                foreach ($days as $day) {
                    if (!empty($idx[$pid . '|' . $day . '|' . $tp['turno_cod']])) {
                        return true;
                    }
                }

                return false;
            }));
            usort($turnos, static function (array $a, array $b): int {
                return strcmp((string) $a['hora_inicio'], (string) $b['hora_inicio']);
            });
            if ($turnos === []) {
                continue;
            }
            $celdas = [];
            $incompleto = false;
            foreach ($days as $day) {
                $celdas[$day] = [];
                foreach ($turnos as $tp) {
                    $tc = $tp['turno_cod'];
                    $assigned = $idx[$pid . '|' . $day . '|' . $tc] ?? [];
                    $req = (int) $tp['personal_requerido'];
                    $count = count($assigned);
                    $estado = $count === 0 ? 'vacio' : ($count < $req ? 'incompleto' : 'completo');
                    if ($estado !== 'completo') {
                        $incompleto = true;
                    }
                    $celdas[$day][$tc] = $this->packCelda($assigned, $req, $estado);
                }
            }
            if ($soloIncompletos && !$incompleto) {
                continue;
            }
            $rows[] = [
                'punto' => $p,
                'turnos' => $turnos,
                'celdas' => $celdas,
            ];
        }

        return ['dias' => $days, 'filas' => $rows];
    }

    /**
     * @param list<array<string, mixed>> $assigned
     * @return array<string, mixed>
     */
    private function packCelda(array $assigned, int $req, string $estado): array
    {
        return [
            'requerido' => $req,
            'asignado' => count($assigned),
            'estado' => $estado,
            'asignaciones' => $this->mapAsignacionesCelda($assigned),
            'ocupacion_externa' => $this->ocupacionExterna($assigned),
        ];
    }

    /**
     * @param list<array<string, mixed>> $assigned
     * @return list<array<string, mixed>>
     */
    /**
     * @param list<array<string, mixed>> $assigned
     * @return list<array<string, mixed>>
     */
    private function mapAsignacionesCelda(array $assigned): array
    {
        $isGrs = UserContext::isGrs();
        $visibles = $isGrs
            ? $assigned
            : array_values(array_filter($assigned, static function (array $row): bool {
                return UserContext::asignacionEditable($row);
            }));

        return array_map(function (array $row) {
            return [
                'id' => (int) $row['id'],
                'personal_id' => (int) $row['personal_id'],
                'nombres' => (string) ($row['nombres'] ?? ''),
                'apellidos' => (string) ($row['apellidos'] ?? ''),
                'cargo' => (string) ($row['cargo'] ?? ''),
                'dni' => (string) ($row['dni'] ?? ''),
                'fecha' => (string) ($row['fecha'] ?? ''),
                'turno_cod' => (string) ($row['turno_cod'] ?? ''),
                'punto_id' => (int) ($row['punto_id'] ?? 0),
                'es_reemplazo' => (int) ($row['es_reemplazo'] ?? 0),
                'reemplazo_motivo' => (string) ($row['reemplazo_motivo'] ?? ''),
                'reemplaza_a' => trim((string) ($row['original_apellidos'] ?? '') . ', ' . (string) ($row['original_nombres'] ?? ''), ', '),
                'original' => !empty($row['personal_original_id']) ? [
                    'personal_id' => (int) $row['personal_original_id'],
                    'nombres' => (string) ($row['original_nombres'] ?? ''),
                    'apellidos' => (string) ($row['original_apellidos'] ?? ''),
                    'cargo' => (string) ($row['original_cargo'] ?? ''),
                ] : null,
                'empresa_id' => isset($row['empresa_id']) ? (int) $row['empresa_id'] : null,
                'empresa_codigo' => (string) ($row['empresa_codigo'] ?? ''),
                'empresa_nombre' => $this->etiquetaOcupante($row),
                'origen' => (string) ($row['origen'] ?? 'EMPRESA'),
                'editable' => UserContext::isGrs()
                    || (UserContext::asignacionEditable($row) && ($row['origen'] ?? 'EMPRESA') !== 'GRS'),
            ];
        }, $visibles);
    }

    /**
     * @param list<array<string, mixed>> $assigned
     * @return list<array{etiqueta: string, cantidad: int}>
     */
    private function ocupacionExterna(array $assigned): array
    {
        if (UserContext::isGrs()) {
            return [];
        }
        $eid = UserContext::empresaId();
        $grupos = [];
        foreach ($assigned as $row) {
            if ($eid !== null && (int) ($row['empresa_id'] ?? 0) === $eid) {
                continue;
            }
            $etiqueta = $this->etiquetaOcupante($row);
            if (!isset($grupos[$etiqueta])) {
                $grupos[$etiqueta] = 0;
            }
            $grupos[$etiqueta]++;
        }
        $out = [];
        foreach ($grupos as $etiqueta => $cantidad) {
            $out[] = ['etiqueta' => $etiqueta, 'cantidad' => (int) $cantidad];
        }
        usort($out, static function (array $a, array $b): int {
            return strcmp($a['etiqueta'], $b['etiqueta']);
        });

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function etiquetaOcupante(array $row): string
    {
        if (($row['origen'] ?? '') === 'GRS' || ($row['personal_empresa'] ?? ($row['empresa'] ?? '')) === 'GRS') {
            return 'GRS (planificación central)';
        }

        return $this->etiquetaEmpresaLegible($row);
    }

    /** @param array<string, mixed> $row */
    private function etiquetaEmpresaLegible(array $row): string
    {
        $cod = trim((string) ($row['empresa_codigo'] ?? ''));
        $razon = trim((string) ($row['empresa_razon_social'] ?? ''));
        if ($razon !== '' && $cod !== '') {
            $nombre = function_exists('mb_substr') ? mb_substr($razon, 0, 36, 'UTF-8') : substr($razon, 0, 36);

            return $nombre . ' · ' . $cod;
        }
        if ($razon !== '') {
            return function_exists('mb_substr') ? mb_substr($razon, 0, 40, 'UTF-8') : substr($razon, 0, 40);
        }
        if ($cod !== '') {
            return $cod;
        }

        return 'Otra empresa';
    }

    /** @param array<string, mixed> $row */
    private function etiquetaEmpresaFila(array $row): string
    {
        return $this->etiquetaEmpresaLegible($row);
    }

    private function resumenCobertura(array $grid): array
    {
        $req = 0;
        $ok = 0;
        $porGranja = [];
        foreach ($grid['filas'] as $fila) {
            $gc = (string) $fila['punto']['granja_cod'];
            if (!isset($porGranja[$gc])) {
                $porGranja[$gc] = ['requeridos' => 0, 'cubiertos' => 0];
            }
            foreach ($fila['celdas'] as $dayCells) {
                foreach ($dayCells as $cell) {
                    $req += $cell['requerido'];
                    $porGranja[$gc]['requeridos'] += $cell['requerido'];
                    $c = min($cell['asignado'], $cell['requerido']);
                    $ok += $c;
                    $porGranja[$gc]['cubiertos'] += $c;
                }
            }
        }

        return [
            'global' => ['requeridos' => $req, 'cubiertos' => $ok],
            'por_granja' => $porGranja,
        ];
    }
}
