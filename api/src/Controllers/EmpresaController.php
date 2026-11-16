<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Services\EmpresaService;
use Bio\Api\Services\RucService;
use Bio\Api\Support\UserContext;

final class EmpresaController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        UserContext::requireGrs();
        $svc = new EmpresaService();
        self::respond($svc->listar());
    }

    public static function consultarRuc(array $params): void
    {
        self::auth();
        UserContext::requireGrs();
        $r = (new RucService())->consultar((string) ($params['ruc'] ?? ''));
        $code = $r['error']['code'] ?? '';
        self::respond($r, $code === 'validation' ? 422 : ($code === 'not_found' ? 404 : ($code === 'config' ? 503 : 502)));
    }

    public static function store(): void
    {
        $user = self::auth();
        UserContext::requireGrs();
        $svc = new EmpresaService();
        self::respond($svc->crear(self::body(), $user['codigo']));
    }

    public static function patch(array $params): void
    {
        self::auth();
        UserContext::requireGrs();
        $id = (int) ($params['id'] ?? 0);
        $svc = new EmpresaService();
        self::respond($svc->patchEstado($id, self::body()));
    }
}
