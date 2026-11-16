<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Services\OperacionService;
use Bio\Api\Support\PersonalProveedorSync;
use Bio\Api\Support\SanidadTrabajadorBridge;
use Bio\Api\Support\UserContext;

final class OperacionController extends BaseController
{
    public static function puestos(): void
    {
        self::auth();
        self::respond((new OperacionService())->puestos());
    }

    public static function requerimientos(): void
    {
        self::auth();
        self::respond((new OperacionService())->requerimientos());
    }

    public static function guardarRequerimientos(): void
    {
        self::auth();
        self::respond((new OperacionService())->guardarRequerimientos(self::body()));
    }

    public static function asistencia(): void
    {
        self::auth();
        $q = self::query();
        UserContext::applyPersonalScope($q);
        self::respond((new OperacionService())->asistencia($q));
    }

    public static function marcar(): void
    {
        $user = self::auth();
        $desdeApp = self::desdeApp();
        self::respond((new OperacionService())->marcar(self::body(), $user['codigo'], $desdeApp), 422);
    }

    public static function alcoholemia(): void
    {
        self::auth();
        $q = self::query();
        UserContext::applyPersonalScope($q);
        self::respond((new OperacionService())->alcoholemia($q));
    }

    public static function registrarAlcoholemia(): void
    {
        $user = self::auth();
        self::respond((new OperacionService())->registrarAlcoholemia(self::body(), $user['codigo'], self::desdeApp()), 422);
    }

    public static function anomalias(): void
    {
        self::auth();
        $q = self::query();
        UserContext::applyPersonalScope($q);
        self::respond((new OperacionService())->anomalias($q));
    }

    public static function crearAnomalia(): void
    {
        $user = self::auth();
        self::respond((new OperacionService())->crearAnomalia(self::body(), $user['codigo']), 422);
    }

    public static function actualizarAnomalia(array $params): void
    {
        self::auth();
        self::respond((new OperacionService())->actualizarAnomalia((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function faltante(): void
    {
        self::auth();
        $q = self::query();
        UserContext::applyPersonalScope($q);
        self::respond((new OperacionService())->reporteFaltante($q));
    }

    public static function horarios(): void
    {
        self::auth();
        $q = self::query();
        UserContext::applyPersonalScope($q);
        self::respond((new OperacionService())->reporteHorarios($q));
    }

    public static function fotoReferencia(array $params): void
    {
        self::auth();
        $dni = trim((string) ($params['dni'] ?? ''));
        $repo = new PersonalRepository();
        $row = $repo->findFichaPorDni($dni);
        if ($row === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Personal no encontrado']], 404);
        }
        UserContext::assertPersonalOwned($row);
        $ruta = trim((string) ($row['foto_ruta'] ?? ''));
        $abs = self::resolverFoto($ruta);
        JsonResponse::send(true, [
            'dni' => $row['dni'],
            'personal_id' => (int) $row['id'],
            'tiene_foto' => $abs !== null,
            'foto_url' => '/personal/dni/' . rawurlencode((string) $row['dni']) . '/foto',
        ], null);
    }

    public static function fotoArchivo(array $params): void
    {
        self::auth();
        $dni = trim((string) ($params['dni'] ?? ''));
        $repo = new PersonalRepository();
        $row = $repo->findFichaPorDni($dni);
        if ($row === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Personal no encontrado']], 404);
        }
        UserContext::assertPersonalOwned($row);
        $abs = self::resolverFoto((string) ($row['foto_ruta'] ?? ''));
        if ($abs === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Sin foto de referencia']], 404);
        }
        $mime = mime_content_type($abs) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($abs));
        header('Cache-Control: private, max-age=300');
        readfile($abs);
        exit;
    }

    public static function asegurarUsuario(array $params): void
    {
        self::auth();
        $id = (int) ($params['id'] ?? 0);
        $repo = new PersonalRepository();
        $p = $repo->find($id);
        if ($p === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']], 404);
        }
        UserContext::assertPersonalOwned($p);
        if (($p['empresa'] ?? '') !== 'TERCERO' || empty($p['empresa_id'])) {
            self::respond(['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Solo personal de un tercero']], 422);
        }
        $nombre = trim((string) $p['nombres'] . ' ' . (string) $p['apellidos']);
        $r = SanidadTrabajadorBridge::asegurar((string) $p['dni'], $nombre, $id, (int) $p['empresa_id']);
        if (empty($r['ok'])) {
            self::respond(['ok' => false, 'error' => ['code' => 'usuario', 'message' => $r['message'] ?? 'No se pudo crear el usuario']], 422);
        }
        $repo->setUsuarioCod($id, (string) $r['usuario_cod']);
        (new PersonalProveedorSync())->publicar($p, (string) ($_SESSION['usuario'] ?? ''));
        self::respond(['ok' => true, 'data' => $r]);
    }

    private static function desdeApp(): bool
    {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        return str_starts_with($auth, 'Bearer ');
    }

    private static function resolverFoto(string $ruta): ?string
    {
        $ruta = trim($ruta);
        if ($ruta === '') {
            return null;
        }
        if (is_file($ruta)) {
            return $ruta;
        }
        $rel = ltrim($ruta, '/');
        $candidatos = [
            dirname(__DIR__, 2) . '/public/' . $rel,
            dirname(__DIR__, 3) . '/' . $rel,
            dirname(__DIR__, 4) . '/sanidad/' . $rel,
        ];
        foreach ($candidatos as $c) {
            if (is_file($c)) {
                return $c;
            }
        }

        return null;
    }
}
