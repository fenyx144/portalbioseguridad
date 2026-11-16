<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Services\CapacitacionRegistroService;
use Bio\Api\Services\CapacitacionSesionService;
use Bio\Api\Services\CapacitacionService;

final class CapacitacionController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        $solo = (self::query()['todos'] ?? '') !== '1';
        self::respond((new CapacitacionService())->listar($solo));
    }

    public static function registrosEmpresa(): void
    {
        self::auth();
        self::respond((new CapacitacionRegistroService())->resumenEmpresa(self::query()), 422);
    }

    public static function store(): void
    {
        self::auth();
        self::respond((new CapacitacionService())->crear(self::body()), 422);
    }

    public static function update(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionService())->actualizar((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function sesionesIndex(): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->listar(self::query()), 422);
    }

    public static function sesionesShow(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->detalle((int) ($params['id'] ?? 0)));
    }

    public static function sesionesStore(): void
    {
        $user = self::auth();
        self::respond((new CapacitacionSesionService())->crear(self::body(), (string) ($user['codigo'] ?? '')), 422);
    }

    public static function sesionesUpdate(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->actualizar((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function sesionesAsistencia(array $params): void
    {
        $user = self::auth();
        self::respond(
            (new CapacitacionSesionService())->registrarAsistencia((int) ($params['id'] ?? 0), self::body(), (string) ($user['codigo'] ?? '')),
            422
        );
    }

    public static function sesionesEvaluacion(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->registrarEvaluacion((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function sesionesAgregarParticipantes(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->agregarParticipantes((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function sesionesQuitarParticipante(array $params): void
    {
        self::auth();
        self::respond(
            (new CapacitacionSesionService())->quitarParticipante((int) ($params['id'] ?? 0), (int) ($params['personalId'] ?? 0)),
            422
        );
    }

    public static function sesionesCancelar(array $params): void
    {
        self::auth();
        self::respond((new CapacitacionSesionService())->cancelar((int) ($params['id'] ?? 0)));
    }
}
