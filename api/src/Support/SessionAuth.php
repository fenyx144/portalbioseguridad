<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Bootstrap\Env;
use Bio\Api\Http\JsonResponse;

final class SessionAuth
{
    public static function start(): void
    {
        $name = Env::get('SESSION_NAME', 'PHPSESSID');
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($name);
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                session_set_cookie_params(0, '/', '', false, true);
            }
            session_start();
        }
    }

    public static function user(): ?array
    {
        if (empty($_SESSION['active']) || empty($_SESSION['usuario'])) {
            return null;
        }

        return [
            'codigo' => (string) $_SESSION['usuario'],
            'nombre' => (string) ($_SESSION['nombre'] ?? ''),
        ];
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            JsonResponse::send(false, null, [
                'code' => 'unauthorized',
                'message' => 'Sesion requerida',
            ], 401);
        }

        return $user;
    }

    public static function login(string $codigo, string $nombre): void
    {
        $_SESSION['active'] = true;
        $_SESSION['usuario'] = $codigo;
        $_SESSION['nombre'] = $nombre;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }
}
