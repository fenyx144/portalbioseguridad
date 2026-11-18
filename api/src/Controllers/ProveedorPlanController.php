<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Services\ProveedorPlanService;

final class ProveedorPlanController extends BaseController
{
    public static function contexto(): void
    {
        self::auth();
        self::respondRol((new ProveedorPlanService())->contexto(self::query()));
    }

    public static function listar(): void
    {
        self::auth();
        self::respondRol((new ProveedorPlanService())->listar(self::query()));
    }

    public static function guardar(): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->guardar(self::body(), $user['codigo']));
    }

    public static function actualizar(array $p): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->actualizar((int) ($p['id'] ?? 0), self::body(), $user['codigo']));
    }

    public static function eliminar(array $p): void
    {
        self::auth();
        $q = self::query();
        $body = self::body();
        if (isset($body['empresa_id']) && !isset($q['empresa_id'])) {
            $q['empresa_id'] = (string) $body['empresa_id'];
        }
        self::respondRol((new ProveedorPlanService())->eliminar((int) ($p['id'] ?? 0), $q));
    }

    public static function guardarPersonal(): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->guardarPersonal(self::body(), $user['codigo']));
    }

    public static function bajaPersonal(): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->bajaPersonal(self::body(), $user['codigo']));
    }

    public static function guardarVehiculo(): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->guardarVehiculo(self::body(), $user['codigo']));
    }

    public static function bajaVehiculo(): void
    {
        $user = self::auth();
        self::respondRol((new ProveedorPlanService())->bajaVehiculo(self::body(), $user['codigo']));
    }

    /** @param array<string, mixed> $result */
    private static function respondRol(array $result): void
    {
        $code = (string) ($result['error']['code'] ?? '');
        $status = $code === 'forbidden' ? 403 : ($code === 'not_found' ? 404 : 422);
        self::respond($result, $status);
    }
}
