<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaAlmacenRepository;
use Bio\Api\Repositories\GranjaRepository;

/**
 * Kardex de granja en bio_almacen_mov. No lee ni escribe imov ni movi_zonas.
 * frente: LIMPIO, LAVADERO, INSUMOS. tipo: SI, E, S.
 * item: ropa 1 tránsito o 2 proceso, o id de insumo.
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
        $kardex = $cod === '' ? ['data' => ['items' => []]] : $this->kardex(['granja_cod' => $cod]);
        $items = $kardex['data']['items'] ?? [];
        $n = count($items);

        return [
            'ok' => true,
            'data' => [
                'granja_cod' => $cod,
                'granjas' => $this->listaGranjas(),
                'ropa_tipos' => self::ropaTipos(),
                'frentes' => self::frentes(),
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
        $insumoRows = [];
        foreach ($this->repo->saldos($cod) as $row) {
            $key = (string) $row['granja_cod'] . '|' . (string) $row['frente'] . '|' . (string) $row['item'];
            $sumas[$key] = round((float) $row['stock'], 2);
            if ((string) $row['frente'] === 'INSUMOS') {
                $insumoRows[] = $row;
            }
        }

        $uniformes = [];
        $resumen = [];
        $lista = $cod === '' ? $this->listaGranjas() : [['codigo' => $cod, 'nombre' => $nombres[$cod] ?? $cod]];
        foreach ($lista as $g) {
            $gc = (string) $g['codigo'];
            $gn = (string) $g['nombre'];
            $limpio = $sumas[$gc . '|LIMPIO|2'] ?? 0.0;
            $lavadero = $sumas[$gc . '|LAVADERO|2'] ?? 0.0;
            $resumen[] = [
                'granja_cod' => $gc,
                'granja_nombre' => $gn,
                'limpio' => $limpio,
                'lavadero' => $lavadero,
            ];
            $uniformes[] = $this->filaUniforme($gc, $gn, 2, 'LIMPIO', $limpio);
            $uniformes[] = $this->filaUniforme($gc, $gn, 2, 'LAVADERO', $lavadero);
            foreach (['LIMPIO', 'LAVADERO'] as $frente) {
                $clave = $gc . '|' . $frente . '|1';
                if (isset($sumas[$clave]) && abs($sumas[$clave]) > 0.001) {
                    $uniformes[] = $this->filaUniforme($gc, $gn, 1, $frente, $sumas[$clave]);
                }
            }
        }

        $insumos = [];
        foreach ($insumoRows as $row) {
            $gc = (string) $row['granja_cod'];
            $insumos[] = [
                'granja_cod' => $gc,
                'granja_nombre' => $nombres[$gc] ?? $gc,
                'insumo_id' => (int) $row['item'],
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
        foreach ($this->repo->movimientos($cod) as $row) {
            $frente = (string) $row['frente'];
            $item = (string) $row['item'];
            $esInsumo = $frente === 'INSUMOS';
            $rows[] = [
                'id' => (int) $row['id'],
                'frente' => $frente,
                'tipo' => (string) $row['tipo'],
                'granja_cod' => $cod,
                'granja_nombre' => $nombre !== '' ? $nombre : $cod,
                'item' => $item,
                'item_nombre' => $esInsumo ? (string) $row['insumo_nombre'] : self::nombreRopa((int) $item),
                'operacion' => (string) $row['operacion'],
                'cantidad' => round((float) $row['cantidad'], 2),
                'par_id' => $row['par_id'] !== null ? (int) $row['par_id'] : 0,
                'personal_id' => $row['personal_id'] !== null ? (int) $row['personal_id'] : 0,
                'dni' => (string) $row['dni'],
                'trabajador' => (string) $row['trabajador'],
                'usuario_registro' => (string) $row['usuario_registro'],
                'origen' => (string) $row['origen'],
                'fecha' => (string) $row['fecha'],
                'observacion' => (string) $row['observacion'],
                'unidad' => $esInsumo ? (string) $row['unidad'] : 'und',
            ];
        }
        $rows = $this->conStock($rows);
        $frente = strtoupper(trim((string) ($q['frente'] ?? ($q['almacen'] ?? ''))));
        $filtradas = $this->filtrar(
            $rows,
            self::fecha($q['desde'] ?? ''),
            self::fecha($q['hasta'] ?? ''),
            $frente
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
        $obs = self::cortar(trim((string) ($body['observacion'] ?? '')), 255);
        $usuario = self::cortar(trim($usuario), 30);
        $origen = self::origenDe($body);

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

        $persona = $this->persona($body);
        $esInsumo = $op === 'SI_INSUMO' || $op === 'CARGA_INSUMO' || $op === 'CONSUMO';
        $item = trim((string) ($body['item'] ?? ''));
        if ($item === '') {
            $item = $esInsumo ? (string) (int) ($body['insumo_id'] ?? 0) : (string) (int) ($body['ropa_tipo'] ?? 2);
        }
        if ($item === '0' || $item === '') {
            $item = $esInsumo ? '' : '2';
        }

        $base = [
            'granja_cod' => $granja,
            'item' => $item,
            'cantidad' => $cantidad,
            'operacion' => $op,
            'par_id' => null,
            'personal_id' => $persona['id'],
            'dni' => $persona['dni'],
            'usuario_registro' => $usuario,
            'origen' => $origen,
            'fecha' => $fecha,
            'observacion' => $obs,
        ];

        if ($op === 'SI_UNIFORME' || $op === 'CARGA_UNIFORME' || $op === 'ENTREGA' || $op === 'RECEPCION' || $op === 'LAVADO') {
            if ($item !== '1' && $item !== '2') {
                return self::error('validation', 'Tipo de ropa inválido');
            }
            $frente = strtoupper(trim((string) ($body['frente'] ?? ($body['almacen'] ?? 'LIMPIO'))));
            if ($op === 'ENTREGA') {
                $frente = 'LIMPIO';
            }
            if ($op === 'RECEPCION') {
                $frente = 'LAVADERO';
            }
            if ($op !== 'LAVADO' && $frente !== 'LIMPIO' && $frente !== 'LAVADERO') {
                return self::error('validation', 'Frente inválido');
            }
            $tipo = 'E';
            if ($op === 'SI_UNIFORME') {
                $tipo = 'SI';
            } elseif ($op === 'ENTREGA') {
                $tipo = 'S';
            }
            if ($op === 'ENTREGA' && $this->repo->saldo($granja, 'LIMPIO', $item) + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            if ($op === 'LAVADO') {
                if ($this->repo->saldo($granja, 'LAVADERO', $item) + 0.001 < $cantidad) {
                    return self::error('stock', 'Stock insuficiente');
                }
                $sale = $this->repo->insertar($base + ['frente' => 'LAVADERO', 'tipo' => 'S']);
                $entra = $this->repo->insertar($base + ['frente' => 'LIMPIO', 'tipo' => 'E']);
                $this->repo->marcarPar($sale, $entra);

                return $this->okMov($sale, $entra, $granja, $item, 0);
            }
            $id = $this->repo->insertar($base + ['frente' => $frente, 'tipo' => $tipo]);

            return $this->okMov($id, 0, $granja, $item, 0);
        }

        if ($esInsumo) {
            $insumoId = (int) $item;
            if ($insumoId <= 0 || !$this->repo->insumoExiste($insumoId)) {
                return self::error('validation', 'Insumo requerido');
            }
            $item = (string) $insumoId;
            $base['item'] = $item;
            $tipo = $op === 'SI_INSUMO' ? 'SI' : ($op === 'CONSUMO' ? 'S' : 'E');
            if ($op === 'CONSUMO' && $this->repo->saldo($granja, 'INSUMOS', $item) + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $id = $this->repo->insertar($base + ['frente' => 'INSUMOS', 'tipo' => $tipo]);

            return $this->okMov($id, 0, $granja, '2', $insumoId);
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
        $hallado = $this->repo->personal($id, $dni);
        if ($hallado !== null) {
            return ['id' => $hallado['id'], 'dni' => $hallado['dni'], 'nombre' => $hallado['nombre']];
        }

        return ['id' => $id > 0 ? $id : null, 'dni' => $dni, 'nombre' => ''];
    }

    /** @return array{ok: true, data: array<string, mixed>} */
    private function okMov(int $id, int $par, string $granja, string $item, int $insumoId): array
    {
        return [
            'ok' => true,
            'data' => [
                'id' => $id,
                'par_id' => $par,
                'limpio' => $this->repo->saldo($granja, 'LIMPIO', $item),
                'lavadero' => $this->repo->saldo($granja, 'LAVADERO', $item),
                'insumo_id' => $insumoId,
                'insumo_stock' => $insumoId > 0 ? $this->repo->saldo($granja, 'INSUMOS', (string) $insumoId) : 0,
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
            $key = $row['frente'] . '|' . $row['item'];
            if (!isset($run[$key])) {
                $run[$key] = 0.0;
            }
            $sign = $row['tipo'] === 'S' ? -1 : 1;
            $run[$key] = round($run[$key] + $sign * (float) $row['cantidad'], 2);
            $row['stock'] = $run[$key];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filtrar(array $rows, string $desde, string $hasta, string $frente): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($frente !== '' && $row['frente'] !== $frente) {
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
    private function filaUniforme(string $granja, string $nombre, int $ropa, string $frente, float $stock): array
    {
        return [
            'granja_cod' => $granja,
            'granja_nombre' => $nombre,
            'ropa_tipo' => $ropa,
            'ropa_nombre' => self::nombreRopa($ropa),
            'frente' => $frente,
            'item' => (string) $ropa,
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
    private static function frentes(): array
    {
        return [
            ['codigo' => 'LIMPIO', 'nombre' => 'Almacén limpio'],
            ['codigo' => 'LAVADERO', 'nombre' => 'Lavadero'],
            ['codigo' => 'INSUMOS', 'nombre' => 'Insumos'],
        ];
    }

    private static function nombreRopa(int $tipo): string
    {
        return $tipo === 1 ? 'Ropa de tránsito' : 'Ropa para el proceso';
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

    /** @param array<string, mixed> $body */
    private static function origenDe(array $body): string
    {
        $o = strtolower(trim((string) ($body['origen'] ?? '')));
        if ($o === 'web' || $o === 'app') {
            return $o;
        }
        $auth = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION']) && is_string($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (strncmp($auth, 'Bearer ', 7) === 0) {
            return 'app';
        }

        return 'web';
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
