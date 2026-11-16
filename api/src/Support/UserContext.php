<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Repositories\EmpresaRepository;

final class UserContext
{
    public static function actor(): string
    {
        return (string) ($_SESSION['bio_actor'] ?? 'grs');
    }

    public static function empresaId(): ?int
    {
        if (!isset($_SESSION['bio_empresa_id'])) {
            return null;
        }

        return (int) $_SESSION['bio_empresa_id'];
    }

    public static function isGrs(): bool
    {
        return self::actor() !== 'empresa';
    }

    public static function requireGrs(): void
    {
        if (!self::isGrs()) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'Solo personal GRS puede realizar esta acción',
            ], 403);
        }
    }

    /** @return array<string, mixed> */
    public static function enrichUser(array $base): array
    {
        $emp = null;
        try {
            $repo = new EmpresaRepository();
            $emp = $repo->findByUsuarioCod($base['codigo']);
        } catch (\Throwable $e) {
            // Tabla bio_empresa ausente o error transitorio: sesión GRS por defecto
            $emp = null;
        }
        if ($emp !== null) {
            return array_merge($base, [
                'actor' => 'empresa',
                'empresa_id' => (int) $emp['id'],
                'empresa_codigo' => (string) $emp['codigo'],
                'empresa_nombre' => (string) $emp['razon_social'],
            ]);
        }

        return array_merge($base, [
            'actor' => 'grs',
            'empresa_id' => null,
            'empresa_codigo' => null,
            'empresa_nombre' => null,
        ]);
    }

    public static function bindSession(array $profile): void
    {
        $_SESSION['bio_actor'] = (string) ($profile['actor'] ?? 'grs');
        if (!empty($profile['empresa_id'])) {
            $_SESSION['bio_empresa_id'] = (int) $profile['empresa_id'];
        } else {
            unset($_SESSION['bio_empresa_id']);
        }
    }

    /** @param array<string, string> $filters */
    public static function applyPersonalScope(array &$filters): void
    {
        if (self::isGrs()) {
            return;
        }
        $eid = self::empresaId();
        if ($eid !== null) {
            $filters['empresa_id'] = (string) $eid;
        }
    }

    public static function assertPersonalOwned(?array $row): void
    {
        if ($row === null || self::isGrs()) {
            return;
        }
        $eid = self::empresaId();
        $rowEid = isset($row['empresa_id']) ? (int) $row['empresa_id'] : 0;
        if ($eid === null || $rowEid !== $eid) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'Personal fuera de su empresa',
            ], 403);
        }
    }

    public static function assertAsignacionOwned(?array $asig): void
    {
        if ($asig === null || self::isGrs()) {
            return;
        }
        $eid = self::empresaId();
        if ($eid === null) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'Empresa no identificada',
            ], 403);
        }
        $rowEid = isset($asig['empresa_id']) ? (int) $asig['empresa_id'] : 0;
        if ($rowEid !== $eid) {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'No puede modificar personal de otro contratista',
            ], 403);
        }
        if (($asig['origen'] ?? 'EMPRESA') === 'GRS' || ($asig['personal_empresa'] ?? '') === 'GRS') {
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => 'No puede modificar cupos definidos por GRS',
            ], 403);
        }
    }

    /** @param array<string, mixed> $row */
    public static function asignacionEditable(array $row): bool
    {
        if (self::isGrs()) {
            return true;
        }
        $eid = self::empresaId();
        if ($eid === null) {
            return false;
        }
        if (($row['personal_empresa'] ?? ($row['empresa'] ?? '')) === 'GRS') {
            return false;
        }

        return (int) ($row['empresa_id'] ?? 0) === $eid;
    }

    /**
     * @param list<array<string, mixed>> $asignaciones
     * @return list<array<string, mixed>>
     */
    public static function asignacionesVisibles(array $asignaciones): array
    {
        if (self::isGrs()) {
            return $asignaciones;
        }
        $eid = self::empresaId();
        if ($eid === null) {
            return [];
        }

        return array_values(array_filter($asignaciones, static function (array $a) use ($eid): bool {
            return (int) ($a['empresa_id'] ?? 0) === $eid;
        }));
    }
}
