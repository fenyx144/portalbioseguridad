<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Http\JsonResponse;

final class CapacitacionPolicy
{
    public static function catalogGestor(): string
    {
        return 'grs';
    }

    public static function registroGestor(): string
    {
        return 'empresa';
    }

    public static function canManageCatalog(): bool
    {
        return UserContext::isGrs();
    }

    public static function assertCanManageCatalog(): void
    {
        if (!self::canManageCatalog()) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'El catálogo de cursos lo administra GRS',
            ], 403);
        }
    }

    public static function canRegisterPersonalCap(): bool
    {
        return !UserContext::isGrs();
    }

    public static function assertCanRegisterPersonalCap(): void
    {
        if (!self::canRegisterPersonalCap()) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'Solo la empresa contratista registra capacitaciones de su personal',
            ], 403);
        }
    }
}
