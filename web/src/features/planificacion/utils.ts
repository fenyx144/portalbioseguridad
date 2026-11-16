import type { Estado, Fila, Grid, Granja } from './types'

export function nombreCorto(p: { apellidos: string; nombres: string }) {
  const a = p.apellidos.trim()
  const n = p.nombres.trim().split(/\s+/)[0] ?? ''
  if (a && n) return `${a}, ${n}`
  return a || n || '—'
}

/** Primer apellido + inicial del nombre: «Mendoza C.» */
export function nombreGrilla(p: { apellidos: string; nombres: string }) {
  const a = p.apellidos.trim().split(/\s+/)[0] ?? ''
  const n = p.nombres.trim()[0] ?? ''
  return a ? `${a}${n ? ` ${n}.` : ''}` : p.nombres.trim() || '—'
}

export function iniciales(p: { apellidos: string; nombres: string }) {
  return ((p.nombres.trim()[0] ?? '') + (p.apellidos.trim()[0] ?? '')).toUpperCase() || '?'
}

export function estadoCobertura(req: number, cub: number): Estado {
  if (req <= 0) return 'sin'
  if (cub >= req) return 'completo'
  if (cub <= 0) return 'vacio'
  return 'parcial'
}

export function pct(req: number, cub: number) {
  return req > 0 ? Math.round((Math.min(cub, req) / req) * 100) : 0
}

export function turnoLabel(t: string) {
  if (t === 'DIA') return 'Día'
  if (t === 'NOCHE') return 'Noche'
  return t.charAt(0) + t.slice(1).toLowerCase()
}

export function turnoIcon(t: string) {
  return t === 'NOCHE' ? 'fa-moon' : 'fa-sun'
}

export type ResumenGranja = {
  codigo: string
  nombre: string
  puntos: number
  requeridos: number
  cubiertos: number
  estado: Estado
  porTurno: { turno: string; req: number; cub: number }[]
  porDia: { fecha: string; req: number; cub: number; estado: 'completo' | 'incompleto' | 'vacio' }[]
  personas: number
  externos: number
  urgentes: Urgente[]
}

export type Urgente = { punto: string; turno: string; falta: number; dias: number; total: number }

/** Turnos de un punto con déficit en la semana, ordenados por déficit total. */
export function urgentesFilas(filas: Fila[], dias: string[]): Urgente[] {
  const out: Urgente[] = []
  for (const f of filas) {
    for (const t of f.turnos) {
      let falta = 0
      let ds = 0
      let total = 0
      for (const d of dias) {
        const c = f.celdas[d]?.[t.turno_cod]
        if (!c) continue
        const n = Math.max(0, c.requerido - c.asignado)
        if (n > 0) {
          ds++
          total += n
          falta = Math.max(falta, n)
        }
      }
      if (total > 0) out.push({ punto: f.punto.nombre, turno: t.turno_cod, falta, dias: ds, total })
    }
  }
  return out.sort((a, b) => b.total - a.total)
}

export function resumenGranjas(grid: Grid, granjas: Granja[]): ResumenGranja[] {
  const map = new Map<string, Fila[]>()
  for (const f of grid.filas) {
    const g = f.punto.granja_cod
    if (!map.has(g)) map.set(g, [])
    map.get(g)!.push(f)
  }
  const out: ResumenGranja[] = []
  for (const [codigo, filas] of map) {
    const porTurno = new Map<string, { req: number; cub: number }>()
    const porDia = new Map<string, { req: number; cub: number }>()
    const personas = new Set<number>()
    let externos = 0
    let req = 0
    let cub = 0
    for (const f of filas) {
      for (const d of grid.dias) {
        for (const t of f.turnos) {
          const c = f.celdas[d]?.[t.turno_cod]
          if (!c) continue
          const ok = Math.min(c.asignado, c.requerido)
          req += c.requerido
          cub += ok
          const pt = porTurno.get(t.turno_cod) ?? { req: 0, cub: 0 }
          pt.req += c.requerido
          pt.cub += ok
          porTurno.set(t.turno_cod, pt)
          const pd = porDia.get(d) ?? { req: 0, cub: 0 }
          pd.req += c.requerido
          pd.cub += ok
          porDia.set(d, pd)
          c.asignaciones.forEach((a) => personas.add(a.personal_id))
          externos += (c.ocupacion_externa ?? []).reduce((n, o) => n + o.cantidad, 0)
        }
      }
    }
    out.push({
      codigo,
      nombre: granjas.find((g) => g.codigo === codigo)?.nombre ?? codigo,
      puntos: filas.length,
      requeridos: req,
      cubiertos: cub,
      estado: estadoCobertura(req, cub),
      porTurno: [...porTurno.entries()]
        .map(([turno, v]) => ({ turno, ...v }))
        .sort((a, b) => (a.turno === 'DIA' ? -1 : b.turno === 'DIA' ? 1 : a.turno.localeCompare(b.turno))),
      porDia: grid.dias.map((fecha) => {
        const v = porDia.get(fecha) ?? { req: 0, cub: 0 }
        return {
          fecha,
          ...v,
          estado: v.req > 0 && v.cub >= v.req ? 'completo' : v.cub > 0 ? 'incompleto' : 'vacio',
        }
      }),
      personas: personas.size,
      externos,
      urgentes: urgentesFilas(filas, grid.dias).slice(0, 2),
    })
  }
  return out.sort((a, b) => a.codigo.localeCompare(b.codigo))
}

export type Ocupacion = { fecha: string; turno: string; punto: string; granja: string }

/** Asignaciones visibles por persona durante la semana (para avisar cruces en el modo rápido). */
export function ocupacionPorPersona(grid: Grid | undefined): Map<number, Ocupacion[]> {
  const out = new Map<number, Ocupacion[]>()
  if (!grid) return out
  for (const f of grid.filas) {
    for (const d of grid.dias) {
      for (const t of f.turnos) {
        for (const a of f.celdas[d]?.[t.turno_cod]?.asignaciones ?? []) {
          if (!out.has(a.personal_id)) out.set(a.personal_id, [])
          out.get(a.personal_id)!.push({ fecha: d, turno: t.turno_cod, punto: f.punto.nombre, granja: f.punto.granja_cod })
        }
      }
    }
  }
  return out
}

export function coberturaFilaTurno(fila: Fila, dias: string[], turno: string) {
  let req = 0
  let cub = 0
  for (const d of dias) {
    const c = fila.celdas[d]?.[turno]
    if (!c) continue
    req += c.requerido
    cub += Math.min(c.asignado, c.requerido)
  }
  return { req, cub }
}

export type FiltroTurnos = 'todos' | 'DIA' | 'NOCHE' | 'incompletos'

/** Turnos de un punto visibles según el filtro activo. */
export function turnosVisibles(fila: Fila, dias: string[], filtro: FiltroTurnos) {
  return fila.turnos.filter((t) => {
    if (filtro === 'DIA' || filtro === 'NOCHE') return t.turno_cod === filtro
    if (filtro === 'incompletos') {
      const c = coberturaFilaTurno(fila, dias, t.turno_cod)
      return c.cub < c.req
    }
    return true
  })
}

export function filtrarFilas(filas: Fila[], dias: string[], filtro: FiltroTurnos): Fila[] {
  if (filtro === 'todos') return filas
  return filas.map((f) => ({ ...f, turnos: turnosVisibles(f, dias, filtro) })).filter((f) => f.turnos.length > 0)
}
