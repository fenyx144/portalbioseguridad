<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Repositories\PuntoRepository;
use Bio\Api\Services\PuntoService;
use Bio\Api\Support\UserContext;

final class PuntoController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        $q = self::query();
        $repo = new PuntoRepository();
        $items = $repo->attachRequeridosTurno($repo->listar(
            $q['granja_cod'] ?? null,
            $q['estado'] ?? null,
            $q['tipo'] ?? null
        ));
        self::respond(['ok' => true, 'data' => ['items' => $items]]);
    }

    public static function tipos(): void
    {
        self::auth();
        self::respond(['ok' => true, 'data' => ['items' => (new PuntoRepository())->tipos()]]);
    }

    public static function show(array $params): void
    {
        self::auth();
        self::respond((new PuntoService())->detalle((int) ($params['id'] ?? 0)));
    }

    public static function store(): void
    {
        self::auth();
        UserContext::requireGrs();
        self::respond((new PuntoService())->crear(self::body()), 422);
    }

    public static function update(array $params): void
    {
        self::auth();
        UserContext::requireGrs();
        self::respond((new PuntoService())->actualizar((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function desactivar(array $params): void
    {
        self::auth();
        UserContext::requireGrs();
        self::respond((new PuntoService())->desactivar((int) ($params['id'] ?? 0)));
    }

    public static function copiar(array $params): void
    {
        self::auth();
        UserContext::requireGrs();
        $body = self::body();
        self::respond((new PuntoService())->copiarGranja(
            (string) ($params['cod'] ?? ''),
            (string) ($body['granja_origen_cod'] ?? '')
        ), 422);
    }
}
