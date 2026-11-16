import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../shared/api/client'
import type { Granja, LoteResultado, PersonaLite, SemanaResp } from './types'

export function useGranjas() {
  return useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: Granja[] }>('/granjas'),
    staleTime: 5 * 60_000,
  })
}

export function useSemanaPlan(semana: string, granjas: string[], empresaId?: string) {
  const key = granjas.join(',')
  return useQuery({
    queryKey: ['plan', 'semana', semana, key, empresaId ?? ''],
    queryFn: () =>
      api.get<SemanaResp>('/planificacion/semana', {
        fecha_inicio: semana,
        granja_cod: key,
        empresa_id: empresaId || undefined,
      }),
    enabled: granjas.length > 0,
  })
}

export function usePersonalActivo(enabled = true) {
  return useQuery({
    queryKey: ['personal', 'activos-plan'],
    queryFn: () => api.get<{ items: PersonaLite[] }>('/personal', { estado: 'ACTIVO', per_page: 500 }),
    enabled,
    staleTime: 60_000,
  })
}

export type RapidaBody = {
  personal_ids: number[]
  punto_id: number
  turno_cod: string
  fechas: string[]
  force_warnings?: boolean
}

export function useAsignarRapido() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: RapidaBody) => api.post<LoteResultado>('/planificacion/asignaciones/rapida', body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plan'] }),
  })
}

export function useAsignarUno() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: { id?: number; personal_id: number; punto_id: number; fecha: string; turno_cod: string; force_warnings?: boolean }) =>
      api.post<{ id: number }>('/planificacion/asignaciones', body),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plan'] }),
  })
}

export function useQuitarAsignaciones() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (ids: number[]) => {
      for (const id of ids) {
        await api.del(`/planificacion/asignaciones/${id}`)
      }
      return ids.length
    },
    onSettled: () => qc.invalidateQueries({ queryKey: ['plan'] }),
  })
}

export function useCopiarSemana() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: { granja_cod: string; semana_origen: string; semana_destino: string }) =>
      api.post<{ copiadas: number; omitidas: number; detalle_omitidas: { personal: string; punto: string; fecha: string; turno_cod: string; message: string }[] }>(
        '/planificacion/semana/copiar',
        body,
      ),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plan'] }),
  })
}

export type ReemplazoBody = {
  asignacion_id: number
  personal_reemplazo_id: number
  motivo: string
  alcance: 'dia' | 'rango'
  fecha_desde?: string
  fecha_hasta?: string
  mismo_punto?: boolean
}

export function useReemplazo() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (body: ReemplazoBody) =>
      api.post<{ reemplazadas: number; fallidas: number; detalle_fallidas: { fecha: string; turno_cod: string; message: string }[] }>(
        '/planificacion/reemplazos',
        body,
      ),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plan'] }),
  })
}

export function usePersonalDisponible(params: { punto_id: number; fecha: string; turno_cod: string } | null) {
  return useQuery({
    queryKey: ['plan', 'disponible', params],
    queryFn: () =>
      api.get<{ misma_granja: PersonaLite[]; otras: PersonaLite[] }>('/planificacion/personal-disponible', {
        punto_id: params!.punto_id,
        fecha: params!.fecha,
        turno_cod: params!.turno_cod,
      }),
    enabled: params !== null,
  })
}
