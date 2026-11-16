<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Mpdf\Mpdf;

final class PdfResponse
{
    public static function send(Mpdf $pdf, string $nombreArchivo, string $disposition = 'inline'): void
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $nombreArchivo) ?: 'reporte.pdf';
        if (!str_ends_with(strtolower($safe), '.pdf')) {
            $safe .= '.pdf';
        }
        header('Content-Type: application/pdf; charset=binary');
        header('Content-Disposition: ' . $disposition . '; filename="' . $safe . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf->Output('', 'S');
        exit;
    }
}
