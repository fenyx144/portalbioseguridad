<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Support\UserContext;

final class PersonalService
{
    /** @var PersonalRepository */
    private $repo;
    /** @var GranjaRepository */
    private $granjas;

    public function __construct()
    {
        $this->repo = new PersonalRepository();
        $this->granjas = new GranjaRepository();
    }

    /** @return array{ok: bool, data?: mixed, error?: array<string, mixed>} */
    public function listar(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(500, max(10, (int) ($query['per_page'] ?? 20)));
        $filters = [
            'q' => $query['q'] ?? '',
            'empresa' => $query['empresa'] ?? '',
            'empresa_id' => $query['empresa_id'] ?? '',
            'estado' => $query['estado'] ?? '',
            'cargo' => $query['cargo'] ?? '',
            'granja_base' => $query['granja_base'] ?? '',
        ];
        $result = $this->repo->listar($filters, $page, $perPage);

        return [
            'ok' => true,
            'data' => [
                'items' => $result['items'],
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body): array
    {
        $this->applyActorDefaults($body);
        $err = $this->validatePayload($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        if ($this->repo->findByDni((string) $body['dni']) !== null) {
            return ['ok' => false, 'error' => ['code' => 'duplicate', 'message' => 'DNI ya registrado']];
        }
        $id = $this->repo->crear($this->normalize($body));

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body): array
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        UserContext::assertPersonalOwned($existing);
        $this->applyActorDefaults($body);
        $err = $this->validatePayload($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        if ($this->repo->findByDni((string) $body['dni'], $id) !== null) {
            return ['ok' => false, 'error' => ['code' => 'duplicate', 'message' => 'DNI ya registrado']];
        }
        $data = $this->normalize($body);
        $data['foto_ruta'] = $body['foto_ruta'] ?? ($existing['foto_ruta'] ?? '');
        $this->repo->actualizar($id, $data);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    public function ficha(int $id): array
    {
        $p = $this->repo->find($id);
        if ($p === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        UserContext::assertPersonalOwned($p);
        $fc = new \DateTimeImmutable($p['fecha_contratacion']);
        $antiguedadDias = (int) $fc->diff(new \DateTimeImmutable('today'))->days;

        return [
            'ok' => true,
            'data' => [
                'personal' => $p,
                'antiguedad_dias' => $antiguedadDias,
                'capacitaciones' => $this->repo->capacitaciones($id),
                'asignaciones' => $this->repo->historialAsignaciones($id),
            ],
        ];
    }

    /** @param array<string, mixed> $body */
    private function validatePayload(array $body): ?array
    {
        $required = ['dni', 'nombres', 'apellidos', 'cargo', 'empresa', 'fecha_contratacion', 'estado'];
        foreach ($required as $k) {
            if (!isset($body[$k]) || trim((string) $body[$k]) === '') {
                return ['code' => 'validation', 'message' => "Campo {$k} obligatorio"];
            }
        }
        if (!in_array($body['empresa'], ['GRS', 'TERCERO'], true)) {
            return ['code' => 'validation', 'message' => 'Empresa invalida'];
        }
        if (!in_array($body['estado'], ['ACTIVO', 'CESADO'], true)) {
            return ['code' => 'validation', 'message' => 'Estado invalido'];
        }
        $gb = trim((string) ($body['granja_base_cod'] ?? ''));
        if ($gb !== '' && !$this->granjas->existe($gb)) {
            return ['code' => 'validation', 'message' => 'Granja base no existe'];
        }
        if (UserContext::isGrs()) {
            if (($body['empresa'] ?? '') === 'TERCERO') {
                $eid = (int) ($body['empresa_id'] ?? 0);
                if ($eid < 1) {
                    return ['code' => 'validation', 'message' => 'Seleccione la empresa contratista'];
                }
                $emp = (new EmpresaRepository())->find($eid);
                if ($emp === null || ($emp['estado'] ?? '') !== 'ACTIVO') {
                    return ['code' => 'validation', 'message' => 'Empresa contratista no válida'];
                }
            }

            return null;
        }
        if ($body['empresa'] === 'GRS') {
            return null;
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private function normalize(array $body): array
    {
        return [
            'dni' => trim((string) $body['dni']),
            'nombres' => trim((string) $body['nombres']),
            'apellidos' => trim((string) $body['apellidos']),
            'cargo' => trim((string) $body['cargo']),
            'empresa' => $body['empresa'],
            'empresa_id' => ($body['empresa'] ?? '') === 'GRS' ? null : (isset($body['empresa_id']) ? (int) $body['empresa_id'] : null),
            'fecha_contratacion' => $body['fecha_contratacion'],
            'estado' => $body['estado'],
            'granja_base_cod' => trim((string) ($body['granja_base_cod'] ?? '')),
            'foto_ruta' => trim((string) ($body['foto_ruta'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $body */
    private function applyActorDefaults(array &$body): void
    {
        if (!UserContext::isGrs()) {
            $body['empresa'] = 'TERCERO';
            $eid = UserContext::empresaId();
            if ($eid !== null) {
                $body['empresa_id'] = $eid;
            }

            return;
        }
    }
}
