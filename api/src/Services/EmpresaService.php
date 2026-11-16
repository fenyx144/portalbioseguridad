<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Support\SanidadEmpresaBridge;

final class EmpresaService
{
    /** @var EmpresaRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new EmpresaRepository();
    }

  /** @return array{ok: bool, data?: mixed, error?: array<string, mixed>} */
    public function listar(): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->listar()]];
    }

    /** @param array<string, mixed> $body */
    public function crear(array $body, string $codigoSesion): array
    {
        $codigo = strtoupper(trim((string) ($body['codigo'] ?? '')));
        $ruc = preg_replace('/\D/', '', (string) ($body['ruc'] ?? '')) ?? '';
        $razon = trim((string) ($body['razon_social'] ?? ''));

        if (!preg_match('/^[A-Z0-9]{6}$/', $codigo)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'El usuario debe tener exactamente 6 caracteres (A-Z, 0-9), igual que en Sanidad']];
        }
        if (strlen($ruc) !== 11) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'RUC de 11 dígitos']];
        }
        if ($razon === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Razón social obligatoria']];
        }
        if ($this->repo->findByCodigo($codigo) !== null) {
            return ['ok' => false, 'error' => ['code' => 'duplicate', 'message' => 'Ya existe esa empresa en Bioseguridad']];
        }

        $sip = SanidadEmpresaBridge::crearUsuarioEmpresa($codigo, $ruc, $razon, $codigoSesion);
        if (!($sip['ok'] ?? false)) {
            return ['ok' => false, 'error' => ['code' => 'sanidad', 'message' => $sip['message'] ?? 'Error SIP']];
        }

        $id = $this->repo->crear([
            'codigo' => $codigo,
            'ruc' => $ruc,
            'razon_social' => $razon,
            'usuario_cod' => $codigo,
        ]);

        return [
            'ok' => true,
            'data' => [
                'id' => $id,
                'codigo' => $codigo,
                'ruc' => $ruc,
                'razon_social' => $razon,
                'usuario_cod' => $codigo,
                'clave_inicial' => $ruc,
            ],
        ];
    }

    public function patchEstado(int $id, array $body): array
    {
        $estado = (string) ($body['estado'] ?? '');
        if (!$this->repo->patchEstado($id, $estado)) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Empresa no encontrada']];
        }

        return ['ok' => true, 'data' => ['id' => $id, 'estado' => $estado]];
    }
}
