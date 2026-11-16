<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\CapacitacionRepository;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Support\CapacitacionPolicy;
use Bio\Api\Support\UserContext;

final class PersonalCapacitacionService
{
    /** @var PersonalRepository */
    private $repo;
    /** @var CapacitacionRepository */
    private $caps;

    public function __construct()
    {
        $this->repo = new PersonalRepository();
        $this->caps = new CapacitacionRepository();
    }

    /** @param array<string, mixed> $body */
    public function agregar(int $personalId, array $body): array
    {
        CapacitacionPolicy::assertCanRegisterPersonalCap();
        $p = $this->repo->find($personalId);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        UserContext::assertPersonalOwned($p);
        $capId = (int) ($body['capacitacion_id'] ?? 0);
        $cap = $this->caps->find($capId);
        if ($cap === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Capacitacion invalida']];
        }
        $fechaRealizada = trim((string) ($body['fecha_realizada'] ?? ''));
        if ($fechaRealizada === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Fecha realizada obligatoria']];
        }
        $resultado = strtoupper(trim((string) ($body['resultado'] ?? 'APROBADO')));
        if (!in_array($resultado, ['APROBADO', 'DESAPROBADO', 'PENDIENTE'], true)) {
            $resultado = 'APROBADO';
        }
        $fechaEval = trim((string) ($body['fecha_evaluacion'] ?? ''));
        if ($fechaEval === '') {
            $fechaEval = $fechaRealizada;
        }
        $vence = $body['fecha_vence'] ?? null;
        if ($vence === '' || $vence === null) {
            $vence = $this->calcVence($fechaRealizada, (int) ($cap['vigencia_meses'] ?? 0));
        }

        $rowId = $this->repo->addCapacitacion($personalId, [
            'capacitacion_id' => $capId,
            'fecha_realizada' => $fechaRealizada,
            'fecha_vence' => $vence,
            'resultado' => $resultado,
            'fecha_evaluacion' => $fechaEval,
            'observacion' => trim((string) ($body['observacion'] ?? '')),
        ]);

        return ['ok' => true, 'data' => ['id' => $rowId]];
    }

    public function eliminar(int $personalId, int $rowId): array
    {
        CapacitacionPolicy::assertCanRegisterPersonalCap();
        $p = $this->repo->find($personalId);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        UserContext::assertPersonalOwned($p);
        if (!$this->repo->deleteCapacitacion($personalId, $rowId)) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }

        return ['ok' => true, 'data' => null];
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
