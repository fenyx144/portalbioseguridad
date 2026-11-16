<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Repositories\PuntoRepository;

final class PuntoService
{
    /** @var PuntoRepository */
    private $repo;
    /** @var GranjaRepository */
    private $granjas;

    public function __construct()
    {
        $this->repo = new PuntoRepository();
        $this->granjas = new GranjaRepository();
    }

    public function detalle(int $id): array
    {
        $p = $this->repo->find($id);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }

        return [
            'ok' => true,
            'data' => [
                'punto' => $p,
                'turnos' => $this->repo->turnos($id),
                'cargos_requeridos' => $this->repo->cargosRequeridos($id),
                'capacitacion_ids' => $this->repo->capacitacionIds($id),
            ],
        ];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body): array
    {
        $err = $this->validate($body, null);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $id = 0;
        for ($intento = 0; $intento < 5; $intento++) {
            try {
                $id = $this->repo->crear([
                    'granja_cod' => $body['granja_cod'],
                    'codigo' => $this->repo->siguienteCodigo((string) $body['granja_cod'], (string) $body['tipo_cod']),
                    'nombre' => $body['nombre'],
                    'tipo_cod' => $body['tipo_cod'],
                    'ubicacion' => $body['ubicacion'] ?? '',
                    'estado' => $body['estado'] ?? 'ACTIVO',
                ]);
                break;
            } catch (\PDOException $e) {
                if ((string) $e->getCode() !== '23000' || $intento === 4) {
                    throw $e;
                }
            }
        }
        $this->persistTurnosYCargos($id, $body);
        $this->repo->syncCapacitaciones($id, array_map('intval', $body['capacitacion_ids'] ?? []));

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body): array
    {
        $p = $this->repo->find($id);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        $body['granja_cod'] = $p['granja_cod'];
        $err = $this->validate($body, $id);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $this->repo->actualizar($id, [
            'codigo' => $p['codigo'],
            'nombre' => $body['nombre'],
            'tipo_cod' => $body['tipo_cod'],
            'ubicacion' => $body['ubicacion'] ?? '',
            'estado' => $body['estado'] ?? $p['estado'],
        ]);
        if (isset($body['turnos']) || isset($body['cargos_requeridos'])) {
            $this->persistTurnosYCargos($id, $body);
        }
        if (isset($body['capacitacion_ids'])) {
            $this->repo->syncCapacitaciones($id, array_map('intval', $body['capacitacion_ids']));
        }

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /**
     * Cupos por turno (personal_requerido) obligatorios; cargos por punto opcionales.
     * Si se detallan cargos, el cupo del turno es el mayor entre lo indicado y la suma de cargos.
     *
     * @param array<string, mixed> $body
     */
    private function persistTurnosYCargos(int $id, array $body): void
    {
        $cargos = is_array($body['cargos_requeridos'] ?? null) ? $body['cargos_requeridos'] : [];
        $turnosBody = is_array($body['turnos'] ?? null) ? $body['turnos'] : [];
        $sumCargos = [];
        foreach ($cargos as $c) {
            if (trim((string) ($c['cargo'] ?? '')) === '') {
                continue;
            }
            $tc = (string) ($c['turno_cod'] ?? '');
            $sumCargos[$tc] = ($sumCargos[$tc] ?? 0) + max(1, min(99, (int) ($c['cantidad'] ?? 1)));
        }
        $previos = [];
        foreach ($this->repo->turnos($id) as $pt) {
            $previos[(string) $pt['turno_cod']] = $pt;
        }
        $turnos = [];
        $vistos = [];
        foreach ($turnosBody as $t) {
            $tc = (string) ($t['turno_cod'] ?? '');
            if ($tc === '' || isset($vistos[$tc])) {
                continue;
            }
            $vistos[$tc] = true;
            $req = max(0, min(99, (int) ($t['personal_requerido'] ?? 0)));
            $req = max($req, (int) ($sumCargos[$tc] ?? 0));
            $turnos[] = [
                'turno_cod' => $tc,
                'personal_requerido' => $req,
                'hora_inicio_override' => array_key_exists('hora_inicio_override', $t) ? $t['hora_inicio_override'] : ($previos[$tc]['hora_inicio_override'] ?? null),
                'hora_fin_override' => array_key_exists('hora_fin_override', $t) ? $t['hora_fin_override'] : ($previos[$tc]['hora_fin_override'] ?? null),
            ];
        }
        foreach ($sumCargos as $tc => $n) {
            if (!isset($vistos[$tc]) && in_array($tc, ['DIA', 'NOCHE'], true)) {
                $turnos[] = ['turno_cod' => $tc, 'personal_requerido' => $n];
            }
        }
        $this->repo->syncTurnos($id, $turnos);
        $this->repo->syncSoloCargos($id, $cargos);
    }

    public function desactivar(int $id): array
    {
        if ($this->repo->find($id) === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        if ($this->repo->countAsignaciones($id) > 0 || $this->repo->countAsistenciasPorPunto($id) > 0) {
            $this->repo->desactivar($id);

            return ['ok' => true, 'data' => ['desactivado' => true]];
        }
        $this->repo->desactivar($id);

        return ['ok' => true, 'data' => ['desactivado' => true]];
    }

    public function copiarGranja(string $destinoCod, string $origenCod): array
    {
        if (!$this->granjas->existe($destinoCod) || !$this->granjas->existe($origenCod)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Granja invalida']];
        }
        if ($destinoCod === $origenCod) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Origen y destino iguales']];
        }

        try {
            $origenPuntos = $this->repo->listar($origenCod, 'ACTIVO', null);
            $created = 0;
            foreach ($origenPuntos as $op) {
                $oid = (int) $op['id'];
                $newId = $this->repo->crear([
                    'granja_cod' => $destinoCod,
                    'codigo' => $op['codigo'],
                    'nombre' => $op['nombre'],
                    'tipo_cod' => $op['tipo_cod'],
                    'ubicacion' => $op['ubicacion'],
                    'estado' => 'ACTIVO',
                ]);
                $this->repo->syncCapacitaciones($newId, $this->repo->capacitacionIds($oid));
                $this->repo->syncTurnos($newId, $this->repo->turnos($oid));
                $this->repo->syncSoloCargos($newId, $this->repo->cargosRequeridos($oid));
                $created++;
            }

            return ['ok' => true, 'data' => ['copiados' => $created]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['code' => 'server', 'message' => 'No se pudo copiar']];
        }
    }

    /** @param array<string, mixed> $body */
    private function validate(array $body, ?int $excludeId): ?array
    {
        foreach (['granja_cod', 'nombre', 'tipo_cod'] as $k) {
            if (empty($body[$k])) {
                return ['code' => 'validation', 'message' => "Campo {$k} obligatorio"];
            }
        }
        if (!$this->granjas->existe((string) $body['granja_cod'])) {
            return ['code' => 'validation', 'message' => 'Granja no existe'];
        }
        if ($this->repo->existeNombre((string) $body['granja_cod'], (string) $body['nombre'], $excludeId)) {
            return ['code' => 'duplicate', 'message' => 'Ya existe un punto con ese nombre en la granja'];
        }

        return null;
    }
}
