<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Repositories\GranjaRepository;

final class GranjaController extends BaseController
{
    public static function index(): void
    {
        self::auth();
        self::respond(['ok' => true, 'data' => ['items' => (new GranjaRepository())->listar()]]);
    }
}
