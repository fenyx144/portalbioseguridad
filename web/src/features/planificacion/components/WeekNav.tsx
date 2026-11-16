import { addDays, dayShort, startOfWeekMonday } from '../../../shared/lib/dates'

const MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']
const fmtDia = (iso: string) => `${dayShort(iso)} ${iso.slice(8, 10)} ${MES[Number(iso.slice(5, 7)) - 1]}`

export function WeekNav({ semana, onChange }: { semana: string; onChange: (s: string) => void }) {
  const actual = startOfWeekMonday(new Date())
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', flexWrap: 'wrap' }}>
      <div className="bio-weeknav">
        <button type="button" className="bio-icon-action" title="Semana anterior" onClick={() => onChange(addDays(semana, -7))}>
          <i className="fa-solid fa-chevron-left" aria-hidden />
        </button>
        <span className="bio-weeknav-label">
          <i className="fa-regular fa-calendar" style={{ color: 'var(--ix2-accent)', marginRight: '0.4rem' }} aria-hidden />
          Desde {fmtDia(semana)} - Hasta {fmtDia(addDays(semana, 6))}
        </span>
        <button type="button" className="bio-icon-action" title="Semana siguiente" onClick={() => onChange(addDays(semana, 7))}>
          <i className="fa-solid fa-chevron-right" aria-hidden />
        </button>
      </div>
      {semana !== actual ? (
        <button type="button" className="bio-btn bio-btn-ghost bio-btn-sm" onClick={() => onChange(actual)}>
          Esta semana
        </button>
      ) : (
        <span className="bio-badge bio-badge-accent">Semana actual</span>
      )}
    </div>
  )
}
