<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Support\BioseguridadAcceso;
use Bio\Api\Support\MobileAppAuth;
use Bio\Api\Support\Request;
use Bio\Api\Support\SessionAuth;
use Bio\Api\Support\UserContext;

abstract class BaseController
{
    protected static function respond(array $result, int $failStatus = 400): void
    {
        if (!($result['ok'] ?? false)) {
            JsonResponse::send(false, null, $result['error'] ?? ['code' => 'error', 'message' => 'Error'], $failStatus);
        }
        JsonResponse::send(true, $result['data'] ?? null, null);
    }

    protected static function auth(): array
    {
        MobileAppAuth::ensureSession();
        $user = SessionAuth::requireUser();
        $gate = BioseguridadAcceso::admitir($user);
        if (!$gate['ok']) {
            SessionAuth::logout();
            JsonResponse::send(false, null, [
                'code' => 'forbidden',
                'message' => $gate['message'],
            ], 403);
        }
        UserContext::bindSession($gate['profile']);

        return $user;
    }

    /** @return array<string, string> */
    protected static function query(): array
    {
        return Request::query();
    }

    /** @return array<string, mixed> */
    protected static function body(): array
    {
        return Request::jsonBody();
    }
}
