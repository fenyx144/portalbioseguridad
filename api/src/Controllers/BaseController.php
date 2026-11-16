<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Support\MobileAppAuth;
use Bio\Api\Support\Request;
use Bio\Api\Support\SessionAuth;

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

        return SessionAuth::requireUser();
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
