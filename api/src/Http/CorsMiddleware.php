<?php

declare(strict_types=1);

namespace Bio\Api\Http;

use Bio\Api\Bootstrap\Env;

final class CorsMiddleware
{
    public static function apply(): void
    {
        $origins = array_filter(array_map('trim', explode(',', Env::get('CORS_ORIGINS', 'http://localhost:5173'))));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Usuario-Codigo');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
