<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Services\ArticuloIngresoService;
use Bio\Api\Support\MobileAppAuth;

final class ArticuloIngresoController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        $solo = (self::query()['todos'] ?? '') !== '1';
        self::respond((new ArticuloIngresoService())->listar($solo));
    }

    /** Bearer móvil (sin sesión web). */
    public static function movil(): void
    {
        MobileAppAuth::ensureSession();
        self::respond((new ArticuloIngresoService())->listarMovil());
    }

    public static function store(): void
    {
        self::auth();
        self::respond((new ArticuloIngresoService())->crear(self::body()), 422);
    }

    public static function update(array $params): void
    {
        self::auth();
        self::respond((new ArticuloIngresoService())->actualizar((int) ($params['id'] ?? 0), self::body()), 422);
    }
}