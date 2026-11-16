import { NavLink, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'

export type ProcessTab = {
  to: string
  label: string
  end?: boolean
  grsOnly?: boolean
  match?: (path: string, search: string) => boolean
}

export const personalTabs: ProcessTab[] = [
  { to: '/personal', label: 'Registro', match: (p) => p === '/personal' || /^\/personal\/\d+$/.test(p) },
  { to: '/personal/requerimiento', label: 'Requerimiento', grsOnly: true },
  { to: '/personal/asistencia', label: 'Asistencia' },
  { to: '/personal/alcoholemia', label: 'Alcoholemia' },
  { to: '/personal/anomalias', label: 'Anomalías' },
  {
    to: '/reportes/personal-registrado',
    label: 'Reportes',
    match: (p) =>
      p.startsWith('/reportes/personal') ||
      p.startsWith('/reportes/faltante') ||
      p.startsWith('/reportes/horarios') ||
      p.startsWith('/reportes/alcoholemia') ||
      p === '/reportes/anomalias',
  },
]

export const personalReporteTabs: ProcessTab[] = [
  {
    to: '/reportes/personal-registrado',
    label: 'Registrado',
    match: (p, s) => p === '/reportes/personal-registrado' && !s.includes('orden=antiguedad'),
  },
  {
    to: '/reportes/personal-registrado?orden=antiguedad',
    label: 'Antigüedad',
    match: (p, s) => p === '/reportes/personal-registrado' && s.includes('orden=antiguedad'),
  },
  { to: '/reportes/faltante', label: 'Faltante' },
  { to: '/reportes/horarios', label: 'Horarios' },
  { to: '/reportes/alcoholemia', label: 'Alcoholemia' },
  { to: '/reportes/anomalias', label: 'Anomalías' },
]

export const planTabs: ProcessTab[] = [
  { to: '/planificacion', label: 'Asignación', end: true, match: (p) => p === '/planificacion' },
  { to: '/planificacion/anomalias', label: 'Anomalías' },
  { to: '/reportes/personal-asignado', label: 'Reporte' },
]

export const capTabs: ProcessTab[] = [
  { to: '/capacitaciones', label: 'Registro', end: true, match: (p) => p === '/capacitaciones' },
  { to: '/reportes/capacitaciones', label: 'Reporte' },
]

export const almacenTabs: ProcessTab[] = [
  { to: '/almacen', label: 'Registro', end: true, match: (p) => p === '/almacen' },
  { to: '/almacen/stock', label: 'Stock' },
  { to: '/reportes/stock-indumentaria', label: 'Indumentaria', grsOnly: true },
]

export function ProcessTabs({ items, label }: { items: ProcessTab[]; label?: string }) {
  const { pathname, search } = useLocation()
  const isGrs = (useAuth().user?.actor ?? 'grs') === 'grs'
  const tabs = items.filter((t) => !t.grsOnly || isGrs)
  return (
    <div className="bio-tabs bio-process-nav" role="tablist" aria-label={label}>
      {tabs.map((t) => {
        const on = t.match ? t.match(pathname, search) : false
        return (
          <NavLink
            key={t.to}
            to={t.to}
            end={t.end}
            role="tab"
            className={({ isActive }) => `bio-tab${on || (!t.match && isActive) ? ' bio-tab--on' : ''}`}
          >
            {t.label}
          </NavLink>
        )
      })}
    </div>
  )
}
