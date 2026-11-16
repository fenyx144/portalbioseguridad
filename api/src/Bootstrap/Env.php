<?php

declare(strict_types=1);

namespace Bio\Api\Bootstrap;

final class Env
{
    /** @var array<string, string> */
    private static $vars = [];

    public static function load(string $apiRoot): void
    {
        foreach ([$apiRoot . '/.env', dirname($apiRoot) . '/.env'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));
                $val = trim($val, "\"'");
                if ($key !== '') {
                    self::$vars[$key] = $val;
                }
            }
            break;
        }

        $grsConfig = dirname($apiRoot) . '/../conexion_grs/configuracion.php';
        if (is_file($grsConfig)) {
            require_once $grsConfig;
            if (!isset(self::$vars['DB_HOST']) && defined('DB_HOST_JOYA')) {
                self::$vars['DB_HOST'] = DB_HOST_JOYA;
                self::$vars['DB_USER'] = DB_USER_JOYA;
                self::$vars['DB_PASSWORD'] = DB_PASSWORD_JOYA;
                self::$vars['DB_NAME'] = DB_NAME_JOYA;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::$vars[$key] ?? $default;
    }
}
