<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Repositories\ConfigRepository;

final class ConfigController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        self::respond(['ok' => true, 'data' => (new ConfigRepository())->all()]);
    }

    public static function patch(): void
    {
        self::auth();
        $body = self::body();
        $repo = new ConfigRepository();
        foreach ($body as $k => $v) {
            if (is_string($k) && is_scalar($v)) {
                $repo->set($k, (string) $v);
            }
        }
        self::respond(['ok' => true, 'data' => $repo->all()]);
    }
}
