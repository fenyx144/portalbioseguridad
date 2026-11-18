<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Repositories\ProveedorPlanRepository;
use Bio\Api\Support\BioseguridadAcceso;
use Bio\Api\Support\UserContext;
use Throwable;

/**
 * Planificación del proveedor sobre las tablas de Sanidad (sin copiarlas).
 * El listado es de todo el RUC, no del usuario que registró la fila.
 */
final class ProveedorPlanService
{
    /** @var ProveedorPlanRepository */
    private $repo;

    /** @var GranjaRepository */
    private $granjas;

    public function __construct()
    {
        $this->repo = new ProveedorPlanRepository();
        $this->granjas = new GranjaRepository();
    }

    /** @param array<string, string> $q */
    public function contexto(array $q): array
    {
        $alc = $this->alcance($q, false);
        if (!$alc['ok']) {
            return $alc;
        }
        $ruc = $alc['ruc'];
        $personal = [];
        $vehiculos = [];
        if ($ruc !== '') {
            try {
                $personal = $this->filasPersonal($ruc);
                $vehiculos = $this->filasVehiculo($ruc);
            } catch (Throwable $e) {
                return self::error('db', 'No está disponible la nómina del proveedor.');
            }
        }

        return [
            'ok' => true,
            'data' => [
                'rol' => UserContext::isGrs() ? 'grs' : 'empresa',
                'ruc' => $ruc,
                'razon' => $alc['razon'],
                'proceso' => $alc['proceso'],
                'empresa_id' => $alc['empresa_id'],
                'granjas' => $this->granjasLista(),
                'personal' => $personal,
                'vehiculos' => $vehiculos,
                'empresas' => UserContext::isGrs() ? $this->empresas() : [],
            ],
        ];
    }

    /** @param array<string, string> $q */
    public function listar(array $q): array
    {
        $alc = $this->alcance($q, false);
        if (!$alc['ok']) {
            return $alc;
        }
        $desde = self::fecha($q['desde'] ?? '');
        $hasta = self::fecha($q['hasta'] ?? '');
        $g3 = self::granja3((string) ($q['granja'] ?? ''));
        try {
            $rows = $this->repo->listar($alc['ruc'], $desde, $hasta, $g3);
        } catch (Throwable $e) {
            return self::error('db', 'No está disponible la planificación del proveedor.');
        }
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        $personas = $this->repo->personasDe($ids);
        $vehs = $this->repo->vehiculosDe($ids);
        $nombres = $this->mapaGranjas();
        $filas = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $g = self::granja3((string) $row['granja']);
            $dnis = $personas[$id] ?? [];
            $placas = [];
            foreach ($vehs[$id] ?? [] as $v) {
                $placas[] = $v['placa'];
            }
            $filas[] = [
                'id' => $id,
                'fecha' => substr((string) $row['fecha'], 0, 10),
                'granja' => $g,
                'granja_nombre' => $nombres[$g] ?? ($nombres[(string) $row['granja']] ?? $g),
                'campania' => (string) $row['campania'],
                'proceso' => (string) $row['motivo'],
                'observacion' => (string) $row['observacion'],
                'ruc' => (string) $row['proveedorRuc'],
                'razon' => (string) $row['proveedor'],
                'usuario' => (string) $row['usuarioRegistrador'],
                'personas' => $dnis,
                'num_personas' => count($dnis) > 0 ? count($dnis) : (int) $row['numPersonas'],
                'vehiculos' => $vehs[$id] ?? [],
                'placas' => $placas,
            ];
        }

