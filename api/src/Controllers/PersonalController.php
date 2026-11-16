<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Repositories\PersonalRepository;
use Bio\Api\Services\DniService;
use Bio\Api\Services\PersonalCapacitacionService;
use Bio\Api\Services\PersonalService;
use Bio\Api\Support\UserContext;

final class PersonalController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        self::respond((new PersonalService())->listar($query));
    }

    public static function cargos(): void
    {
        self::auth();
        $q = self::query();
        $eid = UserContext::isGrs()
            ? (isset($q['empresa_id']) && $q['empresa_id'] !== '' ? (int) $q['empresa_id'] : null)
            : UserContext::empresaId();
        self::respond(['ok' => true, 'data' => ['items' => (new PersonalRepository())->cargos($eid)]]);
    }

    public static function show(array $params): void
    {
        self::auth();
        $id = (int) ($params['id'] ?? 0);
        $row = (new PersonalRepository())->find($id);
        if ($row === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']], 404);
        }
        UserContext::assertPersonalOwned($row);
        self::respond(['ok' => true, 'data' => $row]);
    }

    public static function consultarDni(array $params): void
    {
        self::auth();
        $r = (new DniService())->consultar((string) ($params['dni'] ?? ''));
        $code = $r['error']['code'] ?? '';
        self::respond($r, $code === 'validation' ? 422 : ($code === 'not_found' ? 404 : 502));
    }

    public static function ficha(array $params): void
    {
        self::auth();
        self::respond((new PersonalService())->ficha((int) ($params['id'] ?? 0)));
    }

    public static function store(): void
    {
        self::auth();
        self::respond((new PersonalService())->crear(self::body()), 422);
    }

    public static function update(array $params): void
    {
        self::auth();
        self::respond((new PersonalService())->actualizar((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function uploadFoto(array $params): void
    {
        self::auth();
        $id = (int) ($params['id'] ?? 0);
        $repo = new PersonalRepository();
        $persona = $repo->find($id);
        if ($persona === null) {
            self::respond(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No encontrado']], 404);
        }
        UserContext::assertPersonalOwned($persona);
        if (!isset($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
            self::respond(['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Archivo requerido']], 422);
        }
        $f = $_FILES['foto'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = mime_content_type($f['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            self::respond(['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Formato no permitido']], 422);
        }
        if ($f['size'] > 2 * 1024 * 1024) {
            self::respond(['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Maximo 2MB']], 422);
        }
        if ($mime === 'image/png') {
            $ext = 'png';
        } elseif ($mime === 'image/webp') {
            $ext = 'webp';
        } else {
            $ext = 'jpg';
        }
        $dir = dirname(__DIR__, 2) . '/public/storage/personal';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'p' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($f['tmp_name'], $path)) {
            self::respond(['ok' => false, 'error' => ['code' => 'server', 'message' => 'No se pudo guardar']], 500);
        }
        $ruta = 'storage/personal/' . $name;
        $repo->actualizarFoto($id, $ruta);
        $persona['foto_ruta'] = $ruta;
        try {
            (new \Bio\Api\Support\PersonalProveedorSync())->publicar($persona, (string) ($_SESSION['usuario'] ?? ''));
        } catch (\Throwable $e) {
        }
        self::respond(['ok' => true, 'data' => ['foto_ruta' => $ruta, 'foto_url' => '/personal/dni/' . rawurlencode((string) $persona['dni']) . '/foto']]);
    }

    public static function addCapacitacion(array $params): void
    {
        self::auth();
        self::respond((new PersonalCapacitacionService())->agregar((int) ($params['id'] ?? 0), self::body()), 422);
    }

    public static function deleteCapacitacion(array $params): void
    {
        self::auth();
        self::respond((new PersonalCapacitacionService())->eliminar((int) ($params['id'] ?? 0), (int) ($params['rowId'] ?? 0)), 404);
    }
}
