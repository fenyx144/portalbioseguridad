import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../shared/api/client'

export type Categoria = 'INDUMENTARIA' | 'DESINFECTANTE' | 'EPP' | 'OTRO'
export type Insumo = { id: number; codigo: string; nombre: string; categoria: Categoria; unidad: string; estado: 'ACTIVO' | 'INACTIVO' }
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

export const num = (v: string | number | null | undefined) => Number(v ?? 0)
export const fmt = (v: string | number | null | undefined) => num(v).toLocaleString('es-PE', { maximumFractionDigits: 2 })

export function useInsumos(todos = false) {
  return useQuery({
    queryKey: ['almacen', 'insumos', todos],
    queryFn: () => api.get<{ items: Insumo[] }>('/almacen/insumos', todos ? { todos: '1' } : undefined),
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
export type FrenteAlmacen = 'LIMPIO' | 'LAVADERO' | 'INSUMOS'
export type KardexMov = {
  id: number
  frente: FrenteAlmacen
  tipo: 'SI' | 'E' | 'S'
  granja_cod: string
  granja_nombre: string
  item: string
  item_nombre: string
  operacion: string
  cantidad: number
  stock: number
  par_id: number
  personal_id: number
  dni: string
  trabajador: string
  usuario_registro: string
  origen: 'web' | 'app'
  fecha: string
  observacion: string
  unidad: string
}

export function useStockGranja(granja: string) {
  return useQuery({
    queryKey: ['almacen', 'granja', granja],
    queryFn: () =>
      api.get<{ resumen: ResumenGranja[]; insumos: InsumoGranja[] }>('/almacen/granja/stock', { granja_cod: granja }),
  })
}

export function useKardexGranja(params: { granja_cod: string; frente?: string; desde?: string; hasta?: string }) {
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
      item: string
      frente: FrenteAlmacen
      dni: string
      observacion: string
      origen: 'web' | 'app'
    }) => api.post('/almacen/granja/movimientos', b),
  )
}
