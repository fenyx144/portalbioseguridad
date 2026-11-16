<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Services\AlmacenService;

final class AlmacenController extends BaseController
{
    public static function insumos(): void
    {
        self::auth();
        self::respond((new AlmacenService())->insumos((self::query()['todos'] ?? '') === '1'));
    }

    public static function crearInsumo(): void
    {
        self::auth();
        self::respond((new AlmacenService())->guardarInsumo(null, self::body()), 422);
    }

    public static function actualizarInsumo(array $p): void
    {
        self::auth();
        self::respond((new AlmacenService())->guardarInsumo((int) ($p['id'] ?? 0), self::body()), 422);
    }

    public static function guardarSaldos(): void
    {
        self::auth();
        self::respond((new AlmacenService())->guardarSaldos(self::body()), 422);
    }

    public static function entregas(): void
    {
        self::auth();
        self::respond((new AlmacenService())->entregas(self::query()));
    }

    public static function entrega(array $p): void
    {
        self::auth();
        self::respond((new AlmacenService())->entrega((int) ($p['id'] ?? 0)), 404);
    }

    public static function crearEntrega(): void
    {
        $user = self::auth();
        self::respond((new AlmacenService())->crearEntrega(self::body(), $user['codigo']), 422);
    }

    public static function recepcion(array $p): void
    {
        $user = self::auth();
        self::respond((new AlmacenService())->confirmarRecepcion((int) ($p['id'] ?? 0), self::body(), $user['codigo']), 422);
    }

    public static function anular(array $p): void
    {
        self::auth();
        self::respond((new AlmacenService())->anularEntrega((int) ($p['id'] ?? 0)), 422);
    }

    public static function consumos(): void
    {
        self::auth();
        self::respond((new AlmacenService())->consumos(self::query()));
    }

    public static function crearConsumo(): void
    {
        $user = self::auth();
        self::respond((new AlmacenService())->crearConsumo(self::body(), $user['codigo']), 422);
    }

    public static function eliminarConsumo(array $p): void
    {
        self::auth();
        self::respond((new AlmacenService())->eliminarConsumo((int) ($p['id'] ?? 0)), 422);
    }

    public static function stock(): void
    {
        self::auth();
        self::respond((new AlmacenService())->stock(self::query()));
    }

    public static function campoContext(): void
    {
        self::auth();
        self::respond((new AlmacenService())->campoContext(self::query()));
    }

    public static function relevos(): void
    {
        self::auth();
        self::respond((new AlmacenService())->relevos(self::query()));
    }

    public static function relevo(array $p): void
    {
        self::auth();
        self::respond((new AlmacenService())->relevo((int) ($p['id'] ?? 0)), 404);
    }

    public static function crearRelevo(): void
    {
        $user = self::auth();
        self::respond((new AlmacenService())->crearRelevo(self::body(), $user['codigo']), 422);
    }
}
