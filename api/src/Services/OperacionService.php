<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\OperacionRepository;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Support\UserContext;

final class OperacionService
{
    /** @var OperacionRepository */
    private $repo;
    /** @var PersonalRepository */
    private $personal;

    public function __construct()
    {
        $this->repo = new OperacionRepository();
        $this->personal = new PersonalRepository();
    }

    public function puestos(): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->puestos()]];
    }

    public function requerimientos(): array
    {
        $puestos = $this->repo->puestos();
        $raw = $this->repo->requerimientos();
        $filas = [];
        foreach ($raw as $r) {
            $key = $r['zona'] . '|' . $r['granja_nombre'];
            if (!isset($filas[$key])) {
                $filas[$key] = [
                    'zona' => $r['zona'],
                    'granja_nombre' => $r['granja_nombre'],
                    'granja_cod' => $r['granja_cod'],
                    'orden' => (int) $r['orden'],
                    'celdas' => [],
                ];
            }
            $filas[$key]['celdas'][$r['puesto_cod']] = [
                'id' => (int) $r['id'],
                'cantidad' => (float) $r['cantidad'],
            ];
        }
        $items = array_values($filas);
        usort($items, static function (array $a, array $b): int {
            return [$a['orden'], $a['zona'], $a['granja_nombre']] <=> [$b['orden'], $b['zona'], $b['granja_nombre']];
        });

        return ['ok' => true, 'data' => ['puestos' => $puestos, 'filas' => $items]];
    }

    /** @param array<string, mixed> $body */
    public function guardarRequerimientos(array $body): array
    {
        UserContext::requireGrs();
        if (!empty($body['zona']) || !empty($body['granja_nombre'])) {
            $zona = trim((string) ($body['zona'] ?? ''));
            $granja = trim((string) ($body['granja_nombre'] ?? ''));
            if ($granja === '') {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique la granja']];
            }
            $this->repo->agregarGranjaRequerimiento(substr($zona, 0, 40), substr($granja, 0, 120));
        }
        $items = [];
        foreach ((array) ($body['items'] ?? []) as $it) {
            if (!is_array($it) || !isset($it['id'])) {
                continue;
            }
            $items[] = ['id' => (int) $it['id'], 'cantidad' => (float) ($it['cantidad'] ?? 0)];
        }
        if ($items !== []) {
            $this->repo->guardarCantidades($items);
        }

        return $this->requerimientos();
    }

    /** @param array<string, string> $q */
    public function asistencia(array $q): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->listarAsistencia($q)]];
    }

    /** @param array<string, mixed> $body */
    public function marcar(array $body, string $usuario, bool $desdeApp): array
    {
        $tipo = strtoupper(trim((string) ($body['tipo'] ?? 'ENTRADA')));
        if (!in_array($tipo, ['ENTRADA', 'SALIDA'], true)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Tipo inválido']];
        }
        $personalId = (int) ($body['personal_id'] ?? 0);
        $propio = $this->personal->findPorUsuario($usuario);
        if ($propio !== null && !$this->esAdminEmpresa($usuario, (int) ($propio['empresa_id'] ?? 0))) {
            $personalId = (int) $propio['id'];
        }
        if ($personalId < 1 && !empty($body['dni'])) {
            $porDni = $this->personal->findByDni(trim((string) $body['dni']));
            $personalId = $porDni ? (int) $porDni['id'] : 0;
        }
        $persona = $personalId > 0 ? $this->personal->find($personalId) : null;
        if ($persona === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Personal no encontrado']];
        }
        UserContext::assertPersonalOwned($persona);
        $cuando = trim((string) ($body['marcado_en'] ?? ''));
        if ($cuando === '') {
            $cuando = date('Y-m-d H:i:s');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $cuando)) {
            $cuando = str_replace('T', ' ', substr($cuando, 0, 16)) . ':00';
        }
        $id = $this->repo->crearAsistencia([
            'asignacion_id' => !empty($body['asignacion_id']) ? (int) $body['asignacion_id'] : null,
            'personal_id' => $personalId,
            'empresa_id' => $persona['empresa_id'] !== null ? (int) $persona['empresa_id'] : null,
            'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? $persona['granja_base_cod'] ?? '')), 0, 20),
            'puesto_cod' => substr(trim((string) ($body['puesto_cod'] ?? '')), 0, 30),
            'tipo' => $tipo,
            'marcado_en' => $cuando,
            'origen' => $desdeApp ? 'APP' : 'PORTAL',
            'usuario_registro' => substr($usuario, 0, 30),
            'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 255),
        ]);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, string> $q */
    public function alcoholemia(array $q): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->listarAlcoholemia($q)]];
    }

    /** @param array<string, mixed> $body */
    public function registrarAlcoholemia(array $body, string $usuario, bool $desdeApp): array
    {
        $personalId = (int) ($body['personal_id'] ?? 0);
        $persona = $this->personal->find($personalId);
        if ($persona === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Personal no encontrado']];
        }
        UserContext::assertPersonalOwned($persona);
        $resultado = strtoupper(trim((string) ($body['resultado'] ?? '')));
        if (!in_array($resultado, ['NEGATIVO', 'POSITIVO', 'NO_SE_REALIZO'], true)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Resultado inválido']];
        }
        $fecha = trim((string) ($body['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Fecha inválida']];
        }
        $hora = trim((string) ($body['hora'] ?? ''));
        $id = $this->repo->crearAlcoholemia([
            'personal_id' => $personalId,
            'empresa_id' => $persona['empresa_id'] !== null ? (int) $persona['empresa_id'] : null,
            'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? '')), 0, 20),
            'fecha' => $fecha,
            'hora' => $hora !== '' ? $hora : null,
            'resultado' => $resultado,
            'valor' => ($body['valor'] ?? '') === '' ? null : (float) $body['valor'],
            'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 255),
            'accion' => substr(trim((string) ($body['accion'] ?? '')), 0, 255),
            'origen' => $desdeApp ? 'APP' : 'PORTAL',
            'usuario_registro' => substr($usuario, 0, 30),
        ]);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, string> $q */
    public function anomalias(array $q): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->listarAnomalias($q)]];
    }

    /** @param array<string, mixed> $body */
    public function crearAnomalia(array $body, string $usuario): array
    {
        $ambitos = ['REQUERIMIENTO', 'PLANIFICACION', 'ASISTENCIA', 'ALCOHOLEMIA', 'GENERAL'];
        $ambito = strtoupper(trim((string) ($body['ambito'] ?? 'PLANIFICACION')));
        if (!in_array($ambito, $ambitos, true)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Ámbito inválido']];
        }
        $desc = trim((string) ($body['descripcion'] ?? ''));
        if ($desc === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Descripción obligatoria']];
        }
        $fecha = trim((string) ($body['fecha'] ?? date('Y-m-d')));
        $personalId = !empty($body['personal_id']) ? (int) $body['personal_id'] : null;
        $empresaId = UserContext::isGrs()
            ? (!empty($body['empresa_id']) ? (int) $body['empresa_id'] : null)
            : UserContext::empresaId();
        if ($personalId) {
            $persona = $this->personal->find($personalId);
            if ($persona === null) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Personal no encontrado']];
            }
            UserContext::assertPersonalOwned($persona);
            $empresaId = $persona['empresa_id'] !== null ? (int) $persona['empresa_id'] : $empresaId;
        }
        $estado = strtoupper(trim((string) ($body['estado'] ?? 'ABIERTA')));
        if (!in_array($estado, ['ABIERTA', 'EN_PROCESO', 'CERRADA'], true)) {
            $estado = 'ABIERTA';
        }
        $id = $this->repo->crearAnomalia([
            'ambito' => $ambito,
            'personal_id' => $personalId,
            'empresa_id' => $empresaId,
            'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? '')), 0, 20),
            'granja_nombre' => substr(trim((string) ($body['granja_nombre'] ?? '')), 0, 120),
            'puesto_cod' => substr(trim((string) ($body['puesto_cod'] ?? '')), 0, 30),
            'fecha' => $fecha,
            'descripcion' => substr($desc, 0, 500),
            'accion' => substr(trim((string) ($body['accion'] ?? '')), 0, 500),
            'estado' => $estado,
            'usuario_registro' => substr($usuario, 0, 30),
        ]);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function actualizarAnomalia(int $id, array $body): array
    {
        $row = $this->repo->findAnomalia($id);
        if ($row === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        if (!UserContext::isGrs()) {
            $eid = UserContext::empresaId();
            $own = (int) ($row['empresa_id'] ?? 0);
            $pown = (int) ($row['personal_empresa_id'] ?? 0);
            if ($eid === null || ($own !== $eid && $pown !== $eid)) {
                return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Fuera de su empresa']];
            }
        }
        $estado = strtoupper(trim((string) ($body['estado'] ?? $row['estado'])));
        if (!in_array($estado, ['ABIERTA', 'EN_PROCESO', 'CERRADA'], true)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Estado inválido']];
        }
        $this->repo->actualizarAnomalia($id, [
            'ambito' => $row['ambito'],
            'descripcion' => substr(trim((string) ($body['descripcion'] ?? $row['descripcion'])), 0, 500),
            'accion' => substr(trim((string) ($body['accion'] ?? $row['accion'])), 0, 500),
            'estado' => $estado,
            'granja_cod' => $row['granja_cod'],
            'granja_nombre' => $row['granja_nombre'],
            'puesto_cod' => $row['puesto_cod'],
        ]);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, string> $q */
    public function reporteFaltante(array $q): array
    {
        $fecha = $q['fecha'] ?? date('Y-m-d');

        return ['ok' => true, 'data' => ['fecha' => $fecha, 'items' => $this->repo->faltantes($fecha, (string) ($q['empresa_id'] ?? ''))]];
    }

    /** @param array<string, string> $q */
    public function reporteHorarios(array $q): array
    {
        $desde = $q['desde'] ?? date('Y-m-d');
        $hasta = $q['hasta'] ?? $desde;
        $tol = $this->repo->toleranciaMinutos();
        $items = [];
        foreach ($this->repo->marcacionesConTurno($desde, $hasta, (string) ($q['empresa_id'] ?? '')) as $r) {
            $motivo = $this->incumple($r, $tol);
            if ($motivo === null) {
                continue;
            }
            $r['motivo'] = $motivo;
            $r['tolerancia_min'] = $tol;
            $items[] = $r;
        }

        return ['ok' => true, 'data' => ['desde' => $desde, 'hasta' => $hasta, 'tolerancia_min' => $tol, 'items' => $items]];
    }

    /** @param array<string, mixed> $r */
    private function incumple(array $r, int $tol): ?string
    {
        $inicio = (string) ($r['hora_inicio'] ?? '');
        $fin = (string) ($r['hora_fin'] ?? '');
        if ($inicio === '' || $fin === '') {
            return null;
        }
        $hora = substr((string) $r['marcado_en'], 11, 8);
        $tipo = (string) $r['tipo'];
        $turno = (string) ($r['turno_cod'] ?? 'DIA');
        $limiteEntrada = $this->sumarMinutos($inicio, $tol);
        $limiteSalida = $this->sumarMinutos($fin, -$tol);
        if ($tipo === 'ENTRADA') {
            if ($turno === 'NOCHE' && $hora < '12:00:00') {
                return 'Entrada después de medianoche';
            }
            if ($hora > $limiteEntrada && !($turno === 'NOCHE' && $hora < '12:00:00')) {
                return 'Entrada tarde';
            }

            return null;
        }
        if ($turno === 'NOCHE') {
            if ($hora >= '12:00:00' && $hora < $fin) {
                return 'Salida anticipada';
            }

            return null;
        }
        if ($hora < $limiteSalida) {
            return 'Salida anticipada';
        }

        return null;
    }

    private function sumarMinutos(string $hora, int $min): string
    {
        $base = strtotime('1970-01-01 ' . substr($hora, 0, 8));
        if ($base === false) {
            return $hora;
        }

        return date('H:i:s', $base + ($min * 60));
    }

    private function esAdminEmpresa(string $usuario, int $empresaId): bool
    {
        if ($empresaId < 1 || UserContext::isGrs()) {
            return UserContext::isGrs();
        }
        $emp = (new \Bio\Api\Repositories\EmpresaRepository())->find($empresaId);

        return $emp !== null && strtoupper(trim((string) $emp['usuario_cod'])) === strtoupper(trim($usuario));
    }
}
