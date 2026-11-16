import { NavLink } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'

const TABS = [
  { to: '/reportes/personal-registrado', label: 'Personal registrado' },
  { to: '/reportes/personal-asignado', label: 'Personal asignado' },
  { to: '/reportes/capacitaciones', label: 'Capacitaciones' },
  { to: '/reportes/stock-indumentaria', label: 'Indumentaria por proveedor', grsOnly: true },
] as const

/** Pestañas fijas entre reportes (siempre visibles; la activa queda resaltada). */
export function ReporteSectionNav() {
  const isGrs = (useAuth().user?.actor ?? 'grs') === 'grs'
  const tabs = TABS.filter((t) => !('grsOnly' in t && t.grsOnly) || isGrs)

  return (
    <div className="bio-tabs bio-reporte-section-nav" role="tablist" aria-label="Reportes">
      {tabs.map((t) => (
        <NavLink
          key={t.to}
          to={t.to}
          role="tab"
          className={({ isActive }) => `bio-tab${isActive ? ' bio-tab--on' : ''}`}
        >
          {t.label}
        </NavLink>
      ))}
    </div>
  )
}
