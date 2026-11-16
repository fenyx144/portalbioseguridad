import { dayLetter, formatShortDate } from '../../../shared/lib/dates'
import { EmptyState } from '../../../shared/ui/Kpi'
import type { ResumenGranja } from '../utils'
import { pct, turnoIcon, turnoLabel } from '../utils'

const estadoTexto = { completo: 'Completo', parcial: 'Parcial', vacio: 'Sin cubrir', sin: 'Sin cupos' }

export function GranjaCards({
  items,
  onOpen,
  loading,
}: {
  items: ResumenGranja[]
  onOpen: (codigo: string) => void
  loading?: boolean
}) {
  if (loading) {
    return <EmptyState icon="fa-spinner fa-spin">Cargando planificación…</EmptyState>
  }
  if (items.length === 0) {
    return (
      <div className="bio-card">
        <EmptyState icon="fa-map-location-dot">No hay granjas con puntos activos que coincidan con el filtro.</EmptyState>
      </div>
    )
  }
  return (
    <div className="bio-granja-grid">
      {items.map((g) => {
        const p = pct(g.requeridos, g.cubiertos)
        return (
          <button key={g.codigo} type="button" className="bio-granja-card" data-estado={g.estado} onClick={() => onOpen(g.codigo)}>
            <div className="bio-granja-card-head">
              <div style={{ minWidth: 0 }}>
                <div className="bio-granja-card-sub">{g.codigo}</div>
                <div className="bio-granja-card-title" title={g.nombre}>
                  {g.nombre}
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div className="bio-pct" data-estado={g.estado}>
                  {p}%
                </div>
                <div style={{ fontSize: '0.65rem', color: 'var(--ix2-muted)' }}>{estadoTexto[g.estado]}</div>
              </div>
            </div>

            <div className="bio-turno-rows">
              {g.porTurno.map((t) => {
                const tp = pct(t.req, t.cub)
                const tone = tp >= 100 ? undefined : tp > 0 ? 'warn' : 'danger'
                return (
                  <div key={t.turno} className="bio-turno-row">
                    <span className="bio-turno-lbl">
                      <i className={`fa-solid ${turnoIcon(t.turno)} bio-turno-ic-${t.turno === 'NOCHE' ? 'noche' : 'dia'}`} aria-hidden />
                      {turnoLabel(t.turno)}
                    </span>
                    <div className="bio-progress" data-tone={tone}>
                      <span style={{ width: `${tp}%` }} />
                    </div>
                    <span style={{ fontVariantNumeric: 'tabular-nums', color: 'var(--ix2-muted)' }}>
                      {t.cub}/{t.req}
                    </span>
                  </div>
                )
              })}
            </div>

            <div>
              <div className="bio-week-strip" aria-label="Cobertura por día">
                {g.porDia.map((d) => (
                  <span key={d.fecha} data-estado={d.estado} title={`${formatShortDate(d.fecha)}: ${d.cub}/${d.req}`} />
                ))}
              </div>
              <div className="bio-week-strip" style={{ marginTop: 2 }}>
                {g.porDia.map((d) => (
                  <small key={d.fecha} style={{ fontSize: '0.56rem', textAlign: 'center', color: 'var(--ix2-muted)' }}>
                    {dayLetter(d.fecha)}
                  </small>
                ))}
              </div>
            </div>

            {g.urgentes.length > 0 ? (
              <ul className="bio-urgentes">
                {g.urgentes.map((u) => (
                  <li key={`${u.punto}-${u.turno}`}>
                    <i className={`fa-solid ${turnoIcon(u.turno)}`} aria-hidden />
                    <span>
                      {u.punto} · {turnoLabel(u.turno)}
                    </span>
                    <strong>
                      falta {u.falta}
                      {u.dias > 1 ? ` · ${u.dias} d` : ''}
                    </strong>
                  </li>
                ))}
              </ul>
            ) : null}

            <div className="bio-granja-card-foot">
              <span>
                <i className="fa-solid fa-location-dot" aria-hidden /> {g.puntos} punto{g.puntos !== 1 ? 's' : ''} ·{' '}
                <i className="fa-solid fa-user" aria-hidden /> {g.personas}
              </span>
              <span className="bio-open">
                Ver puntos <i className="fa-solid fa-arrow-right" aria-hidden />
              </span>
            </div>
          </button>
        )
      })}
    </div>
  )
}
