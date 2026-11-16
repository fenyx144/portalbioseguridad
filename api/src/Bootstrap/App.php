<?php

declare(strict_types=1);

namespace Bio\Api\Bootstrap;

use Bio\Api\Http\CorsMiddleware;
use Bio\Api\Http\JsonResponse;
use Bio\Api\Http\Router;
use Bio\Api\Routes\RouteRegistrar;
use Bio\Api\Support\SessionAuth;
use Throwable;

final class App
{
    public static function run(): void
    {
        self::autoload();

        try {
            Env::load(dirname(__DIR__, 2));
            CorsMiddleware::apply();
            SessionAuth::start();

            $router = new Router();
            RouteRegistrar::register($router);
            $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', self::path());
        } catch (Throwable $e) {
            JsonResponse::send(false, null, [
                'code' => 'server_error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    private static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = is_string($uri) ? $uri : '/';
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }

        return '/' . ltrim($uri, '/');
    }

    private static function autoload(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Bio\\Api\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = dirname(__DIR__) . '/' . $rel . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
