<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\GranjaAlmacenRepository;
use Bio\Api\Repositories\GranjaRepository;
use Bio\Api\Support\UserContext;

/**
 * Kardex en bio_almacen_mov. No usa imov ni movi_zonas.
 * Categorías: UNIFORME (LIMPIO/LAVADERO), IMPLEMENTO, INSUMO.
 * GRS: ENVIO y SI. Empresa: CONSUMO, ENTREGA, DEVOLUCION, LAVADO, RELEVO.
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
        if (!($stock['ok'] ?? false)) {
            return $stock;
        }
        $kardex = $cod === '' ? ['ok' => true, 'data' => ['items' => []]] : $this->kardex(['granja_cod' => $cod]);
        if (!($kardex['ok'] ?? false)) {
            return $kardex;
        }
        $items = $kardex['data']['items'] ?? [];
        $n = count($items);

        return [
            'ok' => true,
            'data' => [
                'rol' => self::rol(),
                'acciones' => self::acciones(),
                'granja_cod' => $cod,
                'granjas' => $stock['data']['granjas'] ?? [],
                'ropa_tipos' => self::ropaTipos(),
                'categorias' => self::categorias(),
                'frentes' => self::frentes(),
                'resumen' => $stock['data']['resumen'] ?? [],
                'uniformes' => $stock['data']['uniformes'] ?? [],
                'implementos' => $stock['data']['implementos'] ?? [],
                'insumos' => $stock['data']['insumos'] ?? [],
                'movimientos' => $n > 40 ? array_slice($items, $n - 40) : $items,
            ],
        ];
    }

    /** @param array<string, string> $q */
    public function stock(array $q): array
    {
        $cod = trim((string) ($q['granja_cod'] ?? ''));
        $visibles = $this->granjasVisibles();
        if ($cod !== '' && !$this->puedeGranja($cod, $visibles)) {
            return self::error('forbidden', 'No autorizado');
        }
        $nombres = [];
        foreach ($visibles as $g) {
            $nombres[$g['codigo']] = $g['nombre'];
        }
        $lista = $cod === '' ? $visibles : [['codigo' => $cod, 'nombre' => $nombres[$cod] ?? ($this->granjas->nombre($cod) ?: $cod)]];
        $permitidos = [];
        foreach ($lista as $g) {
            $permitidos[(string) $g['codigo']] = true;
        }

        $sumas = [];
        $implementos = [];
        $insumos = [];
        foreach ($this->repo->saldos($cod) as $row) {
            $gc = (string) $row['granja_cod'];
            if (!isset($permitidos[$gc])) {
                continue;
            }
            $frente = (string) $row['frente'];
            $item = (string) $row['item'];
            $stock = round((float) $row['stock'], 2);
            $sumas[$gc . '|' . $frente . '|' . $item] = $stock;
            if ($frente === 'IMPLEMENTOS' || $frente === 'INSUMOS') {
                $fila = [
                    'granja_cod' => $gc,
                    'granja_nombre' => $nombres[$gc] ?? $gc,
                    'insumo_id' => (int) $item,
                    'codigo' => (string) $row['codigo'],
                    'nombre' => (string) $row['nombre'],
                    'unidad' => (string) $row['unidad'],
                    'categoria' => (string) $row['categoria'],
                    'stock' => $stock,
                ];
                if ($frente === 'IMPLEMENTOS') {
                    $implementos[] = $fila;
                } else {
                    $insumos[] = $fila;
                }
            }
        }

        $uniformes = [];
        $resumen = [];
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

        return [
            'ok' => true,
            'data' => [
                'rol' => self::rol(),
                'acciones' => self::acciones(),
                'granja_cod' => $cod,
                'granjas' => $visibles,
                'resumen' => $resumen,
                'uniformes' => $uniformes,
                'implementos' => $implementos,
                'insumos' => $insumos,
            ],
        ];
    }

    /** @param array<string, string> $q */
    public function kardex(array $q): array
    {
        $cod = trim((string) ($q['granja_cod'] ?? ''));
        $visibles = $this->granjasVisibles();
        if ($cod !== '' && !$this->puedeGranja($cod, $visibles)) {
            return self::error('forbidden', 'No autorizado');
        }
        $nombres = [];
        $codigos = [];
        foreach ($visibles as $g) {
            $nombres[$g['codigo']] = $g['nombre'];
            $codigos[] = $g['codigo'];
        }
        $restringir = UserContext::isGrs() ? null : $codigos;
        $rows = [];
        foreach ($this->repo->movimientos($cod, $restringir) as $row) {
            $frente = (string) $row['frente'];
            $item = (string) $row['item'];
            $gc = (string) $row['granja_cod'];
            $esRopa = $frente === 'LIMPIO' || $frente === 'LAVADERO';
            $rows[] = [
                'id' => (int) $row['id'],
                'frente' => $frente,
                'categoria' => self::categoriaDeFrente($frente),
                'tipo' => (string) $row['tipo'],
                'granja_cod' => $gc,
                'granja_nombre' => $nombres[$gc] ?? ($this->granjas->nombre($gc) ?: $gc),
                'item' => $item,
                'item_nombre' => $esRopa ? self::nombreRopa((int) $item) : (string) $row['insumo_nombre'],
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
                'unidad' => $esRopa ? 'und' : (string) $row['unidad'],
            ];
        }
        $rows = $this->conStock($rows);
        $filtradas = $this->filtrar(
            $rows,
            self::fecha($q['desde'] ?? ''),
            self::fecha($q['hasta'] ?? ''),
            strtoupper(trim((string) ($q['frente'] ?? ''))),
            strtoupper(trim((string) ($q['categoria'] ?? '')))
        );

        return ['ok' => true, 'data' => ['items' => $filtradas, 'granjas' => $visibles, 'rol' => self::rol()]];
    }

    /** @param array<string, mixed> $body */
    public function registrar(array $body, string $usuario): array
    {
        $op = strtoupper(trim((string) ($body['operacion'] ?? '')));
        $conocidas = ['ENVIO', 'SI', 'CONSUMO', 'ENTREGA', 'DEVOLUCION', 'LAVADO', 'RELEVO'];
        if (!in_array($op, $conocidas, true)) {
            return self::error('validation', 'Operación inválida');
        }
        $grs = UserContext::isGrs();
        if ($grs && $op !== 'ENVIO' && $op !== 'SI') {
            return self::error('forbidden', 'No autorizado');
        }
        if (!$grs && ($op === 'ENVIO' || $op === 'SI')) {
            return self::error('forbidden', 'No autorizado');
        }

        $granja = trim((string) ($body['granja_cod'] ?? ''));
        $fecha = self::fecha($body['fecha'] ?? '');
        $obs = self::cortar(trim((string) ($body['observacion'] ?? '')), 255);
        $usuario = self::cortar(trim($usuario), 30);
        $origen = self::origenDe($body);
        $visibles = $this->granjasVisibles();

        if ($granja === '' || !$this->puedeGranja($granja, $visibles)) {
            return $granja === '' ? self::error('validation', 'Granja requerida') : self::error('forbidden', 'No autorizado');
        }
        if (!$this->granjas->existe($granja)) {
            return self::error('validation', 'Granja no existe');
        }
        if ($fecha === '') {
            return self::error('validation', 'Fecha inválida');
        }

        $cat = strtoupper(trim((string) ($body['categoria'] ?? '')));
        if ($op === 'CONSUMO') {
            $cat = 'INSUMO';
        } elseif ($op === 'LAVADO' || $op === 'RELEVO') {
            $cat = 'UNIFORME';
        }
        if (!in_array($cat, ['UNIFORME', 'IMPLEMENTO', 'INSUMO'], true)) {
            return self::error('validation', 'Categoría inválida');
        }
        if (($op === 'ENTREGA' || $op === 'DEVOLUCION') && $cat === 'INSUMO') {
            return self::error('validation', 'Categoría inválida');
        }
        if (($op === 'ENVIO' || $op === 'SI') && $cat === 'UNIFORME') {
            // item de ropa
        }

        $cantidad = round((float) ($body['cantidad'] ?? ($op === 'RELEVO' ? 1 : 0)), 2);
        if ($op !== 'RELEVO' && $cantidad <= 0) {
            return self::error('validation', 'Cantidad inválida');
        }

        $item = trim((string) ($body['item'] ?? ''));
        if ($cat === 'UNIFORME') {
            if ($item === '' || $item === '0') {
                $item = (string) (int) ($body['ropa_tipo'] ?? 2);
            }
            if ($item === '0') {
                $item = '2';
            }
            if ($item !== '1' && $item !== '2') {
                return self::error('validation', 'Tipo de ropa inválido');
            }
        } else {
            if ($item === '') {
                $item = (string) (int) ($body['insumo_id'] ?? 0);
            }
            $clase = $this->repo->claseInsumo((int) $item);
            if ($clase === '' || $clase !== $cat) {
                return self::error('validation', $cat === 'IMPLEMENTO' ? 'Implemento requerido' : 'Insumo requerido');
            }
            $item = (string) (int) $item;
        }

        $eid = $grs ? 0 : (int) UserContext::empresaId();
        $base = [
            'granja_cod' => $granja,
            'cantidad' => $cantidad,
            'operacion' => $op,
            'par_id' => null,
            'personal_id' => null,
            'dni' => '',
            'usuario_registro' => $usuario,
            'origen' => $origen,
            'fecha' => $fecha,
            'observacion' => $obs,
        ];

        if ($op === 'RELEVO') {
            return $this->relevo($body, $base, $granja, $eid);
        }

        $persona = ['id' => null, 'dni' => ''];
        if ($op === 'ENTREGA' || $op === 'DEVOLUCION') {
            $persona = $this->exigirPersona($body, $granja, $eid, 'personal_id', 'dni');
            if (isset($persona['error'])) {
                return $persona;
            }
        }
        $base['personal_id'] = $persona['id'];
        $base['dni'] = $persona['dni'];
        $base['item'] = $item;

        if ($op === 'ENVIO' || $op === 'SI') {
            $frente = $cat === 'UNIFORME' ? 'LIMPIO' : ($cat === 'IMPLEMENTO' ? 'IMPLEMENTOS' : 'INSUMOS');
            $id = $this->repo->insertar(array_merge($base, ['frente' => $frente, 'tipo' => $op === 'SI' ? 'SI' : 'E']));

            return $this->okMov($id, 0, $granja, $cat === 'UNIFORME' ? $item : '2');
        }

        if ($op === 'CONSUMO') {
            if ($this->repo->saldo($granja, 'INSUMOS', $item) + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $id = $this->repo->insertar(array_merge($base, ['frente' => 'INSUMOS', 'tipo' => 'S']));

            return $this->okMov($id, 0, $granja, '2');
        }

        if ($op === 'ENTREGA') {
            $frente = $cat === 'UNIFORME' ? 'LIMPIO' : 'IMPLEMENTOS';
            if ($this->repo->saldo($granja, $frente, $item) + 0.001 < $cantidad) {
                return self::error('stock', 'Stock insuficiente');
            }
            $id = $this->repo->insertar(array_merge($base, ['frente' => $frente, 'tipo' => 'S']));

            return $this->okMov($id, 0, $granja, $cat === 'UNIFORME' ? $item : '2');
        }

        if ($op === 'DEVOLUCION') {
            $frente = $cat === 'UNIFORME' ? 'LAVADERO' : 'IMPLEMENTOS';
            $id = $this->repo->insertar(array_merge($base, ['frente' => $frente, 'tipo' => 'E']));

            return $this->okMov($id, 0, $granja, $cat === 'UNIFORME' ? $item : '2');
        }

        if ($this->repo->saldo($granja, 'LAVADERO', $item) + 0.001 < $cantidad) {
            return self::error('stock', 'Stock insuficiente');
        }
        $sale = $this->repo->insertar(array_merge($base, ['frente' => 'LAVADERO', 'tipo' => 'S', 'item' => $item]));
        $this->repo->fijarPar($sale);
        $this->repo->insertar(array_merge($base, ['frente' => 'LIMPIO', 'tipo' => 'E', 'item' => $item, 'par_id' => $sale]));

        return $this->okMov($sale, $sale, $granja, $item);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function relevo(array $body, array $base, string $granja, int $empresaId): array
    {
        $sale = $this->exigirPersona($body, $granja, $empresaId, 'personal_id_sale', 'dni_sale');
        if (isset($sale['error'])) {
            return $sale;
        }
        $entra = $this->exigirPersona($body, $granja, $empresaId, 'personal_id_entra', 'dni_entra');
        if (isset($entra['error'])) {
            return $entra;
        }
        if ((int) $sale['id'] === (int) $entra['id']) {
            return self::error('validation', 'Personal inválido');
        }
        $item = '2';
        if ($this->repo->saldo($granja, 'LIMPIO', $item) + 0.001 < 1) {
            return self::error('stock', 'Stock insuficiente');
        }

        $filas = [];
        $filas[] = array_merge($base, [
            'frente' => 'LAVADERO',
            'tipo' => 'E',
            'item' => $item,
            'cantidad' => 1,
            'personal_id' => $sale['id'],
            'dni' => $sale['dni'],
        ]);
        $filas[] = array_merge($base, [
            'frente' => 'LIMPIO',
            'tipo' => 'S',
            'item' => $item,
            'cantidad' => 1,
            'personal_id' => $entra['id'],
            'dni' => $entra['dni'],
        ]);
        foreach ($this->repo->saldos($granja) as $row) {
            if ((string) $row['frente'] !== 'IMPLEMENTOS') {
                continue;
            }
            $stock = round((float) $row['stock'], 2);
            if ($stock + 0.001 < 1) {
                continue;
            }
            $imp = (string) (int) $row['item'];
            $filas[] = array_merge($base, [
                'frente' => 'IMPLEMENTOS',
                'tipo' => 'S',
                'item' => $imp,
                'cantidad' => 1,
                'personal_id' => $sale['id'],
                'dni' => $sale['dni'],
            ]);
            $filas[] = array_merge($base, [
                'frente' => 'IMPLEMENTOS',
                'tipo' => 'E',
                'item' => $imp,
                'cantidad' => 1,
                'personal_id' => $entra['id'],
                'dni' => $entra['dni'],
            ]);
        }

        $primero = 0;
        foreach ($filas as $fila) {
            if ($primero > 0) {
                $fila['par_id'] = $primero;
            }
            $id = $this->repo->insertar($fila);
            if ($primero === 0) {
                $primero = $id;
                $this->repo->fijarPar($id);
            }
        }

        return $this->okMov($primero, $primero, $granja, $item);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{id: int, dni: string}|array{ok: false, error: array{code: string, message: string}}
     */
    private function exigirPersona(array $body, string $granja, int $empresaId, string $claveId, string $claveDni)
    {
        $id = (int) ($body[$claveId] ?? ($claveId === 'personal_id' ? ($body['personal_id'] ?? 0) : 0));
        $dni = self::cortar(trim((string) ($body[$claveDni] ?? ($claveDni === 'dni' ? ($body['dni'] ?? '') : ''))), 15);
        if ($claveDni === 'dni_sale' && $dni === '' && isset($body['dni_saliente'])) {
            $dni = self::cortar(trim((string) $body['dni_saliente']), 15);
        }
        if ($claveDni === 'dni_entra' && $dni === '' && isset($body['dni_entrante'])) {
            $dni = self::cortar(trim((string) $body['dni_entrante']), 15);
        }
        $hallado = $this->repo->personalDeGranja($granja, $empresaId, $id, $dni);
        if ($hallado === null) {
            return self::error('validation', 'Personal inválido');
        }

        return ['id' => $hallado['id'], 'dni' => $hallado['dni']];
    }

    /** @return array{ok: true, data: array<string, mixed>} */
    private function okMov(int $id, int $par, string $granja, string $itemRopa): array
    {
        return [
            'ok' => true,
            'data' => [
                'id' => $id,
                'par_id' => $par,
                'limpio' => $this->repo->saldo($granja, 'LIMPIO', $itemRopa),
                'lavadero' => $this->repo->saldo($granja, 'LAVADERO', $itemRopa),
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
            $key = $row['granja_cod'] . '|' . $row['frente'] . '|' . $row['item'];
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
    private function filtrar(array $rows, string $desde, string $hasta, string $frente, string $categoria): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($categoria !== '' && $row['categoria'] !== $categoria) {
                continue;
            }
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

    /**
     * @param list<array{codigo: string, nombre: string}> $visibles
     */
    private function puedeGranja(string $cod, array $visibles): bool
    {
        foreach ($visibles as $g) {
            if ($g['codigo'] === $cod) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private function granjasVisibles(): array
    {
        $todas = $this->listaGranjas();
        if (UserContext::isGrs()) {
            return $todas;
        }
        $eid = UserContext::empresaId();
        if ($eid === null) {
            return [];
        }
        $codigos = $this->repo->granjasEmpresa((int) $eid);
        $porCodigo = [];
        foreach ($todas as $g) {
            $porCodigo[$g['codigo']] = $g;
        }
        $out = [];
        foreach ($codigos as $c) {
            $out[] = $porCodigo[$c] ?? ['codigo' => $c, 'nombre' => $this->granjas->nombre($c) ?: $c];
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
            'categoria' => 'UNIFORME',
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

    private static function rol(): string
    {
        return UserContext::isGrs() ? 'grs' : 'empresa';
    }

    /** @return list<string> */
    private static function acciones(): array
    {
        return UserContext::isGrs()
            ? ['ENVIO', 'SI']
            : ['CONSUMO', 'ENTREGA', 'DEVOLUCION', 'LAVADO', 'RELEVO'];
    }

    private static function categoriaDeFrente(string $frente): string
    {
        if ($frente === 'IMPLEMENTOS') {
            return 'IMPLEMENTO';
        }
        if ($frente === 'INSUMOS') {
            return 'INSUMO';
        }

        return 'UNIFORME';
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
    private static function categorias(): array
    {
        return [
            ['codigo' => 'UNIFORME', 'nombre' => 'Uniformes'],
            ['codigo' => 'IMPLEMENTO', 'nombre' => 'Implementos'],
            ['codigo' => 'INSUMO', 'nombre' => 'Insumos'],
        ];
    }

    /** @return list<array{codigo: string, nombre: string}> */
    private static function frentes(): array
    {
        return [
            ['codigo' => 'LIMPIO', 'nombre' => 'Almacén limpio'],
            ['codigo' => 'LAVADERO', 'nombre' => 'Lavadero'],
            ['codigo' => 'IMPLEMENTOS', 'nombre' => 'Implementos'],
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
        if ($f === '') {
            return date('Y-m-d');
        }
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
