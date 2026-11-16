<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\CapacitacionRepository;
use Bio\Api\Support\CapacitacionPolicy;

final class CapacitacionService
{
    /** @var CapacitacionRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new CapacitacionRepository();
    }

    /** @return array{ok: bool, data?: mixed, error?: array<string, mixed>} */
    public function listar(bool $soloActivos = true): array
    {
        return [
            'ok' => true,
            'data' => [
                'items' => $this->repo->listar($soloActivos),
                'permisos' => [
                    'catalogo_editar' => CapacitacionPolicy::canManageCatalog(),
                    'catalogo_gestor' => CapacitacionPolicy::catalogGestor(),
                    'registro_gestor' => CapacitacionPolicy::registroGestor(),
                    'registro_editar' => CapacitacionPolicy::canRegisterPersonalCap(),
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body): array
    {
        CapacitacionPolicy::assertCanManageCatalog();
        $err = $this->validateCatalog($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $id = $this->repo->crear($this->normalizeCatalog($body));

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body): array
    {
        CapacitacionPolicy::assertCanManageCatalog();
        if ($this->repo->find($id) === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        $err = $this->validateCatalog($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $this->repo->actualizar($id, $this->normalizeCatalog($body));

        return ['ok' => true, 'data' => null];
    }

    /** @param array<string, mixed> $body */
    private function validateCatalog(array $body): ?array
    {
        foreach (['codigo', 'nombre'] as $k) {
            if (trim((string) ($body[$k] ?? '')) === '') {
                return ['code' => 'validation', 'message' => "Campo {$k} obligatorio"];
            }
        }
        $estado = (string) ($body['estado'] ?? 'ACTIVO');
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            return ['code' => 'validation', 'message' => 'Estado invalido'];
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private function normalizeCatalog(array $body): array
    {
        $tipo = (string) ($body['tipo'] ?? 'OTRO');
        if (!in_array($tipo, ['INDUCCION', 'FORMACION', 'EVALUACION', 'OTRO'], true)) {
            $tipo = 'OTRO';
        }

        return [
            'codigo' => strtoupper(trim((string) $body['codigo'])),
            'nombre' => trim((string) $body['nombre']),
            'vigencia_meses' => max(0, (int) ($body['vigencia_meses'] ?? 0)),
            'estado' => $body['estado'] ?? 'ACTIVO',
            'tipo' => $tipo,
        ];
    }
}
