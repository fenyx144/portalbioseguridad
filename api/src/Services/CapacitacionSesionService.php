<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\CapacitacionRepository;
use Bio\Api\Repositories\CapacitacionSesionRepository;
use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Support\CapacitacionPolicy;
use Bio\Api\Support\UserContext;

/**
 * Sesiones de capacitación: programación (fecha, hora, lugar), convocados por cargo o por nombre,
 * asistencia y evaluación (nota / resultado). La empresa contratista gestiona sus sesiones; GRS consulta.
 */
final class CapacitacionSesionService
{
    /** @var CapacitacionSesionRepository */
    private $repo;
    /** @var PersonalRepository */
    private $personal;
    /** @var CapacitacionRepository */
    private $caps;

    public function __construct()
    {
        $this->repo = new CapacitacionSesionRepository();
        $this->personal = new PersonalRepository();
        $this->caps = new CapacitacionRepository();
    }

    /** @param array<string, string> $query */
    public function listar(array $query): array
    {
        $empresaId = null;
        if (!UserContext::isGrs()) {
            $empresaId = UserContext::empresaId();
            if ($empresaId === null) {
                return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Empresa no identificada']];
            }
        } elseif ((int) ($query['empresa_id'] ?? 0) > 0) {
            $empresaId = (int) $query['empresa_id'];
            if ((new EmpresaRepository())->find($empresaId) === null) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Empresa no existe']];
            }
        }
        $desde = (string) ($query['desde'] ?? '');
        $hasta = (string) ($query['hasta'] ?? '');
        $mes = (string) ($query['mes'] ?? '');
        if (!self::esFecha($desde) || !self::esFecha($hasta)) {
            if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $mes = date('Y-m');
            }
            $desde = $mes . '-01';
            $hasta = (new \DateTimeImmutable($desde))->modify('last day of this month')->format('Y-m-d');
        }
        $estado = strtoupper((string) ($query['estado'] ?? ''));
        if (!in_array($estado, ['PROGRAMADA', 'REALIZADA', 'CANCELADA'], true)) {
            $estado = '';
        }

        return [
            'ok' => true,
            'data' => [
                'items' => $this->repo->listar([
                    'empresa_id' => $empresaId,
                    'desde' => $desde,
                    'hasta' => $hasta,
                    'capacitacion_id' => (int) ($query['capacitacion_id'] ?? 0),
                    'estado' => $estado,
                ]),
                'desde' => $desde,
                'hasta' => $hasta,
                'empresa_id' => $empresaId,
                'puede_gestionar' => CapacitacionPolicy::canRegisterPersonalCap(),
            ],
        ];
    }

    public function detalle(int $id): array
    {
        $s = $this->repo->find($id);
        if ($s === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Sesión no encontrada']];
        }
        if (!UserContext::isGrs()) {
            $eid = UserContext::empresaId();
            if ($eid === null || (int) $s['empresa_id'] !== $eid) {
                return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'No puede ver esta sesión']];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'sesion' => $s,
                'participantes' => $this->repo->participantes($id),
                'puede_gestionar' => CapacitacionPolicy::canRegisterPersonalCap() && ($s['estado'] ?? '') !== 'CANCELADA',
            ],
        ];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body, string $usuario): array
    {
        CapacitacionPolicy::assertCanRegisterPersonalCap();
        $eid = UserContext::empresaId();
        if ($eid === null) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Empresa no identificada']];
        }
        $parsed = $this->parseBody($body);
        if ($parsed['error'] !== null) {
            return ['ok' => false, 'error' => $parsed['error']];
        }
        $ids = $this->resolverConvocados($body, $eid);
        if ($ids === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Hay trabajadores que no pertenecen a su empresa']];
        }
        if ($ids === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione al menos un trabajador o un cargo con personal activo']];
        }
        $id = $this->repo->crear($eid, $parsed['data'], $ids, $usuario);

        return ['ok' => true, 'data' => ['id' => $id, 'convocados' => count($ids)]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        if (($s['estado'] ?? '') === 'CANCELADA') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'La sesión está cancelada']];
        }
        $parsed = $this->parseBody($body);
        if ($parsed['error'] !== null) {
            return ['ok' => false, 'error' => $parsed['error']];
        }
        $personalIds = null;
        if (array_key_exists('personal_ids', $body) && ($s['estado'] ?? '') === 'PROGRAMADA') {
            $personalIds = $this->resolverConvocados($body, (int) $s['empresa_id']);
            if ($personalIds === null || $personalIds === []) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione al menos un trabajador']];
            }
        }
        $this->repo->actualizar($id, $parsed['data'], $personalIds);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /**
     * Agrega convocados por nombre (personal_ids) y/o por cargo (cargos).
     *
     * @param array<string, mixed> $body
     */
    public function agregarParticipantes(int $id, array $body): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        if (($s['estado'] ?? '') === 'CANCELADA') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'La sesión está cancelada']];
        }
        $ids = $this->resolverConvocados($body, (int) $s['empresa_id']);
        if ($ids === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Hay trabajadores que no pertenecen a su empresa']];
        }
        if ($ids === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'No hay personal para agregar']];
        }
        $n = $this->repo->agregarParticipantes($id, $ids);

        return ['ok' => true, 'data' => ['agregados' => $n]];
    }

    public function quitarParticipante(int $id, int $personalId): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        $p = $this->repo->participante($id, $personalId);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No está convocado']];
        }
        if ((int) $p['asistio'] === 1) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'No se puede quitar: ya tiene asistencia registrada']];
        }
        $this->repo->quitarParticipante($id, $personalId);

        return ['ok' => true, 'data' => null];
    }

    /**
     * Marca asistencia. Quien asistió queda registrado en su ficha (resultado PENDIENTE hasta evaluar);
     * si se desmarca, se elimina el registro generado por la sesión.
     *
     * @param array<string, mixed> $body
     */
    public function registrarAsistencia(int $id, array $body, string $usuario): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        if (($s['estado'] ?? '') === 'CANCELADA') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'La sesión está cancelada']];
        }
        $lista = $body['participantes'] ?? null;
        if (!is_array($lista) || $lista === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique participación']];
        }
        $capId = (int) $s['capacitacion_id'];
        $fechaSesion = (string) $s['fecha'];
        $vigencia = (int) ($s['vigencia_meses'] ?? 0);
        $asistieron = 0;
        foreach ($lista as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pid = (int) ($row['personal_id'] ?? 0);
            $part = $pid > 0 ? $this->repo->participante($id, $pid) : null;
            if ($part === null) {
                continue;
            }
            $asistio = !empty($row['asistio']);
            $registroId = isset($part['registro_cap_id']) ? (int) $part['registro_cap_id'] : 0;
            if ($registroId > 0 && !$this->repo->existeRegistroFicha($registroId)) {
                $registroId = 0;
            }
            if ($asistio) {
                $asistieron++;
                // Compatibilidad: si envían resultado en la asistencia, se usa como evaluación directa
                $resultado = strtoupper(trim((string) ($row['resultado'] ?? '')));
                if (!in_array($resultado, ['APROBADO', 'DESAPROBADO', 'PENDIENTE'], true)) {
                    $resultado = (string) ($part['resultado'] ?? 'PENDIENTE');
                }
                if ($registroId === 0) {
                    $registroId = $this->personal->addCapacitacion($pid, [
                        'capacitacion_id' => $capId,
                        'fecha_realizada' => $fechaSesion,
                        'fecha_vence' => $this->calcVence($fechaSesion, $vigencia),
                        'resultado' => $resultado,
                        'fecha_evaluacion' => $resultado === 'PENDIENTE' ? null : $fechaSesion,
                        'observacion' => 'Sesión #' . $id . ($s['lugar'] !== '' ? ' — ' . $s['lugar'] : ''),
                    ]);
                }
                $this->repo->marcarAsistencia($id, $pid, true, $registroId);
                if ($resultado !== 'PENDIENTE' && $resultado !== ($part['resultado'] ?? '')) {
                    $this->repo->guardarEvaluacion($id, $pid, null, $resultado, $fechaSesion, '');
                    $this->repo->actualizarRegistroFicha($registroId, null, $resultado, $fechaSesion);
                }
            } else {
                if ($registroId > 0) {
                    $this->repo->eliminarRegistroFicha($registroId);
                }
                $this->repo->marcarAsistencia($id, $pid, false, null);
            }
        }
        $this->repo->setEstado($id, 'REALIZADA');

        return ['ok' => true, 'data' => ['id' => $id, 'asistieron' => $asistieron]];
    }

    /**
     * Registra evaluación de los asistentes. Si se envía nota sin resultado, se calcula con la nota mínima.
     *
     * @param array<string, mixed> $body
     */
    public function registrarEvaluacion(int $id, array $body): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        if (($s['estado'] ?? '') !== 'REALIZADA') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Primero registre la asistencia de la sesión']];
        }
        $items = $body['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique al menos una evaluación']];
        }
        $fecha = (string) ($body['fecha_evaluacion'] ?? '');
        if (!self::esFecha($fecha)) {
            $fecha = (string) $s['fecha'];
        }
        $min = (float) ($s['nota_minima'] ?? 14);
        $guardados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $pid = (int) ($it['personal_id'] ?? 0);
            $part = $pid > 0 ? $this->repo->participante($id, $pid) : null;
            if ($part === null || (int) $part['asistio'] !== 1) {
                $omitidos++;
                continue;
            }
            $nota = null;
            if (isset($it['nota']) && $it['nota'] !== '' && $it['nota'] !== null && is_numeric($it['nota'])) {
                $nota = max(0.0, min(20.0, round((float) $it['nota'], 1)));
            }
            $resultado = strtoupper(trim((string) ($it['resultado'] ?? '')));
            if (!in_array($resultado, ['APROBADO', 'DESAPROBADO', 'PENDIENTE'], true)) {
                $resultado = $nota === null ? 'PENDIENTE' : ($nota >= $min ? 'APROBADO' : 'DESAPROBADO');
            }
            $obs = substr(trim((string) ($it['observacion'] ?? '')), 0, 255);
            $fechaEval = $resultado === 'PENDIENTE' ? null : $fecha;
            $this->repo->guardarEvaluacion($id, $pid, $nota, $resultado, $fechaEval, $obs);
            $rid = (int) ($part['registro_cap_id'] ?? 0);
            if ($rid > 0) {
                $this->repo->actualizarRegistroFicha($rid, $nota, $resultado, $fechaEval);
            }
            $guardados++;
        }

        return ['ok' => true, 'data' => ['guardados' => $guardados, 'omitidos' => $omitidos]];
    }

    public function cancelar(int $id): array
    {
        $s = $this->sesionPropia($id);
        if (isset($s['error'])) {
            return $s;
        }
        if (($s['estado'] ?? '') === 'REALIZADA') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'La sesión ya tiene asistencia registrada']];
        }
        $this->repo->setEstado($id, 'CANCELADA');

        return ['ok' => true, 'data' => null];
    }

    /** @return array<string, mixed> Sesión o ['ok' => false, 'error' => ...] */
    private function sesionPropia(int $id): array
    {
        CapacitacionPolicy::assertCanRegisterPersonalCap();
        $s = $this->repo->find($id);
        if ($s === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Sesión no encontrada']];
        }
        $eid = UserContext::empresaId();
        if ($eid === null || (int) $s['empresa_id'] !== $eid) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'No puede modificar esta sesión']];
        }

        return $s;
    }

    /**
     * Une convocados por nombre y por cargo. null si algún id no pertenece a la empresa.
     *
     * @param array<string, mixed> $body
     * @return list<int>|null
     */
    private function resolverConvocados(array $body, int $empresaId): ?array
    {
        $ids = [];
        if (isset($body['personal_ids']) && is_array($body['personal_ids'])) {
            foreach ($body['personal_ids'] as $x) {
                $pid = (int) $x;
                if ($pid < 1) {
                    continue;
                }
                $p = $this->personal->find($pid);
                if ($p === null || (int) ($p['empresa_id'] ?? 0) !== $empresaId) {
                    return null;
                }
                $ids[] = $pid;
            }
        }
        if (isset($body['cargos']) && is_array($body['cargos'])) {
            $ids = array_merge($ids, $this->personal->idsPorCargos($empresaId, array_map('strval', $body['cargos'])));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $body
     * @return array{data: array<string, mixed>, error: array<string, string>|null}
     */
    private function parseBody(array $body): array
    {
        $capId = (int) ($body['capacitacion_id'] ?? 0);
        $fecha = trim((string) ($body['fecha'] ?? ''));
        if ($capId < 1 || $this->caps->find($capId) === null) {
            return ['data' => [], 'error' => ['code' => 'validation', 'message' => 'Capacitación inválida']];
        }
        if (!self::esFecha($fecha)) {
            return ['data' => [], 'error' => ['code' => 'validation', 'message' => 'Fecha inválida']];
        }
        $hora = trim((string) ($body['hora_inicio'] ?? ''));
        if ($hora !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
            return ['data' => [], 'error' => ['code' => 'validation', 'message' => 'Hora inválida']];
        }
        $notaMin = isset($body['nota_minima']) && is_numeric($body['nota_minima']) ? (float) $body['nota_minima'] : 14.0;

        return [
            'data' => [
                'capacitacion_id' => $capId,
                'fecha' => $fecha,
                'hora_inicio' => $hora === '' ? null : $hora,
                'lugar' => substr(trim((string) ($body['lugar'] ?? '')), 0, 120),
                'nota_minima' => max(0.0, min(20.0, $notaMin)),
                'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 255),
            ],
            'error' => null,
        ];
    }

    private static function esFecha(string $f): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
    }

    private function calcVence(string $fechaRealizada, int $vigenciaMeses): ?string
    {
        if ($vigenciaMeses <= 0) {
            return null;
        }

        return (new \DateTimeImmutable($fechaRealizada))
            ->modify('+' . $vigenciaMeses . ' months')
            ->format('Y-m-d');
    }
}
