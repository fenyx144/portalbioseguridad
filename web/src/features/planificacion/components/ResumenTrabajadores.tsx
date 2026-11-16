import { Fragment, useEffect, useState } from 'react'
import { dayNum, dayShort, formatShortDate, todayIso } from '../../../shared/lib/dates'
import { EmptyState } from '../../../shared/ui/Kpi'
import type { Asignacion, Fila, TurnoPunto } from '../types'
import { coberturaFilaTurno, pct, turnoIcon, turnoLabel, type FiltroTurnos } from '../utils'

export type TrabajadoresActions = {
  onAsignar: (fila: Fila, turno?: string, fecha?: string) => void
  onAsignarDia: (fila: Fila, turno: string, personalId: number, fecha: string) => void
  onCambiarTurno: (fila: Fila, asig: Asignacion, turno: string) => void
  onReemplazar: (fila: Fila, turno: string, asig: Asignacion) => void
  onQuitar: (asig: Asignacion) => void
}

type Entrada = { turno: string; asig: Asignacion; reemplazado: boolean }
type Trabajador = {
  personal_id: number
  nombres: string
  apellidos: string
  cargo?: string
  empresa?: string
  dias: Record<string, Entrada[]>
}

type MenuData =
  | { kind: 'asig'; fila: Fila; t: Trabajador; e: Entrada; fecha: string }
  | { kind: 'vacio'; fila: Fila; t: Trabajador; fecha: string; turnos: TurnoPunto[] }
type Menu = MenuData & { x: number; y: number }

const letra = (t: string) => (t === 'NOCHE' ? 'N' : 'D')

function trabajadores(fila: Fila, dias: string[], turnos: TurnoPunto[]): Trabajador[] {
  const map = new Map<number, Trabajador>()
  const get = (id: number, p: { nombres: string; apellidos: string; cargo?: string }, empresa?: string) => {
    let w = map.get(id)
    if (!w) {
      w = { personal_id: id, nombres: p.nombres, apellidos: p.apellidos, cargo: p.cargo, empresa, dias: {} }
      map.set(id, w)
    }
    return w
  }
  for (const d of dias) {
    for (const t of turnos) {
      for (const a of fila.celdas[d]?.[t.turno_cod]?.asignaciones ?? []) {
        const w = get(a.personal_id, a, a.empresa_nombre)
        ;(w.dias[d] ??= []).push({ turno: t.turno_cod, asig: a, reemplazado: false })
        if (a.es_reemplazo && a.original) {
          const o = get(a.original.personal_id, a.original, a.empresa_nombre)
          ;(o.dias[d] ??= []).push({ turno: t.turno_cod, asig: a, reemplazado: true })
        }
      }
    }
  }
  return [...map.values()].sort((a, b) => `${a.apellidos} ${a.nombres}`.localeCompare(`${b.apellidos} ${b.nombres}`))
}

function tono(req: number, cub: number) {
  const p = pct(req, cub)
  return p >= 100 ? 'ok' : p > 0 ? 'warn' : 'danger'
}

