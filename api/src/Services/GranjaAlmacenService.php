<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaAlmacenRepository;
use Bio\Api\Repositories\GranjaRepository;

/**
 * Kardex de granja. No lee ni escribe imov ni movi_zonas.
 * Depósitos: LIMPIO (Almacén limpio) y LAVADERO (Lavadero).
 * Ropa 1 tránsito, 2 proceso. El stock controlado es el de proceso.
 */
final class GranjaAlmacenService
{
    /** @var GranjaAlmacenRepository */
    private $repo;

    /** @var GranjaRepository */
    private $granjas;

    public function __construct()
    {
        $this->repo = new GranjaAlmacenRepository();
        $this->granjas = new GranjaRepository();
    }

    /** @param array<string, string> $q */
    public function contexto(array $q): array
    {
        $cod = trim((string) ($q['granja_cod'] ?? ''));
        $stock = $this->stock(['granja_cod' => $cod]);
        $kardex = $cod === '' ? ['data' => ['items' => []]] : $this->kardex([
            'granja_cod' => $cod,
            'clase' => 'todos',
        ]);
        $items = $kardex['data']['items'] ?? [];
        $n = count($items);

        return [
            'ok' => true,
            'data' => [
                'granja_cod' => $cod,
                'granjas' => $this->listaGranjas(),
                'ropa_tipos' => self::ropaTipos(),
                'almacenes' => self::almacenes(),
                'resumen' => $stock['data']['resumen'] ?? [],
                'uniformes' => $stock['data']['uniformes'] ?? [],
                'insumos' => $stock['data']['insumos'] ?? [],
                'movimientos' => $n > 40 ? array_slice($items, $n - 40) : $items,
            ],
        ];
    }

    /** @param array<string, string> $q */
    public function stock(array $q): array
    {
        $cod = trim((string) ($q['granja_cod'] ?? ''));
        $nombres = $this->mapaGranjas();
        $sumas = [];
        foreach ($this->repo->saldosUniforme($cod) as $row) {
            $key = (string) $row['granja_cod'] . '|' . (int) $row['ropa_tipo'] . '|' . (string) $row['almacen'];
            $sumas[$key] = round((float) $row['stock'], 2);
        }

        $uniformes = [];
        $resumen = [];
        $lista = $cod === '' ? $this->listaGranjas() : [['codigo' => $cod, 'nombre' => $nombres[$cod] ?? $cod]];
        foreach ($lista as $g) {
            $gc = (string) $g['codigo'];
            $gn = (string) $g['nombre'];
            $limpio = $sumas[$gc . '|2|LIMPIO'] ?? 0.0;
            $lavadero = $sumas[$gc . '|2|LAVADERO'] ?? 0.0;
            $resumen[] = [
                'granja_cod' => $gc,
                'granja_nombre' => $gn,
                'limpio' => $limpio,
                'lavadero' => $lavadero,
            ];
            $uniformes[] = $this->filaUniforme($gc, $gn, 2, 'LIMPIO', $limpio);
            $uniformes[] = $this->filaUniforme($gc, $gn, 2, 'LAVADERO', $lavadero);
            $tLimpio = $sumas[$gc . '|1|LIMPIO'] ?? null;
            $tLav = $sumas[$gc . '|1|LAVADERO'] ?? null;
            if ($tLimpio !== null && abs($tLimpio) > 0.001) {
                $uniformes[] = $this->filaUniforme($gc, $gn, 1, 'LIMPIO', $tLimpio);
            }
            if ($tLav !== null && abs($tLav) > 0.001) {
                $uniformes[] = $this->filaUniforme($gc, $gn, 1, 'LAVADERO', $tLav);
            }
        }

        $insumos = [];
        foreach ($this->repo->saldosInsumo($cod) as $row) {
            $gc = (string) $row['granja_cod'];
            $insumos[] = [
                'granja_cod' => $gc,
                'granja_nombre' => $nombres[$gc] ?? $gc,
                'insumo_id' => (int) $row['insumo_id'],
                'codigo' => (string) $row['codigo'],
                'nombre' => (string) $row['nombre'],
                'unidad' => (string) $row['unidad'],
                'categoria' => (string) $row['categoria'],
                'stock' => round((float) $row['stock'], 2),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'granja_cod' => $cod,
                'resumen' => $resumen,
                'uniformes' => $uniformes,
                'insumos' => $insumos,
            ],
        ];
    }

