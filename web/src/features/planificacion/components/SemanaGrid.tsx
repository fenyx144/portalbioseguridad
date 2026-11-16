import { dayShort, dayNum, todayIso } from '../../../shared/lib/dates'
import { EmptyState } from '../../../shared/ui/Kpi'
import type { Asignacion, Fila } from '../types'
import { nombreCorto, turnoIcon, turnoLabel } from '../utils'

export type GridActions = {
  onAgregar: (fila: Fila, turno: string, fecha: string) => void
  onQuitar: (asig: Asignacion) => void
  onReemplazar: (fila: Fila, turno: string, asig: Asignacion) => void
}

/** Grilla detallada: filas = punto + turno, columnas = días de la semana. */
export function SemanaGrid({
  filas,
  dias,
  readOnly,
  actions,
  mostrarEmpresa,
}: {
  filas: Fila[]
  dias: string[]
  readOnly: boolean
  actions?: GridActions
  mostrarEmpresa?: boolean
}) {
  const hoy = todayIso()
  if (filas.length === 0) {
    return (
      <div className="bio-card">
        <EmptyState icon="fa-table-cells">No hay puntos con cupos para mostrar.</EmptyState>
      </div>
    )
  }
  return (
    <div className="bio-card" style={{ padding: 0 }}>
      <div className="bio-table-wrap" style={{ maxHeight: '70vh', overflow: 'auto' }}>
        <table className="bio-plan-table">
          <thead>
            <tr>
              <th className="bio-plan-rowhead">Punto / turno</th>
              {dias.map((d) => (
                <th key={d} className={d === hoy ? 'is-today' : undefined}>
                  {dayShort(d)} {dayNum(d)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {filas.map((fila) =>
              fila.turnos.map((t, idx) => (
                <tr key={`${fila.punto.id}-${t.turno_cod}`}>
                  <td className="bio-plan-rowhead">
                    {idx === 0 ? (
                      <div style={{ fontWeight: 700, marginBottom: '0.15rem' }}>
                        <i className="fa-solid fa-location-dot" style={{ color: 'var(--ix2-accent)' }} aria-hidden /> {fila.punto.nombre}
                      </div>
                    ) : null}
                    <span className={`bio-badge ${t.turno_cod === 'NOCHE' ? 'bio-badge-noche' : 'bio-badge-dia'}`}>
                      <i className={`fa-solid ${turnoIcon(t.turno_cod)}`} aria-hidden /> {turnoLabel(t.turno_cod)} · {t.personal_requerido}
                    </span>
                  </td>
                  {dias.map((d) => {
                    const c = fila.celdas[d]?.[t.turno_cod]
                    if (!c) return <td key={d} className="bio-plan-cell" />
                    const falta = Math.max(0, c.requerido - c.asignado)
                    return (
                      <td key={d} className="bio-plan-cell" data-estado={c.estado}>
                        <div className="bio-plan-cell-head">
                          <span>
                            {c.asignado}/{c.requerido}
                          </span>
                          {falta === 0 ? <i className="fa-solid fa-check" style={{ color: 'var(--ix2-ok)' }} aria-hidden /> : null}
                        </div>
                        {c.asignaciones.map((a) => {
                          const editable = !readOnly && !!actions && a.editable !== false
                          const titulo = [
                            `${a.apellidos}, ${a.nombres}`,
                            a.cargo,
                            mostrarEmpresa ? a.empresa_nombre : null,
                            a.es_reemplazo ? `Reemplaza a ${a.reemplaza_a || '—'} (${a.reemplazo_motivo || 'sin motivo'})` : null,
                          ]
                            .filter(Boolean)
                            .join('\n')
                          return (
                            <div key={a.id} className="bio-chip" data-reemplazo={a.es_reemplazo ? 'true' : undefined} title={titulo}>
                              {a.es_reemplazo ? <i className="fa-solid fa-right-left" style={{ color: 'var(--ix2-info)' }} aria-hidden /> : null}
                              <span className="bio-chip-name">{nombreCorto(a)}</span>
                              {editable ? (
                                <>
                                  <button type="button" title="Reemplazar" onClick={() => actions!.onReemplazar(fila, t.turno_cod, a)}>
                                    <i className="fa-solid fa-right-left" aria-hidden />
                                  </button>
                                  <button type="button" className="danger" title="Quitar" onClick={() => actions!.onQuitar(a)}>
                                    <i className="fa-solid fa-xmark" aria-hidden />
                                  </button>
                                </>
                              ) : null}
                            </div>
                          )
                        })}
                        {(c.ocupacion_externa ?? []).map((o) => (
                          <div key={o.etiqueta} className="bio-chip" data-externo="true" title="Otra empresa / GRS">
                            <span className="bio-chip-name">
                              {o.cantidad} · {o.etiqueta}
                            </span>
                          </div>
                        ))}
                        {!readOnly && actions && falta > 0 ? (
                          <button type="button" className="bio-cell-add" onClick={() => actions.onAgregar(fila, t.turno_cod, d)}>
                            <i className="fa-solid fa-plus" aria-hidden /> Asignar
                          </button>
                        ) : null}
                      </td>
                    )
                  })}
                </tr>
              )),
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
