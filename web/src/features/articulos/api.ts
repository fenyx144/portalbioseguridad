import { useQuery } from '@tanstack/react-query'
import { api } from '../../shared/api/client'

export type ReglaArticulo = 'PERMITIDO' | 'PROHIBIDO' | 'CRITERIO'

export type ArticuloIngreso = {
  id: number
  codigo: string
  nombre: string
  regla: ReglaArticulo
  nota: string
  orden: number
  estado: 'ACTIVO' | 'INACTIVO'
}

export function useArticulosIngreso(todos = false) {
  return useQuery({
    queryKey: ['articulos-ingreso', todos],
    queryFn: () =>
      api.get<{ items: ArticuloIngreso[] }>(
        `/articulos-ingreso${todos ? '?todos=1' : ''}`,
      ),
  })
}