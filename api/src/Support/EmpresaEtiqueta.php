<?php

declare(strict_types=1);

namespace Bio\Api\Support;

/** Texto legible para contratista / GRS en listados y reportes. */
final class EmpresaEtiqueta
{
    /** @param array<string, mixed> $row */
    public static function personal(array $row): string
    {
        if (($row['empresa'] ?? '') === 'GRS') {
            return 'GRS';
        }
        $cod = trim((string) ($row['empresa_codigo'] ?? ''));
        $razon = trim((string) ($row['empresa_razon_social'] ?? ''));
        if ($razon !== '' && $cod !== '') {
            return $cod . ' · ' . $razon;
        }
        if ($razon !== '') {
            return $razon;
        }
        if ($cod !== '') {
            return $cod;
        }

        return 'Contratista sin asignar';
    }
}
