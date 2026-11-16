import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { formatWeekCompact, startOfWeekMonday } from '../../shared/lib/dates'
import { Kpi, PageHero } from '../../shared/ui/Kpi'
import { useAuth } from '../auth/useAuth'
import { navGroups } from './navConfig'

export function HomePage() {
  const { user } = useAuth()
  const esGrs = user?.actor !== 'empresa'
  const semana = startOfWeekMonday(new Date())
  const cov = useQuery({
    queryKey: ['cobertura', semana],
    queryFn: () =>
      api.get<{ global: { requeridos: number; cubiertos: number } }>('/planificacion/cobertura-resumen', { fecha_inicio: semana }),
  })
  const g = cov.data?.global
  const pct = g && g.requeridos > 0 ? Math.round((g.cubiertos / g.requeridos) * 100) : 0
  const faltan = g ? Math.max(0, g.requeridos - g.cubiertos) : 0

  const grupos = navGroups
    .filter((gr) => gr.id !== 'inicio' && (!gr.grsOnly || esGrs))
    .map((gr) => ({
      ...gr,
      items: gr.items.filter((i) => (!i.grsOnly || esGrs) && (!i.empresaOnly || !esGrs)),
    }))
    .filter((gr) => gr.items.length > 0)

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon={esGrs ? 'fa-shield-halved' : 'fa-building'}
        title={`Hola, ${user?.nombre || user?.codigo || ''}`}
        subtitle={esGrs ? 'GRS · Bioseguridad' : user?.empresa_nombre ?? undefined}
      />
      <div className="bio-kpis">
        <Kpi icon="fa-gauge-high" label={`Cobertura ${formatWeekCompact(semana)}`} value={cov.isLoading ? '…' : `${pct}%`} tone={pct >= 100 ? 'ok' : pct > 0 ? 'warn' : 'danger'} />
        <Kpi icon="fa-user-check" label="Turnos cubiertos" value={g ? `${g.cubiertos}/${g.requeridos}` : '—'} tone="info" />
        <Kpi icon="fa-user-clock" label="Turnos por cubrir" value={faltan} tone={faltan ? 'danger' : 'ok'} />
      </div>
      {grupos.map((gr) => (
        <section key={gr.id}>
          <h3 className="bio-section-title" style={{ margin: '0 0 0.5rem' }}>
            {gr.label}
          </h3>
          <div className="bio-hub-grid">
            {gr.items.map((item) => (
              <Link key={item.to} to={item.to} className="bio-hub-card">
                <i className={`fa-solid ${item.icon}`} style={{ color: 'var(--ix2-accent)', marginBottom: '0.35rem', display: 'block' }} aria-hidden />
                <strong>{!esGrs && item.labelEmpresa ? item.labelEmpresa : item.label}</strong>
              </Link>
            ))}
          </div>
        </section>
      ))}
    </div>
  )
}
