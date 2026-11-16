<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Support\EmpresaEtiqueta;
use Bio\Api\Support\MpdfFactory;
use Bio\Api\Support\PdfTabla;
use Bio\Api\Support\SessionAuth;
use Bio\Api\Support\UserContext;
use Mpdf\Mpdf;

final class ReportPdfService
{
    /** @param array<string, string> $query */
    public function planificacionSemana(array $query): Mpdf
    {
        $inicio = (string) ($query['fecha_inicio'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
            $inicio = (new \DateTimeImmutable('monday this week'))->format('Y-m-d');
        }
        $granja = trim((string) ($query['granja_cod'] ?? ''));
        if ($granja === '') {
            throw new \InvalidArgumentException('granja_cod requerido');
        }
        $svc = new PlanificacionService();
        $result = $svc->semana($inicio, [$granja], null, false);
        if (!($result['ok'] ?? false)) {
            throw new \RuntimeException('No se pudo cargar la planificación');
        }
        $grid = $result['data']['grid'] ?? [];
        $dias = $grid['dias'] ?? [];
        $filas = $grid['filas'] ?? [];
        $granjaNombre = (new GranjaRepository())->nombre($granja);
        $fin = (new \DateTimeImmutable($inicio))->modify('+6 days')->format('Y-m-d');
        $titulo = 'Planificación semanal — ' . ($granjaNombre !== '' ? $granjaNombre : $granja);
        $sub = $inicio . ' al ' . $fin;

        $pdf = MpdfFactory::crear('L');
        $this->encabezado($pdf, $titulo, $sub);

        $html = '<p style="font-size:9px;"><strong>Granja:</strong> ' . htmlspecialchars($granja . ($granjaNombre !== '' ? ' · ' . $granjaNombre : '')) . '</p>';
        $html .= '<table style="border-collapse:collapse;width:100%;font-size:7px;">';
        $html .= '<thead><tr style="background:#2398C5;color:#fff;">';
        $html .= '<th style="border:1px solid #7CB3E7;padding:4px;text-align:left;min-width:90px;">Punto</th>';
        $html .= '<th style="border:1px solid #7CB3E7;padding:4px;width:40px;">Turno</th>';
        foreach ($dias as $d) {
            $html .= '<th style="border:1px solid #7CB3E7;padding:4px;">' . htmlspecialchars(substr($d, 5)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $alt = false;
        foreach ($filas as $fila) {
            $punto = (string) ($fila['punto']['nombre'] ?? '');
            $turnos = $fila['turnos'] ?? [];
            if ($turnos === []) {
                continue;
            }
            $celdas = $fila['celdas'] ?? [];
            $first = true;
            foreach ($turnos as $tp) {
                $tc = (string) ($tp['turno_cod'] ?? '');
                $bg = $alt ? '#AEE4E8' : '#ffffff';
                $html .= '<tr style="background:' . $bg . ';">';
                if ($first) {
                    $html .= '<td style="border:1px solid #7CB3E7;padding:3px;vertical-align:top;" rowspan="' . count($turnos) . '">'
                        . htmlspecialchars($punto) . '</td>';
                    $first = false;
                }
                $html .= '<td style="border:1px solid #7CB3E7;padding:3px;text-align:center;">' . htmlspecialchars($tc) . '</td>';
                foreach ($dias as $d) {
                    $cell = $celdas[$d][$tc] ?? null;
                    $txt = $this->textoCelda($cell);
                    $est = is_array($cell) ? (string) ($cell['estado'] ?? '') : '';
                    $color = $est === 'vacio' ? '#fee2e2' : ($est === 'incompleto' ? '#fef3c7' : '#ecfdf5');
                    $html .= '<td style="border:1px solid #7CB3E7;padding:2px;background:' . $color . ';vertical-align:top;">' . $txt . '</td>';
                }
                $html .= '</tr>';
            }
            $alt = !$alt;
        }
        $html .= '</tbody></table>';
        $pdf->WriteHTML($html);

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function personalAsignado(array $query): Mpdf
    {
        $data = (new ReporteService())->personalAsignado($query);
        $items = $data['data']['items'] ?? [];
        $desde = $data['data']['desde'] ?? '';
        $hasta = $data['data']['hasta'] ?? '';
        $pdf = MpdfFactory::crear('L');
        $this->encabezado($pdf, 'Personal asignado por período', $desde . ' al ' . $hasta);
        $filas = [];
        foreach ($items as $r) {
            $nombre = trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? ''));
            $granjaTxt = (string) ($r['granja_cod'] ?? '');
            if (!empty($r['granja_nombre'])) {
                $granjaTxt .= ' · ' . $r['granja_nombre'];
            }
            $filas[] = [
                (string) ($r['fecha'] ?? ''),
                (string) ($r['turno_cod'] ?? ''),
                $granjaTxt,
                (string) ($r['punto_nombre'] ?? ''),
                (string) ($r['dni'] ?? ''),
                $nombre,
                (string) ($r['empresa_nombre'] ?? $r['empresa'] ?? ''),
                !empty($r['es_reemplazo']) ? 'Sí' : '',
            ];
        }
        PdfTabla::dibujar($pdf, [
            ['titulo' => 'Fecha', 'peso' => 1.15],
            ['titulo' => 'Turno', 'peso' => 0.8],
            ['titulo' => 'Granja', 'peso' => 1.8],
            ['titulo' => 'Punto', 'peso' => 1.6],
            ['titulo' => 'DNI', 'peso' => 1.1],
            ['titulo' => 'Nombre', 'peso' => 2.1],
            ['titulo' => 'Empresa', 'peso' => 1.8],
            ['titulo' => 'Reemplazo', 'peso' => 0.9],
        ], $filas, 7);

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function personalRegistrado(array $query): Mpdf
    {
        $data = (new ReporteService())->personalRegistrado($query);
        $items = $data['data']['items'] ?? [];
        $pdf = MpdfFactory::crear('P');
        $this->encabezado($pdf, 'Personal registrado', 'Listado actual');
        $filas = [];
        foreach ($items as $r) {
            $filas[] = [
                (string) ($r['dni'] ?? ''),
                trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? '')),
                (string) ($r['cargo'] ?? ''),
                EmpresaEtiqueta::personal($r),
                (string) ($r['estado'] ?? ''),
                (string) ($r['granja_base_cod'] ?? ''),
            ];
        }
        PdfTabla::dibujar($pdf, [
            ['titulo' => 'DNI', 'peso' => 1.2],
            ['titulo' => 'Apellidos y nombres', 'peso' => 2.4],
            ['titulo' => 'Cargo', 'peso' => 1.5],
            ['titulo' => 'Contratista', 'peso' => 2],
            ['titulo' => 'Estado', 'peso' => 0.9],
            ['titulo' => 'Granja base', 'peso' => 1.1],
        ], $filas, 8);

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function stockIndumentariaPorProveedor(array $query): Mpdf
    {
        $q = $query;
        $q['categoria'] = 'INDUMENTARIA';
        $data = (new AlmacenService())->stock($q);
        $items = $data['data']['items'] ?? [];
        $pdf = MpdfFactory::crear('L');
        $this->encabezado($pdf, 'Stock de indumentaria por proveedor', 'Saldo inicial vs. stock en almacén contratista');

        $porEmpresa = [];
        foreach ($items as $r) {
            $eid = (int) ($r['empresa_id'] ?? 0);
            $porEmpresa[$eid]['meta'] = [
                'codigo' => (string) ($r['empresa_codigo'] ?? ''),
                'razon' => (string) ($r['empresa_razon_social'] ?? ''),
            ];
            $porEmpresa[$eid]['items'][] = $r;
        }
        uasort($porEmpresa, static function (array $a, array $b): int {
            return strcmp($a['meta']['razon'], $b['meta']['razon']);
        });

        if ($porEmpresa === []) {
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->WriteCell(0, 6, 'Sin movimientos de indumentaria registrados.', 0, 1);
            $pdf->SetTextColor(0, 0, 0);

            return $pdf;
        }
        $cols = [
            ['titulo' => 'Código', 'peso' => 1.1],
            ['titulo' => 'Insumo', 'peso' => 2.2],
            ['titulo' => 'Ud.', 'peso' => 0.6],
            ['titulo' => 'Saldo inicial', 'peso' => 1.1, 'align' => 'R'],
            ['titulo' => 'Entregado', 'peso' => 1, 'align' => 'R'],
            ['titulo' => 'Recibido', 'peso' => 1, 'align' => 'R'],
            ['titulo' => 'Pendiente', 'peso' => 1, 'align' => 'R'],
            ['titulo' => 'Consumido', 'peso' => 1, 'align' => 'R'],
            ['titulo' => 'Stock', 'peso' => 0.9, 'align' => 'R'],
            ['titulo' => 'Faltante', 'peso' => 0.9, 'align' => 'R'],
        ];
        foreach ($porEmpresa as $bloque) {
            $meta = $bloque['meta'];
            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetTextColor(22, 64, 114);
            $pdf->Cell(0, 5, $meta['codigo'] . ' · ' . $meta['razon'], 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $filas = [];
            foreach ($bloque['items'] as $r) {
                $falt = (float) ($r['faltante'] ?? 0);
                $fila = [
                    (string) ($r['insumo_codigo'] ?? ''),
                    (string) ($r['insumo_nombre'] ?? ''),
                    (string) ($r['unidad'] ?? ''),
                ];
                foreach (['saldo_inicial', 'entregado', 'recibido', 'pendiente', 'consumido', 'stock'] as $c) {
                    $fila[] = $this->fmtNum($r[$c] ?? 0);
                }
                $fila[] = $falt > 0
                    ? ['text' => $this->fmtNum($falt), 'color' => '#b91c1c', 'bold' => true]
                    : $this->fmtNum($falt);
                $filas[] = $fila;
            }
            PdfTabla::dibujar($pdf, $cols, $filas, 7);
        }

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function capacitaciones(array $query): Mpdf
    {
        $data = (new ReporteService())->capacitacionesPersonal($query);
        $items = $data['data']['items'] ?? [];
        $pdf = MpdfFactory::crear('L');
        $this->encabezado($pdf, 'Capacitaciones del personal', '');
        $filas = [];
        foreach ($items as $r) {
            $filas[] = [
                (string) ($r['dni'] ?? ''),
                trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? '')),
                (string) ($r['cap_nombre'] ?? ''),
                (string) ($r['fecha_realizada'] ?? ''),
                (string) ($r['fecha_vence'] ?? ''),
                (string) ($r['estado_vigencia'] ?? ''),
            ];
        }
        PdfTabla::dibujar($pdf, [
            ['titulo' => 'DNI', 'peso' => 1.1],
            ['titulo' => 'Nombre', 'peso' => 2.2],
            ['titulo' => 'Tema', 'peso' => 2.2],
            ['titulo' => 'Fecha', 'peso' => 1.1],
            ['titulo' => 'Vigencia', 'peso' => 1.1],
            ['titulo' => 'Estado', 'peso' => 1.2],
        ], $filas, 8);

        return $pdf;
    }

    /**
     * @param float|int|string|null $v
     */
    private function fmtNum($v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    private function encabezado(Mpdf $pdf, string $titulo, string $subtitulo): void
    {
        $logo = MpdfFactory::logoPath();
        $logoHtml = '';
        if ($logo !== null) {
            $logoHtml = '<img src="' . $logo . '" style="height:18px;vertical-align:middle;margin-right:6px;">';
        }
        $usuario = SessionAuth::user();
        $pieUsuario = $usuario ? htmlspecialchars($usuario['codigo'] . ' — ' . $usuario['nombre']) : '';
        $actor = UserContext::isGrs() ? 'GRS' : 'Empresa';
        $pdf->SetHTMLHeader('
        <table width="100%" style="border-collapse:collapse;border:1px solid #164072;">
            <tr>
                <td style="width:28%;padding:5px;font-size:8px;">' . $logoHtml . ' GRANJA RINCONADA DEL SUR S.A.</td>
                <td style="width:44%;text-align:center;padding:5px;background:#2398C5;color:#fff;font-weight:bold;font-size:11px;">'
            . htmlspecialchars($titulo) . '</td>
                <td style="width:28%;text-align:right;padding:5px;font-size:8px;">' . date('d/m/Y H:i') . '<br>' . htmlspecialchars($actor) . '</td>
            </tr>
        </table>');
        if ($subtitulo !== '') {
            $pdf->WriteHTML('<p style="text-align:center;margin:4px 0 8px;font-size:9px;"><strong>' . htmlspecialchars($subtitulo) . '</strong></p>');
        }
        $pdf->SetHTMLFooter('<div style="font-size:7px;text-align:center;border-top:1px solid #ccc;padding-top:4px;">'
            . $pieUsuario . ' · Página {PAGENO} de {nbpg}</div>');
    }

    /**
     * @param array<string, mixed>|null $cell
     */
    private function textoCelda(?array $cell): string
    {
        if ($cell === null) {
            return '';
        }
        $parts = [];
        foreach ($cell['asignaciones'] ?? [] as $a) {
            $nom = trim((string) ($a['apellidos'] ?? '') . ' ' . substr((string) ($a['nombres'] ?? ''), 0, 1) . '.');
            if ($nom === '') {
                $nom = (string) ($a['dni'] ?? '');
            }
            if (!empty($a['es_reemplazo'])) {
                $nom .= ' (R)';
            }
            $parts[] = htmlspecialchars($nom);
        }
        $req = (int) ($cell['requerido'] ?? 0);
        $asig = (int) ($cell['asignado'] ?? count($parts));
        $footer = $req > 0 ? '<br><span style="color:#64748B;font-size:6px;">' . $asig . '/' . $req . '</span>' : '';

        return $parts === [] ? '—' . $footer : implode('<br>', $parts) . $footer;
    }
}
