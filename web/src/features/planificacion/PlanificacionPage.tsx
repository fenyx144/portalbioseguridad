import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { startOfWeekMonday, weekDaysFromMonday } from '../../shared/lib/dates'
import { Kpi, PageHero } from '../../shared/ui/Kpi'
import { useAuth } from '../auth/useAuth'
import { useGranjas, useSemanaPlan } from './api'
import { GranjaCards } from './components/GranjaCards'
import { GranjaPanel } from './components/GranjaPanel'
import { WeekNav } from './components/WeekNav'
import { pct, resumenGranjas } from './utils'

type Filtro = 'todas' | 'pendientes' | 'completas'

export function PlanificacionPage() {
  const { user } = useAuth()
  const esGrs = user?.actor !== 'empresa'
  const [semana, setSemana] = useState(() => startOfWeekMonday(new Date()))
  const [params, setParams] = useSearchParams()
  const granjaSel = params.get('granja')
  const setGranjaSel = (cod: string | null) =>
    setParams(
      (prev) => {
        const n = new URLSearchParams(prev)
        if (cod) n.set('granja', cod)
        else {
          n.delete('granja')
          n.delete('tab')
        }
        return n
      },
    )
  const [empresaId, setEmpresaId] = useState('')
  const [granjaFiltro, setGranjaFiltro] = useState('')
  const [filtro, setFiltro] = useState<Filtro>('todas')

  const granjas = useGranjas()
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: esGrs,
  })
  const codigos = useMemo(
    () => (granjaFiltro ? [granjaFiltro] : (granjas.data?.items ?? []).map((g) => g.codigo)),
    [granjas.data, granjaFiltro],
  )
  const plan = useSemanaPlan(semana, codigos, esGrs ? empresaId : undefined)
  const grid = plan.data?.grid
  const dias = grid?.dias ?? weekDaysFromMonday(semana)

  const resumen = useMemo(() => (grid ? resumenGranjas(grid, granjas.data?.items ?? []) : []), [grid, granjas.data])
  const visibles = resumen.filter((g) =>
    filtro === 'todas' ? true : filtro === 'completas' ? g.estado === 'completo' : g.estado !== 'completo',
  )
  const tot = resumen.reduce(
    (a, g) => ({ req: a.req + g.requeridos, cub: a.cub + g.cubiertos, personas: a.personas + g.personas }),
    { req: 0, cub: 0, personas: 0 },
  )
  const completas = resumen.filter((g) => g.estado === 'completo').length

  const seleccion = granjaSel ? resumen.find((g) => g.codigo === granjaSel) : undefined
  const filasSel = grid && seleccion ? grid.filas.filter((f) => f.punto.granja_cod === seleccion.codigo) : []

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-calendar-week"
        title={esGrs ? 'Planificación de terceros' : 'Planificación semanal'}
        subtitle={esGrs ? 'Vista consolidada' : user?.empresa_nombre ?? undefined}
      />
      <div style={{ display: 'flex', justifyContent: 'center' }}>
        <WeekNav semana={semana} onChange={setSemana} />
      </div>

      {esGrs ? (
        <div className="bio-card bio-filter-bar">
          <label style={{ flex: '1 1 14rem' }}>
            <span className="bio-label">Empresa</span>
            <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
              <option value="">Todas las empresas</option>
              {(empresas.data?.items ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.razon_social} · {e.codigo}
                </option>
              ))}
            </select>
          </label>
          <label style={{ flex: '1 1 12rem' }}>
            <span className="bio-label">Granja</span>
            <select
              className="bio-select"
              value={granjaFiltro}
              onChange={(e) => {
                setGranjaFiltro(e.target.value)
                setGranjaSel(e.target.value || null)
              }}
            >
              <option value="">Todas las granjas</option>
              {(granjas.data?.items ?? []).map((g) => (
                <option key={g.codigo} value={g.codigo}>
                  {g.nombre}
                </option>
              ))}
            </select>
          </label>
        </div>
      ) : null}

      {seleccion && grid ? (
        <GranjaPanel
          key={`${seleccion.codigo}-${semana}`}
          granja={seleccion}
          filas={filasSel}
          dias={dias}
          semana={semana}
          gridCompleto={grid}
          readOnly={esGrs}
          mostrarEmpresa={esGrs}
          onBack={() => {
            setGranjaSel(null)
            if (esGrs) setGranjaFiltro('')
          }}
        />
      ) : (
        <>
          <div className="bio-kpis">
            <Kpi icon="fa-gauge-high" label="Cobertura semanal" value={`${pct(tot.req, tot.cub)}%`} tone={tot.req && tot.cub >= tot.req ? 'ok' : tot.cub ? 'warn' : 'danger'} />
            <Kpi icon="fa-wheat-awn" label="Granjas completas" value={`${completas}/${resumen.length}`} tone="info" />
            <Kpi icon="fa-user-clock" label="Turnos por cubrir" value={Math.max(0, tot.req - tot.cub)} tone={tot.req - tot.cub > 0 ? 'danger' : 'ok'} />
            <Kpi icon="fa-users" label="Personas asignadas" value={tot.personas} />
          </div>
          <div className="bio-tabs" role="tablist">
            {(
              [
                ['todas', 'Todas', resumen.length],
                ['pendientes', 'Por completar', resumen.length - completas],
                ['completas', 'Completas', completas],
              ] as const
            ).map(([v, l, n]) => (
              <button key={v} type="button" role="tab" className="bio-tab" aria-selected={filtro === v} onClick={() => setFiltro(v)}>
                {l}
                <span className="bio-tab-count">{n}</span>
              </button>
            ))}
          </div>
          {plan.isError ? (
            <div className="bio-alert" data-tone="danger">
              <i className="fa-solid fa-circle-exclamation" aria-hidden /> {(plan.error as Error).message}
            </div>
          ) : null}
          <GranjaCards items={visibles} loading={plan.isLoading || granjas.isLoading} onOpen={setGranjaSel} />
        </>
      )}
    </div>
  )
}
