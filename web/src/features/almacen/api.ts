import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../shared/api/client'

export type Categoria = 'INDUMENTARIA' | 'DESINFECTANTE' | 'EPP' | 'OTRO'
export type Insumo = { id: number; codigo: string; nombre: string; categoria: Categoria; unidad: string; estado: 'ACTIVO' | 'INACTIVO' }
export type EstadoEntrega = 'PENDIENTE' | 'RECIBIDA' | 'OBSERVADA' | 'ANULADA'
export type Entrega = {
  id: number
  empresa_id: number
  granja_cod: string
  fecha: string
  estado: EstadoEntrega
  observacion: string
  anomalia: string
  recibido_en: string | null
  recibido_por: string
  empresa_codigo?: string
  empresa_razon_social?: string
  num_items?: number
  total_cantidad?: string | number
}
export type EntregaItem = {
  id: number
  insumo_id: number
  cantidad: string | number
  cantidad_recibida: string | number | null
  insumo_codigo: string
  insumo_nombre: string
  categoria: Categoria
  unidad: string
}
export type Consumo = {
  id: number
  insumo_id: number
  fecha: string
  cantidad: string | number
  granja_cod: string
  observacion: string
  insumo_nombre: string
  unidad: string
  categoria: Categoria
  empresa_razon_social?: string
  empresa_codigo?: string
}
export type StockRow = {
  empresa_id: number
  insumo_id: number
  saldo_inicial: number
  entregado: number
  recibido: number
  pendiente: number
  consumido: number
  stock: number
  faltante: number
  empresa_codigo: string
  empresa_razon_social: string
  insumo_codigo: string
  insumo_nombre: string
  categoria: Categoria
  unidad: string
}

export const categorias: Record<Categoria, { label: string; icon: string; badge: string }> = {
  INDUMENTARIA: { label: 'Indumentaria', icon: 'fa-shirt', badge: 'bio-badge-info' },
  DESINFECTANTE: { label: 'Desinfectante', icon: 'fa-spray-can-sparkles', badge: 'bio-badge-accent' },
  EPP: { label: 'EPP', icon: 'fa-helmet-safety', badge: 'bio-badge-warn' },
  OTRO: { label: 'Otro', icon: 'fa-box', badge: 'bio-badge-muted' },
}

export const estadosEntrega: Record<EstadoEntrega, { label: string; badge: string }> = {
  PENDIENTE: { label: 'Pendiente', badge: 'bio-badge-warn' },
  RECIBIDA: { label: 'Recibida', badge: 'bio-badge-ok' },
  OBSERVADA: { label: 'Observada', badge: 'bio-badge-danger' },
  ANULADA: { label: 'Anulada', badge: 'bio-badge-muted' },
}

export const num = (v: string | number | null | undefined) => Number(v ?? 0)
export const fmt = (v: string | number | null | undefined) => num(v).toLocaleString('es-PE', { maximumFractionDigits: 2 })

export function useInsumos(todos = false) {
  return useQuery({
    queryKey: ['almacen', 'insumos', todos],
    queryFn: () => api.get<{ items: Insumo[] }>('/almacen/insumos', todos ? { todos: '1' } : undefined),
  })
}

export function useEntregas(params: { estado?: string; empresa_id?: string; desde?: string; hasta?: string }) {
  return useQuery({
    queryKey: ['almacen', 'entregas', params],
    queryFn: () => api.get<{ items: Entrega[] }>('/almacen/entregas', params),
  })
}

export function useEntrega(id: number | null) {
  return useQuery({
    queryKey: ['almacen', 'entrega', id],
    queryFn: () => api.get<{ entrega: Entrega; items: EntregaItem[] }>(`/almacen/entregas/${id}`),
    enabled: id !== null,
  })
}

export function useConsumos(enabled = true) {
  return useQuery({
    queryKey: ['almacen', 'consumos'],
    queryFn: () => api.get<{ items: Consumo[] }>('/almacen/consumos'),
    enabled,
  })
}

export function useStock(empresaId?: string) {
  return useQuery({
    queryKey: ['almacen', 'stock', empresaId ?? ''],
    queryFn: () => api.get<{ items: StockRow[] }>('/almacen/stock', { empresa_id: empresaId }),
  })
}

