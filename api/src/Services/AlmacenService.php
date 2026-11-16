<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Repositories\AlmacenRepository;
use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Support\UserContext;

/**
 * Control de almacén: GRS entrega insumos a la empresa contratista; la empresa confirma la recepción
 * (con cantidades recibidas y anomalías) y registra consumos. Stock = recibido − consumido;
 * faltante = saldo inicial − stock (si es positivo).
 */
final class AlmacenService
{
    const CATEGORIAS = ['INDUMENTARIA', 'DESINFECTANTE', 'EPP', 'OTRO'];

    /** @var AlmacenRepository */
    private $repo;

    public function __construct()
    {
        $this->repo = new AlmacenRepository();
    }

    // ---------- Insumos ----------

    public function insumos(bool $todos): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->insumos(!$todos)]];
    }

    /** @param array<string, mixed> $body */
    public function guardarInsumo(?int $id, array $body): array
    {
        UserContext::requireGrs();
        if ($id !== null && $this->repo->insumo($id) === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Insumo no encontrado']];
        }
        $d = [
            'codigo' => strtoupper(substr(trim((string) ($body['codigo'] ?? '')), 0, 20)),
            'nombre' => substr(trim((string) ($body['nombre'] ?? '')), 0, 120),
            'categoria' => strtoupper(trim((string) ($body['categoria'] ?? 'INDUMENTARIA'))),
            'unidad' => strtoupper(substr(trim((string) ($body['unidad'] ?? 'UND')), 0, 20)) ?: 'UND',
            'estado' => ($body['estado'] ?? 'ACTIVO') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO',
        ];
        if ($d['codigo'] === '' || $d['nombre'] === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Código y nombre son obligatorios']];
        }
        if (!in_array($d['categoria'], self::CATEGORIAS, true)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Categoría inválida']];
        }
        if ($this->repo->existeCodigoInsumo($d['codigo'], $id)) {
            return ['ok' => false, 'error' => ['code' => 'duplicate', 'message' => 'Ya existe un insumo con ese código']];
        }
        if ($id === null) {
            $id = $this->repo->crearInsumo($d);
        } else {
            $this->repo->actualizarInsumo($id, $d);
        }

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    // ---------- Saldos ----------

    /** @param array<string, mixed> $body */
    public function guardarSaldos(array $body): array
    {
        UserContext::requireGrs();
        $empresaId = (int) ($body['empresa_id'] ?? 0);
        if ($empresaId < 1 || (new EmpresaRepository())->find($empresaId) === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Empresa inválida']];
        }
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $n = 0;
        foreach ($items as $it) {
            $iid = (int) ($it['insumo_id'] ?? 0);
            if ($iid < 1 || $this->repo->insumo($iid) === null || !is_numeric($it['saldo_inicial'] ?? null)) {
                continue;
            }
            $this->repo->guardarSaldo($empresaId, $iid, max(0.0, round((float) $it['saldo_inicial'], 2)));
            $n++;
        }

        return ['ok' => true, 'data' => ['guardados' => $n]];
    }

    // ---------- Entregas ----------

    /** @param array<string, string> $q */
    public function entregas(array $q): array
    {
        $estado = strtoupper((string) ($q['estado'] ?? ''));

        return ['ok' => true, 'data' => ['items' => $this->repo->entregas([
            'empresa_id' => $this->empresaScope($q),
            'estado' => in_array($estado, ['PENDIENTE', 'RECIBIDA', 'OBSERVADA', 'ANULADA'], true) ? $estado : '',
            'desde' => self::esFecha((string) ($q['desde'] ?? '')) ? $q['desde'] : '',
            'hasta' => self::esFecha((string) ($q['hasta'] ?? '')) ? $q['hasta'] : '',
        ])]];
    }

    public function entrega(int $id): array
    {
        $e = $this->repo->entrega($id);
        if ($e === null || !$this->puedeVerEmpresa((int) $e['empresa_id'])) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Entrega no encontrada']];
        }

        return ['ok' => true, 'data' => ['entrega' => $e, 'items' => $this->repo->entregaDetalle($id)]];
    }

    /** @param array<string, mixed> $body */
    public function crearEntrega(array $body, string $usuario): array
    {
        UserContext::requireGrs();
        $empresaId = (int) ($body['empresa_id'] ?? 0);
        if ($empresaId < 1 || (new EmpresaRepository())->find($empresaId) === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione la empresa']];
        }
        $fecha = (string) ($body['fecha'] ?? '');
        if (!self::esFecha($fecha)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Fecha inválida']];
        }
        $items = [];
        foreach (is_array($body['items'] ?? null) ? $body['items'] : [] as $it) {
            $iid = (int) ($it['insumo_id'] ?? 0);
            $cant = is_numeric($it['cantidad'] ?? null) ? round((float) $it['cantidad'], 2) : 0.0;
            if ($iid < 1 || $cant <= 0 || $this->repo->insumo($iid) === null) {
                continue;
            }
            $items[] = ['insumo_id' => $iid, 'cantidad' => $cant];
        }
        if ($items === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Agregue al menos un insumo con cantidad']];
        }
        $id = $this->repo->crearEntrega([
            'empresa_id' => $empresaId,
            'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? '')), 0, 20),
            'fecha' => $fecha,
            'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 255),
        ], $items, $usuario);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    /**
     * La empresa confirma la recepción. Si alguna cantidad difiere o hay anomalía, queda OBSERVADA.
     *
     * @param array<string, mixed> $body
     */
    public function confirmarRecepcion(int $id, array $body, string $usuario): array
    {
        if (UserContext::isGrs()) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'La recepción la confirma la empresa contratista']];
        }
        $e = $this->repo->entrega($id);
        if ($e === null || (int) $e['empresa_id'] !== (int) UserContext::empresaId()) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Entrega no encontrada']];
        }
        if ($e['estado'] !== 'PENDIENTE') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'La entrega ya fue procesada']];
        }
        $detalle = $this->repo->entregaDetalle($id);
        $enviadas = [];
        foreach (is_array($body['items'] ?? null) ? $body['items'] : [] as $it) {
            if (is_numeric($it['cantidad_recibida'] ?? null)) {
                $enviadas[(int) ($it['detalle_id'] ?? 0)] = max(0.0, round((float) $it['cantidad_recibida'], 2));
            }
        }
        $recibidas = [];
        $difiere = false;
        foreach ($detalle as $d) {
            $did = (int) $d['id'];
            $cant = $enviadas[$did] ?? (float) $d['cantidad'];
            $recibidas[$did] = $cant;
            if (abs($cant - (float) $d['cantidad']) > 0.001) {
                $difiere = true;
            }
        }
        $anomalia = substr(trim((string) ($body['anomalia'] ?? '')), 0, 500);
        if ($difiere && $anomalia === '') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Hay diferencias en cantidades: describa la anomalía']];
        }
        $estado = ($difiere || $anomalia !== '') ? 'OBSERVADA' : 'RECIBIDA';
        $this->repo->confirmarRecepcion($id, $recibidas, $estado, $anomalia, $usuario);

        return ['ok' => true, 'data' => ['estado' => $estado]];
    }

    public function anularEntrega(int $id): array
    {
        UserContext::requireGrs();
        $e = $this->repo->entrega($id);
        if ($e === null) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Entrega no encontrada']];
        }
        if ($e['estado'] !== 'PENDIENTE') {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Solo se anulan entregas pendientes']];
        }
        $this->repo->setEstadoEntrega($id, 'ANULADA');

        return ['ok' => true, 'data' => null];
    }

    // ---------- Consumos ----------

    /** @param array<string, string> $q */
    public function consumos(array $q): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->consumos([
            'empresa_id' => $this->empresaScope($q),
            'desde' => self::esFecha((string) ($q['desde'] ?? '')) ? $q['desde'] : '',
            'hasta' => self::esFecha((string) ($q['hasta'] ?? '')) ? $q['hasta'] : '',
        ])]];
    }

    /** @param array<string, mixed> $body */
    public function crearConsumo(array $body, string $usuario): array
    {
        if (UserContext::isGrs()) {
            $eid = (int) ($body['empresa_id'] ?? 0);
            if ($eid < 1) {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione la empresa contratista']];
            }
            $emp = (new EmpresaRepository())->find($eid);
            if ($emp === null || ($emp['estado'] ?? '') !== 'ACTIVO') {
                return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Empresa contratista no válida']];
            }
        } else {
            $eid = (int) UserContext::empresaId();
            if ($eid < 1) {
                return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Empresa no identificada']];
            }
        }
        $iid = (int) ($body['insumo_id'] ?? 0);
        $cant = is_numeric($body['cantidad'] ?? null) ? round((float) $body['cantidad'], 2) : 0.0;
        $fecha = (string) ($body['fecha'] ?? '');
        if ($iid < 1 || $this->repo->insumo($iid) === null) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Seleccione el insumo']];
        }
        if ($cant <= 0) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Cantidad inválida']];
        }
        if (!self::esFecha($fecha)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Fecha inválida']];
        }
        $id = $this->repo->crearConsumo([
            'empresa_id' => $eid,
            'insumo_id' => $iid,
            'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? '')), 0, 20),
            'fecha' => $fecha,
            'cantidad' => $cant,
            'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 255),
        ], $usuario);

        return ['ok' => true, 'data' => ['id' => $id]];
    }

    public function eliminarConsumo(int $id): array
    {
        $c = $this->repo->consumo($id);
        if ($c === null || UserContext::isGrs() || (int) $c['empresa_id'] !== (int) UserContext::empresaId()) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Consumo no encontrado']];
        }
        $this->repo->eliminarConsumo($id);

        return ['ok' => true, 'data' => null];
    }

    // ---------- Stock ----------

    /** @param array<string, string> $q */
    public function stock(array $q): array
    {
        $empresaId = $this->empresaScope($q);
        $empresas = [];
        foreach ((new EmpresaRepository())->listar() as $e) {
            if ($empresaId !== null && (int) $e['id'] !== $empresaId) {
                continue;
            }
            $empresas[(int) $e['id']] = $e;
        }
        $insumos = [];
        foreach ($this->repo->insumos(false) as $i) {
            $insumos[(int) $i['id']] = $i;
        }
        $rows = [];
        $key = static function (int $e, int $i): string {
            return $e . '|' . $i;
        };
        foreach ($this->repo->saldos($empresaId) as $s) {
            $k = $key((int) $s['empresa_id'], (int) $s['insumo_id']);
            $rows[$k] = ['empresa_id' => (int) $s['empresa_id'], 'insumo_id' => (int) $s['insumo_id'], 'saldo_inicial' => (float) $s['saldo_inicial'],
                'entregado' => 0.0, 'recibido' => 0.0, 'pendiente' => 0.0, 'consumido' => 0.0];
        }
        foreach ($this->repo->movimientos($empresaId) as $m) {
            $k = $key((int) $m['empresa_id'], (int) $m['insumo_id']);
            if (!isset($rows[$k])) {
                $rows[$k] = ['empresa_id' => (int) $m['empresa_id'], 'insumo_id' => (int) $m['insumo_id'], 'saldo_inicial' => 0.0,
                    'entregado' => 0.0, 'recibido' => 0.0, 'pendiente' => 0.0, 'consumido' => 0.0];
            }
            foreach (['entregado', 'recibido', 'pendiente', 'consumido'] as $c) {
                $rows[$k][$c] += (float) $m[$c];
            }
        }
        $categoriaFiltro = trim((string) ($q['categoria'] ?? ''));
        $out = [];
        foreach ($rows as $r) {
            $e = $empresas[$r['empresa_id']] ?? null;
            $i = $insumos[$r['insumo_id']] ?? null;
            if ($e === null || $i === null) {
                continue;
            }
            if ($categoriaFiltro !== '' && (string) ($i['categoria'] ?? '') !== $categoriaFiltro) {
                continue;
            }
            $stock = round($r['recibido'] - $r['consumido'], 2);
            $out[] = array_merge($r, [
                'empresa_codigo' => $e['codigo'],
                'empresa_razon_social' => $e['razon_social'],
                'insumo_codigo' => $i['codigo'],
                'insumo_nombre' => $i['nombre'],
                'categoria' => $i['categoria'],
                'unidad' => $i['unidad'],
                'stock' => $stock,
                'faltante' => max(0.0, round($r['saldo_inicial'] - $stock, 2)),
            ]);
        }
        usort($out, static function (array $a, array $b): int {
            return strcmp($a['empresa_razon_social'] . $a['categoria'] . $a['insumo_nombre'], $b['empresa_razon_social'] . $b['categoria'] . $b['insumo_nombre']);
        });

        return ['ok' => true, 'data' => ['items' => $out]];
    }

    /** @param array<string, string> $q */
    private function empresaScope(array $q): ?int
    {
        if (!UserContext::isGrs()) {
            return (int) UserContext::empresaId();
        }
        $eid = (int) ($q['empresa_id'] ?? 0);

        return $eid > 0 ? $eid : null;
    }

    private function puedeVerEmpresa(int $empresaId): bool
    {
        return UserContext::isGrs() || $empresaId === (int) UserContext::empresaId();
    }

    private static function esFecha(string $f): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
    }

    // ---------- Campo (móvil empresa) ----------

    /** @param array<string, string> $q */
    public function campoContext(array $q): array
    {
        $stock = $this->stock($q);
        $entregas = $this->entregas(array_merge($q, ['estado' => 'PENDIENTE']));

        return [
            'ok' => true,
            'data' => [
                'actor' => UserContext::actor(),
                'empresa_id' => UserContext::empresaId(),
                'stock' => $stock['data']['items'] ?? [],
                'entregas_pendientes' => $entregas['data']['items'] ?? [],
                'insumos' => $this->insumos(false)['data']['items'] ?? [],
            ],
        ];
    }

    // ---------- Relevo ----------

    /** @param array<string, string> $q */
    public function relevos(array $q): array
    {
        return ['ok' => true, 'data' => ['items' => $this->repo->relevos([
            'empresa_id' => $this->empresaScope($q),
            'desde' => self::esFecha((string) ($q['desde'] ?? '')) ? $q['desde'] : '',
            'hasta' => self::esFecha((string) ($q['hasta'] ?? '')) ? $q['hasta'] : '',
        ])]];
    }

    public function relevo(int $id): array
    {
        $r = $this->repo->relevo($id);
        if ($r === null || !$this->puedeVerEmpresa((int) $r['empresa_id'])) {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Relevo no encontrado']];
        }

        return ['ok' => true, 'data' => ['relevo' => $r, 'items' => $this->repo->relevoDetalle($id)]];
    }

    /** @param array<string, mixed> $body */
    public function crearRelevo(array $body, string $usuario): array
    {
        if (UserContext::isGrs()) {
            return ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'El relevo lo registra la empresa en campo']];
        }
        $eid = (int) UserContext::empresaId();
        $fecha = (string) ($body['fecha'] ?? date('Y-m-d'));
        if (!self::esFecha($fecha)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Fecha inválida']];
        }
        $itemsIn = is_array($body['items'] ?? null) ? $body['items'] : [];
        $items = [];
        foreach ($itemsIn as $it) {
            $iid = (int) ($it['insumo_id'] ?? 0);
            if ($iid < 1 || $this->repo->insumo($iid) === null) {
                continue;
            }
            $sys = is_numeric($it['cantidad_sistema'] ?? null) ? round((float) $it['cantidad_sistema'], 2) : 0.0;
            $cnt = is_numeric($it['cantidad_contada'] ?? null) ? round((float) $it['cantidad_contada'], 2) : 0.0;
            $items[] = ['insumo_id' => $iid, 'cantidad_sistema' => $sys, 'cantidad_contada' => $cnt];
        }
        if ($items === []) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Indique al menos un insumo contado']];
        }
        try {
            $id = $this->repo->crearRelevo([
                'empresa_id' => $eid,
                'granja_cod' => substr(trim((string) ($body['granja_cod'] ?? '')), 0, 20),
                'fecha' => $fecha,
                'turno_cod' => strtoupper(substr(trim((string) ($body['turno_cod'] ?? 'DIA')), 0, 20)),
                'responsable_salida' => substr(trim((string) ($body['responsable_salida'] ?? '')), 0, 80),
                'responsable_entrada' => substr(trim((string) ($body['responsable_entrada'] ?? '')), 0, 80),
                'observacion' => substr(trim((string) ($body['observacion'] ?? '')), 0, 500),
            ], $items, $usuario);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['code' => 'db', 'message' => 'No se pudo guardar el relevo (¿migración 008 aplicada?)']];
        }

        return ['ok' => true, 'data' => ['id' => $id]];
    }
}
