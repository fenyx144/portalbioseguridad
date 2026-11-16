<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Support\MobileAppAuth;
use Bio\Api\Support\Request;
use Bio\Api\Support\SanidadAuthBridge;
use Bio\Api\Support\SessionAuth;
use Bio\Api\Support\UserContext;

final class AuthController
{
    public static function login(): void
    {
        $body = Request::jsonBody();
        $usuario = trim((string) ($body['usuario'] ?? ''));
        $clave = trim((string) ($body['clave'] ?? ''));
        if ($usuario === '' || $clave === '') {
            JsonResponse::send(false, null, [
                'code' => 'validation',
                'message' => 'Usuario y clave son obligatorios',
            ], 422);
        }

        $result = SanidadAuthBridge::authenticate($usuario, $clave);
        if ($result === null) {
            JsonResponse::send(false, null, [
                'code' => 'invalid_credentials',
                'message' => 'Credenciales invalidas',
            ], 401);
        }

        SessionAuth::login($result['codigo'], $result['nombre']);
        $profile = UserContext::enrichUser($result);
        UserContext::bindSession($profile);
        JsonResponse::send(true, ['user' => $profile], null);
    }

    public static function logout(): void
    {
        SessionAuth::logout();
        JsonResponse::send(true, null, null);
    }

    public static function me(): void
    {
        MobileAppAuth::ensureSession();
        $user = SessionAuth::user();
        if ($user === null) {
            JsonResponse::send(false, null, [
                'code' => 'unauthorized',
                'message' => 'Sin sesion',
            ], 401);
        }
        $profile = UserContext::enrichUser($user);
        UserContext::bindSession($profile);
        JsonResponse::send(true, ['user' => $profile], null);
    }
}
