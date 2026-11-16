export type Asignacion = {
  id: number
  personal_id: number
  nombres: string
  apellidos: string
  cargo?: string
  dni?: string
  fecha?: string
  turno_cod?: string
  punto_id?: number
  es_reemplazo?: number
  reemplazo_motivo?: string
  reemplaza_a?: string
  original?: { personal_id: number; nombres: string; apellidos: string; cargo?: string } | null
  empresa_id?: number | null
  empresa_codigo?: string
  empresa_nombre?: string
  origen?: 'GRS' | 'EMPRESA'
  editable?: boolean
}

export type Celda = {
  requerido: number
  asignado: number
  estado: 'vacio' | 'incompleto' | 'completo'
  asignaciones: Asignacion[]
  ocupacion_externa?: { etiqueta: string; cantidad: number }[]
}

export type TurnoPunto = {
  turno_cod: string
  personal_requerido: number
  turno_nombre?: string
  hora_inicio?: string
  hora_fin?: string
  hora_inicio_override?: string | null
  hora_fin_override?: string | null
}

export type Punto = { id: number; granja_cod: string; codigo?: string; nombre: string; tipo_cod: string }

export type Fila = {
  punto: Punto
  turnos: TurnoPunto[]
  celdas: Record<string, Record<string, Celda>>
}

export type Grid = { dias: string[]; filas: Fila[] }

export type SemanaResp = {
  grid: Grid
  resumen: {
    global: { requeridos: number; cubiertos: number }
    por_granja?: Record<string, { requeridos: number; cubiertos: number }>
  }
}

export type Granja = { codigo: string; nombre: string }

export type PersonaLite = {
  id: number
  dni?: string
  nombres: string
  apellidos: string
  cargo: string
  granja_base_cod?: string
  empresa_id?: number | null
  empresa_codigo?: string | null
}

export type LoteResultado = {
  creadas: number
  fallidas: number
  con_advertencias?: number
  detalle_fallidas: {
    personal_id: number
    punto_id: number
    fecha: string
    turno_cod: string
    code: string
    message: string
  }[]
}

export type Estado = 'completo' | 'parcial' | 'vacio' | 'sin'
