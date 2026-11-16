<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\CapacitacionRegistroRepository;
use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Support\UserContext;

final class CapacitacionRegistroService
{
    /** @var CapacitacionRegistroRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new CapacitacionRegistroRepository();
    }

    /** @param array<string, string> $query */
    public function resumenEmpresa(array $query): array
    {
        $empresaId = (int) ($query['empresa_id'] ?? 0);
        if (!UserContext::isGrs()) {
            $eid = UserContext::empresaId();
            if ($eid === null) {
                return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Empresa no identificada']];
            }
            $empresaId = $eid;
        } elseif ($empresaId <= 0) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'empresa_id requerido']];
        }

        $empRepo = new EmpresaRepository();
        if ($empRepo->find($empresaId) === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Empresa no encontrada']];
        }

        $mes = preg_match('/^\d{4}-\d{2}$/', (string) ($query['mes'] ?? '')) ? $query['mes'] : date('Y-m');
        $data = $this->repo->resumenEmpresa($empresaId, $mes);

        return [
            'ok' => true,
            'data' => array_merge($data, [
                'empresa_id' => $empresaId,
                'mes' => $mes,
            ]),
        ];
    }
}
