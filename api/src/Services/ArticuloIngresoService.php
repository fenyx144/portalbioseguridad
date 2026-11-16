<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\ArticuloIngresoRepository;
use Bio\Api\Support\UserContext;

final class ArticuloIngresoService
{
    /** @var ArticuloIngresoRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new ArticuloIngresoRepository();
    }

    /** @return array{ok: bool, data?: mixed, error?: array<string, mixed>} */
    public function listar(bool $soloActivos = true): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->listar($soloActivos)]];
    }

    /** Catálogo móvil: codigo, descripcion, permite (bool|null), regla, nota, requiere_* */
    public function listarMovil(): array
    {
        $items = [];
        foreach ($this->repo->listar(true) as $row) {
            $regla = (string) ($row['regla'] ?? 'CRITERIO');
            $permite = null;
            if ($regla === 'PERMITIDO') {
                $permite = true;
            } elseif ($regla === 'PROHIBIDO') {
                $permite = false;
            }
            $nota = (string) ($row['nota'] ?? '');
            $req = self::requisitosMovil($regla, $nota);
            $items[] = [
                'codigo' => (string) $row['codigo'],
                'descripcion' => (string) $row['nombre'],
                'regla' => $regla,
                'nota' => $nota,
                'permite' => $permite,
                'requiere_foto' => $req['requiere_foto'],
                'requiere_desinfeccion' => $req['requiere_desinfeccion'],
            ];
        }

        return ['ok' => true, 'data' => ['items' => $items]];
    }

    /**
     * @return array{requiere_foto:bool,requiere_desinfeccion:bool}
     */
    private static function requisitosMovil(string $regla, string $nota): array
    {
        $regla = strtoupper(trim($regla));
        if ($regla === 'PROHIBIDO' || $regla === 'CRITERIO') {
            return ['requiere_foto' => false, 'requiere_desinfeccion' => false];
        }
        $notaL = mb_strtolower(trim($nota), 'UTF-8');

        return [
            'requiere_foto' => $notaL !== '' && str_contains($notaL, 'foto'),
            'requiere_desinfeccion' => $notaL !== '' && str_contains($notaL, 'desinfecc'),
        ];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body): array
    {
        UserContext::requireGrs();
        $err = $this->validate($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $id = $this->repo->crear($this->normalize($body));

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body): array
    {
        UserContext::requireGrs();
        if ($this->repo->find($id) === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']];
        }
        $err = $this->validate($body);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $this->repo->actualizar($id, $this->normalize($body));

        return ['ok' => true, 'data' => null];
    }

    /** @param array<string, mixed> $body */
    private function validate(array $body): ?array
    {
        if (trim((string) ($body['codigo'] ?? '')) === '' || trim((string) ($body['nombre'] ?? '')) === '') {
            return ['code' => 'validation', 'message' => 'Código y nombre son obligatorios'];
        }
        $regla = (string) ($body['regla'] ?? 'CRITERIO');
        if (!in_array($regla, ['PERMITIDO', 'PROHIBIDO', 'CRITERIO'], true)) {
            return ['code' => 'validation', 'message' => 'Regla inválida'];
        }
        $estado = (string) ($body['estado'] ?? 'ACTIVO');
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            return ['code' => 'validation', 'message' => 'Estado inválido'];
        }

        return null;
    }

    /** @param array<string, mixed> $body
     * @return array{codigo:string,nombre:string,regla:string,nota:string,orden:int,estado:string}
     */
    private function normalize(array $body): array
    {
        return [
            'codigo' => substr(trim((string) $body['codigo']), 0, 20),
            'nombre' => substr(trim((string) $body['nombre']), 0, 120),
            'regla' => (string) ($body['regla'] ?? 'CRITERIO'),
            'nota' => substr(trim((string) ($body['nota'] ?? '')), 0, 255),
            'orden' => max(0, min(9999, (int) ($body['orden'] ?? 0))),
            'estado' => (string) ($body['estado'] ?? 'ACTIVO'),
        ];
    }
}