export function ResumenTrabajadores({
  filas,
  dias,
  filtro,
  readOnly,
  actions,
  mostrarEmpresa,
  onFalta,
}: {
  filas: Fila[]
  dias: string[]
  filtro: FiltroTurnos
  readOnly: boolean
  actions?: TrabajadoresActions
  mostrarEmpresa?: boolean
  onFalta?: () => void
}) {
  const [menu, setMenu] = useState<Menu | null>(null)
  const hoy = todayIso()
  const editable = !readOnly && !!actions

  useEffect(() => {
    if (!menu) return
    const close = () => setMenu(null)
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close()
    window.addEventListener('scroll', close, true)
    document.addEventListener('keydown', onKey)
    return () => {
      window.removeEventListener('scroll', close, true)
      document.removeEventListener('keydown', onKey)
    }
  }, [menu])

  const grupos = filas
    .map((fila) => {
      const turnos = filtro === 'DIA' || filtro === 'NOCHE' ? fila.turnos.filter((t) => t.turno_cod === filtro) : fila.turnos
      const cov = turnos.reduce(
        (a, t) => {
          const c = coberturaFilaTurno(fila, dias, t.turno_cod)
          return { req: a.req + c.req, cub: a.cub + c.cub }
        },
        { req: 0, cub: 0 },
      )
      return { fila, turnos, cov, workers: trabajadores(fila, dias, turnos) }
    })
    .filter((g) => g.turnos.length > 0 && (filtro !== 'incompletos' || g.cov.cub < g.cov.req))

  if (grupos.length === 0) {
    return (
      <div className="bio-card">
        <EmptyState icon="fa-location-dot">Sin puntos para mostrar.</EmptyState>
      </div>
    )
  }

  const abrir = (e: React.MouseEvent, m: MenuData) => {
    const r = (e.currentTarget as HTMLElement).getBoundingClientRect()
    setMenu({ ...m, x: r.left, y: r.bottom + 4 })
  }

  const run = (fn: () => void) => {
    setMenu(null)
    fn()
  }

  return (
    <>
      <div className="bio-card bio-rt-wrap">
        <table className="bio-rt-table">
          <thead>
            <tr>
              <th className="bio-rt-first">Punto / trabajador</th>
              {dias.map((d) => (
                <th key={d} className={d === hoy ? 'is-today' : undefined}>
                  {dayShort(d)} {dayNum(d)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {grupos.map(({ fila, turnos, cov, workers }) => {
                            const faltaDia = dias.map((d) =>
                turnos
                  .map((t) => {
                    const c = fila.celdas[d]?.[t.turno_cod]
                    return { turno: t.turno_cod, n: c ? Math.max(0, c.requerido - c.asignado) : 0 }
                  })
                  .filter((x) => x.n > 0),
              )
              const faltaTotal = faltaDia.reduce((s, xs) => s + xs.reduce((a, x) => a + x.n, 0), 0)
              const tn = tono(cov.req, cov.cub)
              return (
                <Fragment key={fila.punto.id}>
                  <tr className="bio-rt-punto">
                    <th className="bio-rt-first" scope="rowgroup">
                      <div className="bio-rt-pline">
                        <i className="fa-solid fa-location-dot" aria-hidden />
                        <strong title={fila.punto.nombre}>{fila.punto.nombre}</strong>
                        <span className="bio-badge bio-badge-muted">{fila.punto.tipo_cod}</span>
                        <span className="bio-rt-cov" data-tone={tn}>
                          {cov.cub}/{cov.req}
                        </span>
                      </div>
                    </th>
                    <td colSpan={dias.length} className="bio-rt-pturnos">
                      {turnos.map((t) => (
                        <span key={t.turno_cod} className={`bio-badge ${t.turno_cod === 'NOCHE' ? 'bio-badge-noche' : 'bio-badge-dia'}`}>
                          <i className={`fa-solid ${turnoIcon(t.turno_cod)}`} aria-hidden /> {turnoLabel(t.turno_cod)}: {t.personal_requerido} {t.personal_requerido === 1 ? 'persona' : 'personas'}
                        </span>
                      ))}
                    </td>
                  </tr>
                  {faltaTotal > 0 ? (
                  <tr className="bio-rt-falta">
                    <th className="bio-rt-first" scope="row">
                      <i className="fa-solid fa-circle-exclamation" aria-hidden /> Faltan
                    </th>
                    {faltaDia.map((xs, i) => (
                      <td key={dias[i]} className={dias[i] === hoy ? 'is-today' : undefined}>
                        {xs.map((x) =>
                          editable ? (
                            <button
                              key={x.turno}
                              type="button"
                              className="bio-rt-faltachip"
                              title={`${turnoLabel(x.turno)} · falta ${x.n}`}
                              onClick={() => actions!.onAsignar(fila, x.turno, dias[i])}
                            >
                              <i className={`fa-solid ${turnoIcon(x.turno)}`} aria-hidden /> {x.n}
                            </button>
                          ) : onFalta ? (
                            <button key={x.turno} type="button" className="bio-rt-faltachip" title={`${turnoLabel(x.turno)} · falta ${x.n}`} onClick={onFalta}>
                              <i className={`fa-solid ${turnoIcon(x.turno)}`} aria-hidden /> {x.n}
                            </button>
                          ) : (
                            <span key={x.turno} className="bio-rt-faltachip" title={`${turnoLabel(x.turno)} · falta ${x.n}`}>
                              <i className={`fa-solid ${turnoIcon(x.turno)}`} aria-hidden /> {x.n}
                            </span>
                          ),
                        )}
                      </td>
                    ))}
                  </tr>
                  ) : null}
                  {workers.map((w) => {
                    return (
                      <tr key={w.personal_id} className="bio-rt-worker">
                        <th className="bio-rt-first" scope="row">
                          <div className="bio-rt-wline">
                            <span className="bio-rt-wname">
                              <span title={`${w.apellidos}, ${w.nombres}`}>
                                {w.apellidos}, {w.nombres}
                              </span>
                              <small>{[w.cargo, mostrarEmpresa ? w.empresa : null].filter(Boolean).join(' · ') || '—'}</small>
                            </span>
                          </div>
                        </th>
                        {dias.map((d) => {
                          const es = w.dias[d] ?? []
                          return (
                            <td key={d} className={`bio-rt-cell${d === hoy ? ' is-today' : ''}`}>
                              {es.length === 0 ? (
                                editable ? (
                                  <button
                                    type="button"
                                    className="bio-rt-empty"
                                    onClick={(e) => abrir(e, { kind: 'vacio', fila, t: w, fecha: d, turnos })}
                                  >
                                    ·
                                  </button>
                                ) : (
                                  <span className="bio-rt-empty">·</span>
                                )
                              ) : (
                                es.map((en) => {
                                  const a = en.asig
                                  const title = en.reemplazado
                                    ? `Reemplazado por ${a.apellidos}, ${a.nombres}${a.reemplazo_motivo ? ` · ${a.reemplazo_motivo}` : ''}`
                                    : a.es_reemplazo
                                      ? `Reemplaza a ${a.reemplaza_a || '—'}${a.reemplazo_motivo ? ` · ${a.reemplazo_motivo}` : ''}`
                                      : `${turnoLabel(en.turno)} · ${formatShortDate(d)}`
                                  const kind = en.reemplazado ? 'reemplazado' : a.es_reemplazo ? 'reemplazo' : en.turno === 'NOCHE' ? 'noche' : 'dia'
                                  const clickable = editable && !en.reemplazado && a.editable !== false
                                  const content = (
                                    <>
                                      {kind === 'reemplazo' ? <i className="fa-solid fa-right-left" aria-hidden /> : null}
                                      {letra(en.turno)}
                                    </>
                                  )
                                  return clickable ? (
                                    <button
                                      key={`${a.id}-${en.reemplazado}`}
                                      type="button"
                                      className="bio-rt-chip"
                                      data-kind={kind}
                                      title={title}
                                      onClick={(e) => abrir(e, { kind: 'asig', fila, t: w, e: en, fecha: d })}
                                    >
                                      {content}
                                    </button>
                                  ) : (
                                    <span key={`${a.id}-${en.reemplazado}`} className="bio-rt-chip" data-kind={kind} title={title}>
                                      {content}
                                    </span>
                                  )
                                })
                              )}
                            </td>
                          )
                        })}
                      </tr>
                    )
                  })}
                </Fragment>
              )
            })}
          </tbody>
        </table>
      </div>

      {menu ? (
        <div className="bio-popover-layer" onMouseDown={() => setMenu(null)}>
          <div
            className="bio-popover"
            style={{ left: Math.min(menu.x, window.innerWidth - 230), top: Math.min(menu.y, window.innerHeight - 170) }}
            onMouseDown={(e) => e.stopPropagation()}
            role="menu"
          >
            <div className="bio-popover-head">
              <strong>
                {menu.t.apellidos}, {menu.t.nombres}
              </strong>
              <span>
                {formatShortDate(menu.fecha)}
                {menu.kind === 'asig' ? ` · ${turnoLabel(menu.e.turno)}` : ''}
              </span>
            </div>
            {menu.kind === 'asig' ? (
              <>
                {menu.fila.turnos
                  .filter((t) => t.turno_cod !== menu.e.turno)
                  .map((t) => (
                    <button
                      key={t.turno_cod}
                      type="button"
                      role="menuitem"
                      onClick={() => {
                        const m = menu
                        run(() => actions!.onCambiarTurno(m.fila, m.e.asig, t.turno_cod))
                      }}
                    >
                      <i className={`fa-solid ${turnoIcon(t.turno_cod)}`} aria-hidden /> Cambiar a {turnoLabel(t.turno_cod)}
                    </button>
                  ))}
                <button
                  type="button"
                  role="menuitem"
                  onClick={() => {
                    const m = menu
                    run(() => actions!.onReemplazar(m.fila, m.e.turno, m.e.asig))
                  }}
                >
                  <i className="fa-solid fa-right-left" aria-hidden /> Reemplazar
                </button>
                <button
                  type="button"
                  role="menuitem"
                  className="danger"
                  onClick={() => {
                    const m = menu
                    run(() => actions!.onQuitar(m.e.asig))
                  }}
                >
                  <i className="fa-solid fa-user-minus" aria-hidden /> Quitar este día
                </button>
              </>
            ) : (
              menu.turnos.map((t) => (
                <button
                  key={t.turno_cod}
                  type="button"
                  role="menuitem"
                  onClick={() => {
                    const m = menu
                    run(() => actions!.onAsignarDia(m.fila, t.turno_cod, m.t.personal_id, m.fecha))
                  }}
                >
                  <i className={`fa-solid ${turnoIcon(t.turno_cod)}`} aria-hidden /> Asignar este día · {turnoLabel(t.turno_cod)}
                </button>
              ))
            )}
          </div>
        </div>
      ) : null}
    </>
  )
}
