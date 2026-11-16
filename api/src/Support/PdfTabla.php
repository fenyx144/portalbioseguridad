<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Mpdf\Mpdf;

/**
 * Dibuja la misma tabla que el HTML de mPDF (columnas, textos, cebra y cabecera)
 * sin el parser de estilos por celda.
 */
final class PdfTabla
{
    /**
     * @param list<array{titulo:string, peso:float, align?:string}> $columnas
     * @param list<list<string|array{text:string, color?:string, bold?:bool}>> $filas
     */
    public static function dibujar(Mpdf $pdf, array $columnas, array $filas, float $tamano = 7.5): void
    {
        if ((int) $pdf->page < 1) {
            $pdf->AddPage();
        }
        $ancho = $pdf->w - $pdf->lMargin - $pdf->rMargin;
        $pesos = 0.0;
        foreach ($columnas as $c) {
            $pesos += (float) $c['peso'];
        }
        if ($pesos <= 0) {
            $pesos = 1;
        }
        $anchos = [];
        foreach ($columnas as $c) {
            $anchos[] = $ancho * ((float) $c['peso'] / $pesos);
        }
        $lineH = $tamano * 0.45;
        $pdf->SetFont('dejavusans', '', $tamano);
        self::cabecera($pdf, $columnas, $anchos, $lineH + 1.6);
        foreach ($filas as $i => $fila) {
            $textos = [];
            $estilos = [];
            $lineas = 1;
            foreach ($fila as $k => $celda) {
                $txt = is_array($celda) ? (string) ($celda['text'] ?? '') : (string) $celda;
                $textos[$k] = $txt;
                $estilos[$k] = is_array($celda) ? $celda : [];
                $w = max(1.0, $anchos[$k] - 1.6);
                $pdf->SetFont('dejavusans', !empty($estilos[$k]['bold']) ? 'B' : '', $tamano);
                $lineas = max($lineas, self::contarLineas($pdf, $txt, $w));
            }
            $alto = ($lineas * $lineH) + 1.6;
            if ($pdf->y + $alto > ($pdf->h - $pdf->bMargin)) {
                $pdf->AddPage();
                self::cabecera($pdf, $columnas, $anchos, $lineH + 1.6);
            }
            $y = $pdf->y;
            $x = $pdf->lMargin;
            $pdf->SetDrawColor(124, 179, 231);
            if ($i % 2 === 0) {
                $pdf->SetFillColor(255, 255, 255);
            } else {
                $pdf->SetFillColor(174, 228, 232);
            }
            foreach ($textos as $k => $txt) {
                $pdf->Rect($x, $y, $anchos[$k], $alto, 'DF');
                $bold = !empty($estilos[$k]['bold']);
                $pdf->SetFont('dejavusans', $bold ? 'B' : '', $tamano);
                self::colorTexto($pdf, (string) ($estilos[$k]['color'] ?? ''));
                $align = (string) ($columnas[$k]['align'] ?? 'L');
                $w = $anchos[$k] - 1.6;
                $yy = $y + 0.5;
                foreach (self::partir($pdf, $txt, $w) as $linea) {
                    $pdf->SetXY($x + 0.8, $yy);
                    $pdf->Cell($w, $lineH, $linea, 0, 0, $align, false);
                    $yy += $lineH;
                }
                $x += $anchos[$k];
            }
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($pdf->lMargin, $y + $alto);
        }
    }

    /**
     * @param list<array{titulo:string, peso:float, align?:string}> $columnas
     * @param list<float> $anchos
     */
    private static function cabecera(Mpdf $pdf, array $columnas, array $anchos, float $alto): void
    {
        if ($pdf->y + $alto > ($pdf->h - $pdf->bMargin)) {
            $pdf->AddPage();
        }
        $pdf->SetFont('dejavusans', 'B', $pdf->default_font_size > 0 ? 8 : 8);
        $pdf->SetFillColor(35, 152, 197);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(124, 179, 231);
        $x = $pdf->lMargin;
        $y = $pdf->y;
        foreach ($columnas as $k => $c) {
            $pdf->Rect($x, $y, $anchos[$k], $alto, 'DF');
            $pdf->SetXY($x + 0.8, $y + 0.7);
            $pdf->Cell($anchos[$k] - 1.6, $alto - 1.4, (string) $c['titulo'], 0, 0, 'L', false);
            $x += $anchos[$k];
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('dejavusans', '', 7.5);
        $pdf->SetXY($pdf->lMargin, $y + $alto);
    }

    private static function colorTexto(Mpdf $pdf, string $hex): void
    {
        if (!preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m)) {
            $pdf->SetTextColor(0, 0, 0);

            return;
        }
        $n = hexdec($m[1]);
        $pdf->SetTextColor(($n >> 16) & 255, ($n >> 8) & 255, $n & 255);
    }

    private static function contarLineas(Mpdf $pdf, string $txt, float $ancho): int
    {
        return max(1, count(self::partir($pdf, $txt, $ancho)));
    }

    /** @return list<string> */
    private static function partir(Mpdf $pdf, string $txt, float $ancho): array
    {
        $ancho = max(1.0, $ancho);
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $txt) ?: [''] as $bloque) {
            if ($bloque === '') {
                $out[] = '';
                continue;
            }
            $actual = '';
            foreach (preg_split('/\s+/u', $bloque) ?: [] as $palabra) {
                if ($palabra === '') {
                    continue;
                }
                $candidato = $actual === '' ? $palabra : $actual . ' ' . $palabra;
                if ($pdf->GetStringWidth($candidato) <= $ancho) {
                    $actual = $candidato;
                    continue;
                }
                if ($actual !== '') {
                    $out[] = $actual;
                }
                if ($pdf->GetStringWidth($palabra) <= $ancho) {
                    $actual = $palabra;
                    continue;
                }
                $trozo = '';
                $len = strlen($palabra);
                for ($i = 0; $i < $len; $i++) {
                    $ch = $palabra[$i];
                    if ($pdf->GetStringWidth($trozo . $ch) > $ancho && $trozo !== '') {
                        $out[] = $trozo;
                        $trozo = $ch;
                    } else {
                        $trozo .= $ch;
                    }
                }
                $actual = $trozo;
            }
            $out[] = $actual;
        }

        return $out === [] ? [''] : $out;
    }
}
