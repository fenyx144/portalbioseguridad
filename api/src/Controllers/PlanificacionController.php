<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Services\PlanificacionService;

final class PlanificacionController extends BaseController
{
    /** @return list<string> */
    private static function granjasFromQuery(): array
    {
        $q = self::query();
        if (($q['granja_cod'] ?? '') === '') {
            return array_column((new GranjaRepository())->listar(), 'codigo');
        }
        return array_values(array_filter(array_map('trim', explode(',', $q['granja_cod']))));
    }

    public static function semana(): void
    {
        self::auth();
        $q = self::query();
        $fecha = $q['fecha_inicio'] ?? date('Y-m-d', strtotime('monday this week'));
        $tipo = $q['tipo'] ?? null;
        $solo = ($q['solo_incompletos'] ?? '') === '1';
        $empresaId = isset($q['empresa_id']) && $q['empresa_id'] !== '' ? (int) $q['empresa_id'] : null;
        self::respond((new PlanificacionService())->semana($fecha, self::granjasFromQuery(), $tipo, $solo, $empresaId));
    }

    public static function dia(): void
    {
        self::auth();
        $q = self::query();
        $fecha = $q['fecha'] ?? date('Y-m-d');
        self::respond((new PlanificacionService())->dia($fecha, self::granjasFromQuery()));
    }

    public static function guardarAsignacion(): void
    {
        $user = self::auth();
        $body = self::body();
        $force = !empty($body['force_warnings']);
        self::respond((new PlanificacionService())->guardarAsignacion($body, $user['codigo'], $force), 422);
    }

    public static function guardarAsignacionesLote(): void
    {
        $user = self::auth();
        $body = self::body();
        $force = !empty($body['force_warnings']);
        self::respond((new PlanificacionService())->guardarAsignacionesLote($body, $user['codigo'], $force), 422);
    }

    public static function eliminarAsignacion(array $params): void
    {
        self::auth();
        self::respond((new PlanificacionService())->eliminarAsignacion((int) ($params['id'] ?? 0)));
    }

    public static function copiarSemana(): void
    {
        $user = self::auth();
        $body = self::body();
        $origen = (string) ($body['semana_origen'] ?? '');
        $destino = (string) ($body['semana_destino'] ?? '');
        $granjaRaw = trim((string) ($body['granja_cod'] ?? ''));
        $granjas = $granjaRaw === ''
            ? array_column((new GranjaRepository())->listar(), 'codigo')
            : array_values(array_filter(array_map('trim', explode(',', $granjaRaw))));
        self::respond((new PlanificacionService())->copiarSemana($granjas, $origen, $destino, $user['codigo']), 422);
    }

    public static function asignarRapido(): void
    {
        $user = self::auth();
        $body = self::body();
        $force = !empty($body['force_warnings']);
        self::respond((new PlanificacionService())->asignarRapido($body, $user['codigo'], $force), 422);
    }

    public static function personalDisponible(): void
    {
        self::auth();
        $q = self::query();
        self::respond((new PlanificacionService())->personalDisponible(
            $q['fecha'] ?? date('Y-m-d'),
            $q['turno_cod'] ?? 'DIA',
            (int) ($q['punto_id'] ?? 0),
            $q
        ));
    }

    public static function reemplazo(): void
    {
        $user = self::auth();
        self::respond((new PlanificacionService())->reemplazo(self::body(), $user['codigo']), 422);
    }

    public static function cobertura(): void
    {
        self::auth();
        $q = self::query();
        $fecha = $q['fecha_inicio'] ?? date('Y-m-d', strtotime('monday this week'));
        $svc = new PlanificacionService();
        $res = $svc->semana($fecha, self::granjasFromQuery(), null, false);
        if (!$res['ok']) {
            self::respond($res);
        }
        self::respond(['ok' => true, 'data' => $res['data']['resumen'] ?? []]);
    }
}
