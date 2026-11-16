import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../../shared/api/client'

export type Cap = { id: number; codigo: string; nombre: string; vigencia_meses: number; estado: string; tipo?: string }

export type EstadoSesion = 'PROGRAMADA' | 'REALIZADA' | 'CANCELADA'
export type Resultado = 'PENDIENTE' | 'APROBADO' | 'DESAPROBADO'

export type Sesion = {
  id: number
  empresa_id: number
  capacitacion_id: number
  fecha: string
  hora_inicio: string | null
  lugar: string
  observacion: string
  nota_minima: string | number
  estado: EstadoSesion
  cap_codigo: string
  cap_nombre: string
  empresa_codigo?: string
  empresa_razon_social?: string
  num_programados?: number
  num_asistieron?: number
  num_evaluados?: number
  num_aprobados?: number
  num_desaprobados?: number
}

export type Participante = {
  personal_id: number
  dni: string
  nombres: string
  apellidos: string
  cargo: string
  asistio: number
  nota: string | number | null
  resultado: Resultado
  observacion?: string | null
}

export type SesionDetalle = { sesion: Sesion; participantes: Participante[]; puede_gestionar: boolean }

export type SesionBody = {
  capacitacion_id: number
  fecha: string
  hora_inicio?: string
  lugar: string
  nota_minima?: number
  observacion?: string
  personal_ids?: number[]
  cargos?: string[]
}

export const estadoSesion: Record<EstadoSesion, { label: string; badge: string }> = {
  PROGRAMADA: { label: 'Programada', badge: 'bio-badge-info' },
  REALIZADA: { label: 'Realizada', badge: 'bio-badge-ok' },
  CANCELADA: { label: 'Cancelada', badge: 'bio-badge-muted' },
}

export const resultadoBadge: Record<Resultado, string> = {
  PENDIENTE: 'bio-badge-muted',
  APROBADO: 'bio-badge-ok',
  DESAPROBADO: 'bio-badge-danger',
}

export function useCatalogo(todos = false) {
  return useQuery({
    queryKey: ['capacitaciones', 'cat', todos],
    queryFn: () => api.get<{ items: Cap[]; permisos?: { catalogo_editar: boolean } }>('/capacitaciones', todos ? { todos: '1' } : undefined),
  })
}

export function useSesiones(params: { desde: string; hasta: string; estado: string; empresa_id: string; capacitacion_id: string }) {
  return useQuery({
    queryKey: ['cap-sesiones', params],
    queryFn: () => api.get<{ items: Sesion[]; puede_gestionar: boolean }>('/capacitaciones/sesiones', params),
  })
}

export function useSesionDetalle(id: number | null) {
  return useQuery({
    queryKey: ['cap-sesion', id],
    queryFn: () => api.get<SesionDetalle>(`/capacitaciones/sesiones/${id}`),
    enabled: id !== null,
  })
}

export function useCargos(enabled = true) {
  return useQuery({
    queryKey: ['personal', 'cargos'],
    queryFn: () => api.get<{ items: { cargo: string; total: number }[] }>('/personal/cargos'),
    enabled,
    staleTime: 60_000,
  })
}

export function usePersonalActivoCap(enabled = true) {
  return useQuery({
    queryKey: ['personal', 'activos-cap'],
    queryFn: () =>
      api.get<{ items: { id: number; dni: string; nombres: string; apellidos: string; cargo: string }[] }>('/personal', {
        estado: 'ACTIVO',
        per_page: 500,
      }),
    enabled,
    staleTime: 60_000,
  })
}

function useInvalidar() {
  const qc = useQueryClient()
  return () => {
    void qc.invalidateQueries({ queryKey: ['cap-sesiones'] })
    void qc.invalidateQueries({ queryKey: ['cap-sesion'] })
  }
}

export function useGuardarSesion() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: ({ id, body }: { id?: number; body: SesionBody }) =>
      id ? api.patch<{ id: number }>(`/capacitaciones/sesiones/${id}`, body) : api.post<{ id: number; convocados: number }>('/capacitaciones/sesiones', body),
    onSuccess: inv,
  })
}

export function useAgregarParticipantes() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: ({ id, personal_ids, cargos }: { id: number; personal_ids?: number[]; cargos?: string[] }) =>
      api.post<{ agregados: number }>(`/capacitaciones/sesiones/${id}/participantes`, { personal_ids, cargos }),
    onSuccess: inv,
  })
}

export function useQuitarParticipante() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: ({ id, personalId }: { id: number; personalId: number }) => api.del(`/capacitaciones/sesiones/${id}/participantes/${personalId}`),
    onSuccess: inv,
  })
}

export function useAsistencia() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: ({ id, participantes }: { id: number; participantes: { personal_id: number; asistio: boolean }[] }) =>
      api.post<{ asistieron: number }>(`/capacitaciones/sesiones/${id}/asistencia`, { participantes }),
    onSuccess: inv,
  })
}

export function useEvaluacion() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: ({
      id,
      items,
      fecha_evaluacion,
    }: {
      id: number
      fecha_evaluacion?: string
      items: { personal_id: number; nota: number | null; resultado?: Resultado; observacion?: string }[]
    }) => api.post<{ guardados: number; omitidos: number }>(`/capacitaciones/sesiones/${id}/evaluacion`, { items, fecha_evaluacion }),
    onSuccess: inv,
  })
}

export function useCancelarSesion() {
  const inv = useInvalidar()
  return useMutation({
    mutationFn: (id: number) => api.post(`/capacitaciones/sesiones/${id}/cancelar`),
    onSuccess: inv,
  })
}