    /** @param array<string, string> $q */
    public function kardex(array $q): array
    {
        $cod = trim((string) ($q['granja_cod'] ?? ''));
        if ($cod === '') {
            return ['ok' => true, 'data' => ['items' => []]];
        }
        $nombre = $this->granjas->nombre($cod);
        $rows = [];
        foreach ($this->repo->movimientosUniforme($cod) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'clase' => 'uniforme',
                'granja_cod' => $cod,
                'granja_nombre' => $nombre !== '' ? $nombre : $cod,
                'fecha' => (string) $row['fecha'],
                'concepto' => (string) $row['concepto'],
                'almacen' => (string) $row['almacen'],
                'almacen_nombre' => self::nombreAlmacen((string) $row['almacen']),
                'tipo_mov' => (string) $row['tipo_mov'],
                'cantidad' => round((float) $row['cantidad'], 2),
                'ropa_tipo' => (int) $row['ropa_tipo'],
                'ropa_nombre' => self::nombreRopa((int) $row['ropa_tipo']),
                'insumo_id' => 0,
                'insumo_nombre' => '',
                'unidad' => 'und',
                'dni' => (string) $row['dni'],
                'trabajador' => (string) $row['trabajador'],
                'observacion' => (string) $row['observacion'],
                'usuario_registro' => (string) $row['usuario_registro'],
                'par_id' => $row['par_id'] !== null ? (int) $row['par_id'] : 0,
                '_orden' => (int) $row['id'],
            ];
        }
        foreach ($this->repo->movimientosInsumo($cod) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'clase' => 'insumo',
                'granja_cod' => $cod,
                'granja_nombre' => $nombre !== '' ? $nombre : $cod,
                'fecha' => (string) $row['fecha'],
                'concepto' => (string) $row['concepto'],
                'almacen' => '',
                'almacen_nombre' => '',
                'tipo_mov' => (string) $row['tipo_mov'],
                'cantidad' => round((float) $row['cantidad'], 2),
                'ropa_tipo' => 0,
                'ropa_nombre' => '',
                'insumo_id' => (int) $row['insumo_id'],
                'insumo_nombre' => (string) $row['nombre'],
                'unidad' => (string) $row['unidad'],
                'dni' => '',
                'trabajador' => '',
                'observacion' => (string) $row['observacion'],
                'usuario_registro' => (string) $row['usuario_registro'],
                'par_id' => 0,
                '_orden' => (int) $row['id'],
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            $c = strcmp((string) $a['fecha'], (string) $b['fecha']);
            if ($c !== 0) {
                return $c;
            }
            if ($a['clase'] !== $b['clase']) {
                return $a['clase'] === 'uniforme' ? -1 : 1;
            }

            return $a['_orden'] - $b['_orden'];
        });
        $rows = $this->conStock($rows);
        $filtradas = $this->filtrar(
            $rows,
            self::fecha($q['desde'] ?? ''),
            self::fecha($q['hasta'] ?? ''),
            strtoupper(trim((string) ($q['almacen'] ?? ''))),
            strtolower(trim((string) ($q['clase'] ?? 'todos')))
        );

        return ['ok' => true, 'data' => ['items' => $filtradas]];
    }

    /** @param array<string, mixed> $body */
    public function registrar(array $body, string $usuario): array
    {
        $op = strtoupper(trim((string) ($body['operacion'] ?? '')));
        $granja = trim((string) ($body['granja_cod'] ?? ''));
        $fecha = self::fecha($body['fecha'] ?? '');
        $cantidad = round((float) ($body['cantidad'] ?? 0), 2);
        $ropa = (int) ($body['ropa_tipo'] ?? 2);
        if ($ropa === 0) {
            $ropa = 2;
        }
        $almacen = strtoupper(trim((string) ($body['almacen'] ?? 'LIMPIO')));
        $obs = self::cortar(trim((string) ($body['observacion'] ?? '')), 255);
        $usuario = self::cortar(trim($usuario), 30);

        if ($granja === '') {
            return self::error('validation', 'Granja requerida');
        }
        if (!$this->granjas->existe($granja)) {
            return self::error('validation', 'Granja no existe');
        }
        if ($fecha === '') {
            return self::error('validation', 'Fecha inválida');
        }
        if ($cantidad <= 0) {
            return self::error('validation', 'Cantidad inválida');
        }
        if ($ropa !== 1 && $ropa !== 2) {
            return self::error('validation', 'Tipo de ropa inválido');
        }

        $persona = $this->persona($body);
        $baseU = [
            'granja_cod' => $granja,
            'ropa_tipo' => $ropa,
            'cantidad' => $cantidad,
            'personal_id' => $persona['id'],
            'dni' => $persona['dni'],
            'trabajador' => $persona['nombre'],
            'fecha' => $fecha,
            'observacion' => $obs,
            'usuario_registro' => $usuario,
            'par_id' => null,
        ];

        if ($op === 'SI_UNIFORME' || $op === 'CARGA_UNIFORME') {
            if ($almacen !== 'LIMPIO' && $almacen !== 'LAVADERO') {
                return self::error('validation', 'Almacén inválido');
            }
            $id = $this->repo->insertarUniforme($baseU + [
                'almacen' => $almacen,
                'tipo_mov' => $op === 'SI_UNIFORME' ? 'SI' : 'E',
                'concepto' => 'CARGA',
            ]);

            return $this->okMov($id, 0, $granja, $ropa, 0);
        }

        if ($op === 'ENTREGA') {
            if ($this->repo->saldoUniforme($granja, $ropa, 'LIMPIO') + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $id = $this->repo->insertarUniforme($baseU + [
                'almacen' => 'LIMPIO',
                'tipo_mov' => 'S',
                'concepto' => 'ENTREGA',
            ]);

            return $this->okMov($id, 0, $granja, $ropa, 0);
        }

        if ($op === 'RECEPCION') {
            $id = $this->repo->insertarUniforme($baseU + [
                'almacen' => 'LAVADERO',
                'tipo_mov' => 'E',
                'concepto' => 'RECEPCION',
            ]);

            return $this->okMov($id, 0, $granja, $ropa, 0);
        }

        if ($op === 'LAVADO') {
            if ($this->repo->saldoUniforme($granja, $ropa, 'LAVADERO') + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $sale = $this->repo->insertarUniforme($baseU + [
                'almacen' => 'LAVADERO',
                'tipo_mov' => 'S',
                'concepto' => 'LAVADO',
            ]);
            $entra = $this->repo->insertarUniforme($baseU + [
                'almacen' => 'LIMPIO',
                'tipo_mov' => 'E',
                'concepto' => 'LAVADO',
            ]);
            $this->repo->marcarPar($sale, $entra);

            return $this->okMov($sale, $entra, $granja, $ropa, 0);
        }

        if ($op === 'SI_INSUMO' || $op === 'CARGA_INSUMO' || $op === 'CONSUMO') {
            $insumoId = (int) ($body['insumo_id'] ?? 0);
            if ($insumoId <= 0 || !$this->repo->insumoExiste($insumoId)) {
                return self::error('validation', 'Insumo requerido');
            }
            if ($op === 'CONSUMO' && $this->repo->saldoInsumo($granja, $insumoId) + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $id = $this->repo->insertarInsumo([
                'granja_cod' => $granja,
                'insumo_id' => $insumoId,
                'tipo_mov' => $op === 'SI_INSUMO' ? 'SI' : ($op === 'CONSUMO' ? 'S' : 'E'),
                'cantidad' => $cantidad,
                'concepto' => $op === 'CONSUMO' ? 'CONSUMO' : 'CARGA',
                'fecha' => $fecha,
                'observacion' => $obs,
                'usuario_registro' => $usuario,
            ]);

            return $this->okMov($id, 0, $granja, $ropa, $insumoId);
        }

        return self::error('validation', 'Operación inválida');
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int|null, dni: string, nombre: string}
     */
    private function persona(array $body): array
    {
        $id = (int) ($body['personal_id'] ?? 0);
        $dni = self::cortar(trim((string) ($body['dni'] ?? '')), 15);
        $nombre = self::cortar(trim((string) ($body['trabajador'] ?? '')), 120);
        $hallado = $this->repo->personal($id, $dni);
        if ($hallado !== null) {
            return [
                'id' => $hallado['id'],
                'dni' => $hallado['dni'],
                'nombre' => $hallado['nombre'] !== '' ? $hallado['nombre'] : $nombre,
            ];
        }

        return ['id' => $id > 0 ? $id : null, 'dni' => $dni, 'nombre' => $nombre];
    }

    /** @return array{ok: true, data: array<string, mixed>} */
    private function okMov(int $id, int $par, string $granja, int $ropa, int $insumoId): array
    {
        return [
            'ok' => true,
            'data' => [
                'id' => $id,
                'par_id' => $par,
                'limpio' => $this->repo->saldoUniforme($granja, $ropa, 'LIMPIO'),
                'lavadero' => $this->repo->saldoUniforme($granja, $ropa, 'LAVADERO'),
                'insumo_id' => $insumoId,
                'insumo_stock' => $insumoId > 0 ? $this->repo->saldoInsumo($granja, $insumoId) : 0,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function conStock(array $rows): array
    {
        $run = [];
        $out = [];
        foreach ($rows as $row) {
            $key = $row['clase'] === 'uniforme'
                ? 'U|' . $row['ropa_tipo'] . '|' . $row['almacen']
                : 'I|' . $row['insumo_id'];
            if (!isset($run[$key])) {
                $run[$key] = 0.0;
            }
            $sign = $row['tipo_mov'] === 'S' ? -1 : 1;
            $run[$key] = round($run[$key] + $sign * (float) $row['cantidad'], 2);
            $row['stock'] = $run[$key];
            unset($row['_orden']);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filtrar(array $rows, string $desde, string $hasta, string $almacen, string $clase): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($clase !== '' && $clase !== 'todos' && $row['clase'] !== $clase) {
                continue;
            }
            if ($almacen !== '' && ($row['clase'] !== 'uniforme' || $row['almacen'] !== $almacen)) {
                continue;
            }
            if ($desde !== '' && (string) $row['fecha'] < $desde) {
                continue;
            }
            if ($hasta !== '' && (string) $row['fecha'] > $hasta) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function filaUniforme(string $granja, string $nombre, int $ropa, string $almacen, float $stock): array
    {
        return [
            'granja_cod' => $granja,
            'granja_nombre' => $nombre,
            'ropa_tipo' => $ropa,
            'ropa_nombre' => self::nombreRopa($ropa),
            'almacen' => $almacen,
            'almacen_nombre' => self::nombreAlmacen($almacen),
            'stock' => round($stock, 2),
        ];
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private function listaGranjas(): array
    {
        $out = [];
        foreach ($this->granjas->listar() as $g) {
            $out[] = ['codigo' => $g['codigo'], 'nombre' => $g['nombre']];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function mapaGranjas(): array
    {
        $out = [];
        foreach ($this->listaGranjas() as $g) {
            $out[$g['codigo']] = $g['nombre'];
        }

        return $out;
    }

    /** @return list<array{codigo: int, nombre: string}> */
    private static function ropaTipos(): array
    {
        return [
            ['codigo' => 1, 'nombre' => 'Ropa de tránsito'],
            ['codigo' => 2, 'nombre' => 'Ropa para el proceso'],
        ];
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private static function almacenes(): array
    {
        return [
            ['codigo' => 'LIMPIO', 'nombre' => 'Almacén limpio'],
            ['codigo' => 'LAVADERO', 'nombre' => 'Lavadero'],
        ];
    }

    private static function nombreRopa(int $tipo): string
    {
        return $tipo === 1 ? 'Ropa de tránsito' : 'Ropa para el proceso';
    }

    private static function nombreAlmacen(string $cod): string
    {
        return $cod === 'LAVADERO' ? 'Lavadero' : 'Almacén limpio';
    }

    /** @param mixed $valor */
    private static function fecha($valor): string
    {
        $f = trim((string) $valor);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return '';
        }
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
