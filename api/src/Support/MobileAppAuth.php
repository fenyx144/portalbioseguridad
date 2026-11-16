<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Bootstrap\Env;

/**
 * Misma identidad que la app móvil GRS: token Bearer + código de usuario
 * (tabla `usuario` compartida por GRS y terceros). No es un login aparte.
 */
final class MobileAppAuth
{
    public static function ensureSession(): void
    {
        if (SessionAuth::user() !== null) {
            return;
        }
        $token = self::bearerToken();
        $expected = Env::get('MOBILE_API_TOKEN', 'd9f7a6b3-4c8e-11ee-be56-0242ac120002');
        if ($token === '' || !hash_equals($expected, $token)) {
            return;
        }
        $codigo = trim(self::header('X-Usuario-Codigo'));
        if ($codigo === '') {
            return;
        }
        $user = SanidadAuthBridge::userByCodigo($codigo);
        if ($user === null) {
            return;
        }
        SessionAuth::login($user['codigo'], $user['nombre']);
        $profile = UserContext::enrichUser($user);
        UserContext::bindSession($profile);
    }

    private static function bearerToken(): string
    {
        $auth = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && is_string($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } else {
            $auth = self::header('Authorization');
        }
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return '';
    }

    private static function header(string $name): string
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($name));
        if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $k => $v) {
                    if (strcasecmp((string) $k, $name) === 0 && is_string($v)) {
                        return $v;
                    }
                }
            }
        }

        return '';
    }
}