function useAlmacenMutation<B, R = unknown>(fn: (b: B) => Promise<R>) {
  const qc = useQueryClient()
  return useMutation({ mutationFn: fn, onSuccess: () => qc.invalidateQueries({ queryKey: ['almacen'] }) })
}

export const useGuardarInsumo = () =>
  useAlmacenMutation(({ id, body }: { id?: number; body: Omit<Insumo, 'id'> }) =>
    id ? api.patch(`/almacen/insumos/${id}`, body) : api.post('/almacen/insumos', body),
  )
export const useGuardarSaldos = () =>
  useAlmacenMutation((b: { empresa_id: number; items: { insumo_id: number; saldo_inicial: number }[] }) =>
    api.post<{ guardados: number }>('/almacen/saldos', b),
  )
export const useCrearEntrega = () =>
  useAlmacenMutation(
    (b: { empresa_id: number; granja_cod: string; fecha: string; observacion: string; items: { insumo_id: number; cantidad: number }[] }) =>
      api.post<{ id: number }>('/almacen/entregas', b),
  )
export const useConfirmarRecepcion = () =>
  useAlmacenMutation(({ id, ...b }: { id: number; anomalia: string; items: { detalle_id: number; cantidad_recibida: number }[] }) =>
    api.post<{ estado: EstadoEntrega }>(`/almacen/entregas/${id}/recepcion`, b),
  )
export const useAnularEntrega = () => useAlmacenMutation((id: number) => api.post(`/almacen/entregas/${id}/anular`))
export const useCrearConsumo = () =>
  useAlmacenMutation(
    (b: {
      insumo_id: number
      cantidad: number
      fecha: string
      granja_cod: string
      observacion: string
      empresa_id?: number
    }) => api.post('/almacen/consumos', b),
  )
export const useEliminarConsumo = () => useAlmacenMutation((id: number) => api.del(`/almacen/consumos/${id}`))

export type OperacionAlmacen =
  | 'SI_UNIFORME'
  | 'CARGA_UNIFORME'
  | 'ENTREGA'
  | 'RECEPCION'
  | 'LAVADO'
  | 'SI_INSUMO'
  | 'CARGA_INSUMO'
  | 'CONSUMO'

export type ResumenGranja = { granja_cod: string; granja_nombre: string; limpio: number; lavadero: number }
export type InsumoGranja = {
  granja_cod: string
  granja_nombre: string
  insumo_id: number
  codigo: string
  nombre: string
  unidad: string
  categoria: string
  stock: number
}
export type KardexMov = {
  id: number
  clase: 'uniforme' | 'insumo'
  granja_cod: string
  granja_nombre: string
  fecha: string
  concepto: string
  almacen: string
  almacen_nombre: string
  tipo_mov: 'SI' | 'E' | 'S'
  cantidad: number
  stock: number
  ropa_tipo: number
  ropa_nombre: string
  insumo_id: number
  insumo_nombre: string
  unidad: string
  dni: string
  trabajador: string
  observacion: string
  usuario_registro: string
}

export function useStockGranja(granja: string) {
  return useQuery({
    queryKey: ['almacen', 'granja', granja],
    queryFn: () =>
      api.get<{ resumen: ResumenGranja[]; insumos: InsumoGranja[] }>('/almacen/granja/stock', { granja_cod: granja }),
  })
}

export function useKardexGranja(params: { granja_cod: string; almacen?: string; desde?: string; hasta?: string; clase?: string }) {
  return useQuery({
    queryKey: ['almacen', 'kardex', params],
    queryFn: () => api.get<{ items: KardexMov[] }>('/almacen/granja/kardex', params),
    enabled: params.granja_cod !== '',
  })
}

export function useMovimientoGranja() {
  return useAlmacenMutation(
    (b: {
      operacion: OperacionAlmacen
      granja_cod: string
      fecha: string
      cantidad: number
      ropa_tipo: number
      almacen: string
      insumo_id: number
      dni: string
      trabajador: string
      observacion: string
    }) => api.post('/almacen/granja/movimientos', b),
  )
}