        return ['ok' => true, 'data' => ['items' => $filas]];
    }

    /** @param array<string, mixed> $body */
    public function guardar(array $body, string $usuario): array
    {
        $alc = $this->alcance($body, true);
        if (!$alc['ok']) {
            return $alc;
        }
        $granjas = self::listaGranjas($body['granjas'] ?? ($body['granja'] ?? ''));
        $fechas = self::fechas($body['fechas'] ?? ($body['fecha'] ?? ''), (string) ($body['desde'] ?? ''), (string) ($body['hasta'] ?? ''));
        $personas = $this->personasElegidas($alc['ruc'], $body);
        $vehs = $this->vehiculosElegidos($body);
        if ($granjas === []) {
            return self::error('validation', 'Granja requerida');
        }
        if ($fechas === []) {
            return self::error('validation', 'Fecha requerida');
        }
        if ($personas === []) {
            return self::error('validation', 'Personal requerido');
        }
        $motivo = self::cortar(trim((string) ($body['proceso'] ?? $body['motivo'] ?? $alc['proceso'])), 60);
        $obs = self::cortar(trim((string) ($body['observacion'] ?? $body['motivo'] ?? '')), 200);
        if ($motivo === '') {
            return self::error('validation', 'Proceso requerido');
        }
        $ids = [];
        try {
            foreach ($granjas as $g3) {
                $camp = $this->repo->campania($g3);
                if ($camp === '') {
                    return self::error('validation', 'La granja ' . $g3 . ' no tiene campaña vigente');
                }
                foreach ($fechas as $fecha) {
                    $ids[] = $this->repo->insertar([
                        'fecha' => $fecha,
                        'granja' => $g3,
                        'campania' => $camp,
                        'ruc' => $alc['ruc'],
                        'razon' => $alc['razon'],
                        'motivo' => $motivo,
                        'observacion' => $obs,
                        'usuario' => substr($usuario, 0, 25),
                    ], $personas, $vehs);
                }
            }
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo guardar la planificación.');
        }

        return ['ok' => true, 'data' => ['ids' => $ids, 'total' => count($ids)]];
    }

    /** @param array<string, mixed> $body */
    public function actualizar(int $id, array $body, string $usuario): array
    {
        $cab = $this->propia($id, $body);
        if (isset($cab['ok']) && $cab['ok'] === false) {
            return $cab;
        }
        if (substr((string) $cab['fecha'], 0, 10) < date('Y-m-d')) {
            return self::error('validation', 'La visita ya pasó');
        }
        $ruc = trim((string) $cab['proveedorRuc']);
        $personas = $this->personasElegidas($ruc, $body);
        if ($personas === []) {
            return self::error('validation', 'Personal requerido');
        }
        $motivo = self::cortar(trim((string) ($body['proceso'] ?? $body['motivo'] ?? $cab['motivo'])), 60);
        try {
            $this->repo->reemplazarDetalle($id, $motivo, $personas, $this->vehiculosElegidos($body));
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo actualizar la visita.');
        }
        unset($usuario);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $q */
    public function eliminar(int $id, array $q): array
    {
        $cab = $this->propia($id, $q);
        if (isset($cab['ok']) && $cab['ok'] === false) {
            return $cab;
        }
        try {
            $this->repo->eliminar($id);
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo eliminar la visita.');
        }

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /** @param array<string, mixed> $body */
    public function guardarPersonal(array $body, string $usuario): array
    {
        $alc = $this->alcance($body, true);
        if (!$alc['ok']) {
            return $alc;
        }
        $dnis = $body['dnis'] ?? ($body['dni'] ?? []);
        if (is_string($dnis)) {
            $dnis = preg_split('/[\s,;]+/', $dnis) ?: [];
        }
        $nombre = self::cortar(trim((string) ($body['nombre'] ?? '')), 60);
        $n = 0;
        try {
            foreach ((array) $dnis as $raw) {
                $dni = self::doc((string) $raw);
                if ($dni === '') {
                    continue;
                }
                $this->repo->guardarPersonal($alc['ruc'], $dni, $nombre, $usuario);
                $n++;
            }
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo guardar el personal.');
        }
        if ($n === 0) {
            return self::error('validation', 'DNI inválido');
        }

        return ['ok' => true, 'data' => ['personal' => $this->filasPersonal($alc['ruc'])]];
    }

    /** @param array<string, mixed> $body */
    public function bajaPersonal(array $body, string $usuario): array
    {
        $alc = $this->alcance($body, true);
        if (!$alc['ok']) {
            return $alc;
        }
        $dni = self::doc((string) ($body['dni'] ?? ''));
        if ($dni === '') {
            return self::error('validation', 'DNI inválido');
        }
        try {
            $this->repo->bajaPersonal($alc['ruc'], $dni, $usuario);
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo retirar el personal.');
        }

        return ['ok' => true, 'data' => ['personal' => $this->filasPersonal($alc['ruc'])]];
    }

    /** @param array<string, mixed> $body */
    public function guardarVehiculo(array $body, string $usuario): array
    {
        $alc = $this->alcance($body, true);
        if (!$alc['ok']) {
            return $alc;
        }
        $placas = $body['placas'] ?? ($body['placa'] ?? []);
        if (is_string($placas)) {
            $placas = preg_split('/[\s,;]+/', $placas) ?: [];
        }
        $desc = self::cortar(trim((string) ($body['descripcion'] ?? '')), 60);
        $n = 0;
        try {
            foreach ((array) $placas as $raw) {
                $placa = self::placa((string) $raw);
                if ($placa === '') {
                    continue;
                }
                $this->repo->guardarVehiculo($alc['ruc'], $placa, $desc, $usuario);
                $n++;
            }
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo guardar el vehículo.');
        }
        if ($n === 0) {
            return self::error('validation', 'Placa inválida');
        }

        return ['ok' => true, 'data' => ['vehiculos' => $this->filasVehiculo($alc['ruc'])]];
    }

    /** @param array<string, mixed> $body */
    public function bajaVehiculo(array $body, string $usuario): array
    {
        $alc = $this->alcance($body, true);
        if (!$alc['ok']) {
            return $alc;
        }
        $placa = self::placa((string) ($body['placa'] ?? ''));
        if ($placa === '') {
            return self::error('validation', 'Placa inválida');
        }
        try {
            $this->repo->bajaVehiculo($alc['ruc'], $placa, $usuario);
        } catch (Throwable $e) {
            return self::error('db', 'No se pudo retirar el vehículo.');
        }

        return ['ok' => true, 'data' => ['vehiculos' => $this->filasVehiculo($alc['ruc'])]];
    }

    /**
     * @param array<string, mixed> $src
     * @return array{ok: true, ruc: string, razon: string, proceso: string, empresa_id: int}|array{ok: false, error: array{code: string, message: string}}
     */
    private function alcance(array $src, bool $exigirRuc): array
    {
        if (!UserContext::isGrs()) {
            $eid = (int) UserContext::empresaId();
            $emp = $eid > 0 ? (new EmpresaRepository())->find($eid) : null;
            if ($emp === null) {
                return self::error('forbidden', 'No autorizado');
            }
            $ruc = BioseguridadAcceso::digitos((string) ($emp['ruc'] ?? ''));
            $prov = $this->repo->proveedorDeUsuario((string) ($_SESSION['usuario'] ?? ''));
            if (strlen($ruc) !== 11 && $prov !== null) {
                $ruc = $prov['ruc'];
            }
            if (strlen($ruc) !== 11) {
                return self::error('validation', 'La empresa no tiene RUC');
            }

            return [
                'ok' => true,
                'ruc' => $ruc,
                'razon' => self::cortar((string) ($emp['razon_social'] ?? ''), 120),
                'proceso' => $prov['proceso'] ?? '',
                'empresa_id' => $eid,
            ];
        }
        $eid = (int) ($src['empresa_id'] ?? 0);
        if ($eid <= 0) {
            if ($exigirRuc) {
                return self::error('validation', 'Proveedor requerido');
            }

            return ['ok' => true, 'ruc' => '', 'razon' => '', 'proceso' => '', 'empresa_id' => 0];
        }
        $emp = (new EmpresaRepository())->find($eid);
        if ($emp === null) {
            return self::error('validation', 'Proveedor requerido');
        }
        $ruc = BioseguridadAcceso::digitos((string) ($emp['ruc'] ?? ''));
        if (strlen($ruc) !== 11) {
            return self::error('validation', 'La empresa no tiene RUC');
        }

        return [
            'ok' => true,
            'ruc' => $ruc,
            'razon' => self::cortar((string) $emp['razon_social'], 120),
            'proceso' => '',
            'empresa_id' => $eid,
        ];
    }

    /** @param array<string, mixed> $src @return array<string, mixed> */
    private function propia(int $id, array $src): array
    {
        if ($id <= 0) {
            return self::error('validation', 'Visita requerida');
        }
        try {
            $cab = $this->repo->cabecera($id);
        } catch (Throwable $e) {
            return self::error('db', 'No está disponible la planificación del proveedor.');
        }
        if ($cab === null) {
            return self::error('not_found', 'La visita no existe');
        }
        $alc = $this->alcance($src, !UserContext::isGrs());
        if (!$alc['ok']) {
            return $alc;
        }
        if ($alc['ruc'] !== '' && trim((string) $cab['proveedorRuc']) !== $alc['ruc']) {
            return self::error('forbidden', 'No autorizado');
        }

        return $cab;
    }

    /** @return list<array{dni: string, nombre: string, activo: int}> */
    private function filasPersonal(string $ruc): array
    {
        $out = [];
        foreach ($this->repo->personal($ruc) as $r) {
            $out[] = [
                'dni' => (string) $r['dni'],
                'nombre' => (string) $r['nombre'],
                'activo' => (int) $r['activo'] === 1 ? 1 : 0,
            ];
        }

        return $out;
    }

    /** @return list<array{placa: string, descripcion: string, activo: int}> */
    private function filasVehiculo(string $ruc): array
    {
        $out = [];
        foreach ($this->repo->vehiculos($ruc) as $r) {
            $out[] = [
                'placa' => (string) $r['placa'],
                'descripcion' => (string) $r['descripcion'],
                'activo' => (int) $r['activo'] === 1 ? 1 : 0,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array{dni: string, nombre: string}>
     */
    private function personasElegidas(string $ruc, array $body): array
    {
        $raw = $body['dnis'] ?? ($body['personas'] ?? []);
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        $nombres = [];
        try {
            foreach ($this->repo->personal($ruc) as $p) {
                $nombres[(string) $p['dni']] = (string) $p['nombre'];
            }
        } catch (Throwable $e) {
            $nombres = [];
        }
        $out = [];
        foreach ((array) $raw as $item) {
            $dni = self::doc(is_array($item) ? (string) ($item['dni'] ?? '') : (string) $item);
            if ($dni === '' || isset($out[$dni])) {
                continue;
            }
            $nombre = is_array($item) ? trim((string) ($item['nombre'] ?? '')) : '';
            if ($nombre === '') {
                $nombre = $nombres[$dni] ?? '';
            }
            $out[$dni] = ['dni' => $dni, 'nombre' => self::cortar($nombre, 60)];
        }

        return array_values($out);
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array{placa: string, ingresa: int}>
     */
    private function vehiculosElegidos(array $body): array
    {
        $raw = $body['vehiculos'] ?? ($body['placas'] ?? []);
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ((array) $raw as $item) {
            $placa = self::placa(is_array($item) ? (string) ($item['placa'] ?? '') : (string) $item);
            if ($placa === '' || isset($out[$placa])) {
                continue;
            }
            $ingresa = 1;
            if (is_array($item) && (isset($item['ingresa']) && ((int) $item['ingresa'] === 0 || $item['ingresa'] === false))) {
                $ingresa = 0;
            }
            $out[$placa] = ['placa' => $placa, 'ingresa' => $ingresa];
        }

        return array_values($out);
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private function granjasLista(): array
    {
        $out = [];
        foreach ($this->granjas->listar() as $g) {
            $out[] = ['codigo' => $g['codigo'], 'nombre' => $g['nombre'], 'g3' => self::granja3($g['codigo'])];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function mapaGranjas(): array
    {
        $out = [];
        foreach ($this->granjasLista() as $g) {
            $out[$g['g3']] = $g['nombre'];
            $out[$g['codigo']] = $g['nombre'];
        }

        return $out;
    }

    /** @return list<array{id: int, codigo: string, razon_social: string, ruc: string}> */
    private function empresas(): array
    {
        $out = [];
        foreach ((new EmpresaRepository())->listar() as $e) {
            $out[] = [
                'id' => (int) $e['id'],
                'codigo' => (string) $e['codigo'],
                'razon_social' => (string) $e['razon_social'],
                'ruc' => BioseguridadAcceso::digitos((string) ($e['ruc'] ?? '')),
            ];
        }

        return $out;
    }

    /** @param mixed $raw @return list<string> */
    private static function listaGranjas($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ((array) $raw as $g) {
            $g3 = self::granja3((string) $g);
            if ($g3 !== '' && !in_array($g3, $out, true)) {
                $out[] = $g3;
            }
        }

        return $out;
    }

    /** @param mixed $raw @return list<string> */
    private static function fechas($raw, string $desde, string $hasta): array
    {
        $lista = [];
        if (is_string($raw) && $raw !== '') {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        foreach ((array) $raw as $f) {
            $ymd = self::fecha((string) $f);
            if ($ymd !== '') {
                $lista[] = $ymd;
            }
        }
        $a = self::fecha($desde);
        $b = self::fecha($hasta);
        if ($lista === [] && $a !== '' && $b !== '') {
            if ($a > $b) {
                $tmp = $a;
                $a = $b;
                $b = $tmp;
            }
            $cur = $a;
            while ($cur <= $b && count($lista) < 62) {
                $lista[] = $cur;
                $cur = date('Y-m-d', strtotime($cur . ' +1 day'));
            }
        }
        $hoy = date('Y-m-d');
        $tope = date('Y-m-d', strtotime('+120 days'));
        $out = [];
        foreach ($lista as $f) {
            if ($f >= $hoy && $f <= $tope && !in_array($f, $out, true)) {
                $out[] = $f;
            }
        }
        sort($out);

        return $out;
    }

    private static function granja3(string $cod): string
    {
        $d = preg_replace('/\D/', '', $cod) ?? '';
        if ($d === '') {
            return '';
        }
        if (strlen($d) > 3) {
            $d = substr($d, 0, 3);
        }

        return str_pad($d, 3, '0', STR_PAD_LEFT);
    }

    private static function doc(string $raw): string
    {
        $t = strtoupper(trim($raw));
        $dig = preg_replace('/\D/', '', $t) ?? '';
        if (preg_match('/^\d{8}$/', $dig) && preg_replace('/\s+/', '', $t) === $dig) {
            return $dig;
        }
        $cee = preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
        if (preg_match('/^\d{10}$/', $cee)) {
            $cee = substr($cee, 0, 9);
        }
        if ($cee === '' || strlen($cee) < 6 || strlen($cee) > 15 || preg_match('/^\d{8}$/', $cee)) {
            return '';
        }

        return $cee;
    }

    private static function placa(string $raw): string
    {
        $p = strtoupper((string) preg_replace('/\s+/', '', trim($raw)));
        if ($p === '' || !preg_match('/^[A-Z0-9]{5,8}$/', str_replace('-', '', $p)) || !preg_match('/\d/', $p)) {
            return '';
        }
        $sin = str_replace('-', '', $p);

        return strlen($sin) >= 5 && strlen($sin) <= 8 ? $p : '';
    }

    private static function fecha(string $raw): string
    {
        $f = trim($raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $f)) {
            return '';
        }
        $f = substr($f, 0, 10);
        $p = explode('-', $f);
        if (!checkdate((int) $p[1], (int) $p[2], (int) $p[0])) {
            return '';
        }

        return $f;
    }

    private static function cortar(string $s, int $n): string
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($s, 0, $n);
        }

        return substr($s, 0, $n);
    }

    /** @return array{ok: false, error: array{code: string, message: string}} */
    private static function error(string $code, string $message): array
    {
        return ['ok' => false, 'error' => ['code' => $code, 'message' => $message]];
    }
}
