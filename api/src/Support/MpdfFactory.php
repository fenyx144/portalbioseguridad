<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Mpdf\Mpdf;

final class MpdfFactory
{
  public static function crear(string $orientacion = 'P'): Mpdf
    {
        self::boot();
        $tmp = self::tempDir();
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientacion === 'L' ? 'A4-L' : 'A4',
            'margin_top' => 22,
            'margin_bottom' => 18,
            'margin_left' => 12,
            'margin_right' => 12,
            'default_font_size' => 8,
            'tempDir' => $tmp,
        ]);
    }

    private static function boot(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $candidates = [
            dirname(__DIR__, 3) . '/../sanidad/vendor/autoload.php',
            dirname(__DIR__, 4) . '/sanidad/vendor/autoload.php',
            dirname(__DIR__, 3) . '/vendor/autoload.php',
        ];
        foreach ($candidates as $i => $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                $candidates[$i] = $resolved;
            }
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                $loaded = true;

                return;
            }
        }
        throw new \RuntimeException('mPDF no disponible: instale composer en sanidad o en bioseguridad/api');
    }

    private static function tempDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/pdf_tmp';
    }

    /** Ruta del logo compartido con Sanidad, si existe. */
    public static function logoPath(): ?string
    {
        $p = dirname(__DIR__, 4) . '/sanidad/logo.png';

        return is_file($p) ? $p : null;
    }
}
