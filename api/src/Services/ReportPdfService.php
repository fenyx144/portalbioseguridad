<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Support\EmpresaEtiqueta;
use Bio\Api\Support\MpdfFactory;
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
        $granjaNombre = '';
        foreach ((new GranjaRepository())->listar() as $g) {
            if ($g['codigo'] === $granja) {
                $granjaNombre = (string) $g['nombre'];
                break;
            }
        }
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
        $pdf->WriteHTML($this->tablaAsignaciones($items));

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function personalRegistrado(array $query): Mpdf
    {
        $data = (new ReporteService())->personalRegistrado($query);
        $items = $data['data']['items'] ?? [];
        $pdf = MpdfFactory::crear('P');
        $this->encabezado($pdf, 'Personal registrado', 'Listado actual');
        $html = '<table style="border-collapse:collapse;width:100%;font-size:8px;">';
        $html .= '<thead><tr style="background:#2398C5;color:#fff;">';
        foreach (['DNI', 'Apellidos y nombres', 'Cargo', 'Contratista', 'Estado', 'Granja base'] as $h) {
            $html .= '<th style="border:1px solid #7CB3E7;padding:4px;text-align:left;">' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $n = 0;
        foreach ($items as $r) {
            $bg = $n % 2 === 0 ? '#ffffff' : '#AEE4E8';
            $nombre = trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? ''));
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['dni'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars($nombre) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['cargo'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars(EmpresaEtiqueta::personal($r)) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['estado'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['granja_base_cod'] ?? '')) . '</td>';
            $html .= '</tr>';
            $n++;
        }
        $html .= '</tbody></table>';
        $pdf->WriteHTML($html);

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

        $html = '';
        if ($porEmpresa === []) {
            $html .= '<p style="font-size:9px;color:#64748B;">Sin movimientos de indumentaria registrados.</p>';
        }
        foreach ($porEmpresa as $bloque) {
            $meta = $bloque['meta'];
            $filas = $bloque['items'];
            $html .= '<h3 style="font-size:10px;margin:10px 0 4px;color:#164072;">'
                . htmlspecialchars($meta['codigo'] . ' · ' . $meta['razon']) . '</h3>';
            $html .= '<table style="border-collapse:collapse;width:100%;font-size:7px;margin-bottom:8px;">';
            $html .= '<thead><tr style="background:#2398C5;color:#fff;">';
            foreach (['Código', 'Insumo', 'Ud.', 'Saldo inicial', 'Entregado', 'Recibido', 'Pendiente', 'Consumido', 'Stock', 'Faltante'] as $h) {
                $html .= '<th style="border:1px solid #7CB3E7;padding:3px;text-align:left;">' . htmlspecialchars($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            $n = 0;
            foreach ($filas as $r) {
                $bg = $n % 2 === 0 ? '#ffffff' : '#AEE4E8';
                $falt = (float) ($r['faltante'] ?? 0);
                $faltStyle = $falt > 0 ? 'color:#b91c1c;font-weight:bold;' : '';
                $html .= '<tr style="background:' . $bg . ';">';
                $html .= '<td style="border:1px solid #7CB3E7;padding:2px;">' . htmlspecialchars((string) ($r['insumo_codigo'] ?? '')) . '</td>';
                $html .= '<td style="border:1px solid #7CB3E7;padding:2px;">' . htmlspecialchars((string) ($r['insumo_nombre'] ?? '')) . '</td>';
                $html .= '<td style="border:1px solid #7CB3E7;padding:2px;">' . htmlspecialchars((string) ($r['unidad'] ?? '')) . '</td>';
                foreach (['saldo_inicial', 'entregado', 'recibido', 'pendiente', 'consumido', 'stock'] as $c) {
                    $html .= '<td style="border:1px solid #7CB3E7;padding:2px;text-align:right;">'
                        . htmlspecialchars($this->fmtNum($r[$c] ?? 0)) . '</td>';
                }
                $html .= '<td style="border:1px solid #7CB3E7;padding:2px;text-align:right;' . $faltStyle . '">'
                    . htmlspecialchars($this->fmtNum($falt)) . '</td>';
                $html .= '</tr>';
                $n++;
            }
            $html .= '</tbody></table>';
        }
        $pdf->WriteHTML($html);

        return $pdf;
    }

    /** @param array<string, string> $query */
    public function capacitaciones(array $query): Mpdf
    {
        $data = (new ReporteService())->capacitacionesPersonal($query);
        $items = $data['data']['items'] ?? [];
        $pdf = MpdfFactory::crear('L');
        $this->encabezado($pdf, 'Capacitaciones del personal', '');
        $html = '<table style="border-collapse:collapse;width:100%;font-size:8px;">';
        $html .= '<thead><tr style="background:#2398C5;color:#fff;">';
        foreach (['DNI', 'Nombre', 'Tema', 'Fecha', 'Vigencia', 'Estado'] as $h) {
            $html .= '<th style="border:1px solid #7CB3E7;padding:4px;text-align:left;">' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $n = 0;
        foreach ($items as $r) {
            $bg = $n % 2 === 0 ? '#ffffff' : '#AEE4E8';
            $nombre = trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? ''));
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['dni'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars($nombre) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['cap_nombre'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['fecha_realizada'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['fecha_vence'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['estado_vigencia'] ?? '')) . '</td>';
            $html .= '</tr>';
            $n++;
        }
        $html .= '</tbody></table>';
        $pdf->WriteHTML($html);

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

    /** @param list<array<string, mixed>> $items */
    private function tablaAsignaciones(array $items): string
    {
        $html = '<table style="border-collapse:collapse;width:100%;font-size:7px;">';
        $html .= '<thead><tr style="background:#2398C5;color:#fff;">';
        foreach (['Fecha', 'Turno', 'Granja', 'Punto', 'DNI', 'Nombre', 'Empresa', 'Reemplazo'] as $h) {
            $html .= '<th style="border:1px solid #7CB3E7;padding:4px;text-align:left;">' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $n = 0;
        foreach ($items as $r) {
            $bg = $n % 2 === 0 ? '#ffffff' : '#AEE4E8';
            $nombre = trim((string) ($r['apellidos'] ?? '') . ', ' . (string) ($r['nombres'] ?? ''));
            $granja = (string) ($r['granja_cod'] ?? '');
            if (!empty($r['granja_nombre'])) {
                $granja .= ' · ' . $r['granja_nombre'];
            }
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['fecha'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['turno_cod'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars($granja) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['punto_nombre'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['dni'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars($nombre) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . htmlspecialchars((string) ($r['empresa_nombre'] ?? $r['empresa'] ?? '')) . '</td>';
            $html .= '<td style="border:1px solid #7CB3E7;padding:3px;">' . (!empty($r['es_reemplazo']) ? 'Sí' : '') . '</td>';
            $html .= '</tr>';
            $n++;
        }
        $html .= '</tbody></table>';

        return $html;
    }
}
