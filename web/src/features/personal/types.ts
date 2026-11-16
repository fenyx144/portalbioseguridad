export type Personal = {
  id: number
  dni: string
  nombres: string
  apellidos: string
  cargo: string
  empresa: 'GRS' | 'TERCERO'
  empresa_id?: number | null
  empresa_codigo?: string | null
  empresa_razon_social?: string | null
  fecha_contratacion: string
  estado: 'ACTIVO' | 'CESADO'
  granja_base_cod: string
  foto_ruta: string
  usuario_cod?: string
  antiguedad_dias?: number
}

export function etiquetaEmpresaPersonal(
  p: Pick<Personal, 'empresa' | 'empresa_codigo' | 'empresa_razon_social'>,
): string {
  if (p.empresa === 'GRS') return 'GRS'
  if (p.empresa_razon_social) {
    return p.empresa_codigo ? `${p.empresa_codigo} · ${p.empresa_razon_social}` : p.empresa_razon_social
  }
  if (p.empresa_codigo) return p.empresa_codigo
  return 'Contratista sin asignar'
}
