import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { api } from '../../shared/api/client'
import { EmptyState, Kpi, PageHero } from '../../shared/ui/Kpi'
import { useAuth } from '../auth/useAuth'
import { almacenTabs, ProcessTabs } from '../layout/processNav'
import { categorias, fmt, useStock } from './api'

export function StockPage() {
  const { user } = useAuth()
  const esGrs = user?.actor !== 'empresa'
  const [empresaId, setEmpresaId] = useState('')
  const [soloFaltantes, setSoloFaltantes] = useState(false)
  const stock = useStock(empresaId)
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; razon_social: string }[] }>('/empresas'),
    enabled: esGrs,
  })
  const rows = useMemo(() => (stock.data?.items ?? []).filter((r) => !soloFaltantes || r.faltante > 0), [stock.data, soloFaltantes])
  const all = stock.data?.items ?? []
  const conFaltante = all.filter((r) => r.faltante > 0).length
  const pendientes = all.filter((r) => r.pendiente > 0).length

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero icon="fa-boxes-stacked" title="Stock de insumos" subtitle={esGrs ? undefined : user?.empresa_nombre ?? undefined}       />
      <ProcessTabs items={almacenTabs} label="Almacén" />
      <div className="bio-kpis">
        <Kpi icon="fa-boxes-stacked" label="Insumos con movimiento" value={all.length} tone="info" />
        <Kpi icon="fa-circle-exclamation" label="Con faltante" value={conFaltante} tone={conFaltante ? 'danger' : 'ok'} />
        <Kpi icon="fa-hourglass-half" label="Con entregas pendientes" value={pendientes} tone={pendientes ? 'warn' : undefined} />
      </div>
      <div className="bio-card bio-filter-bar">
        {esGrs ? (
          <label style={{ flex: '1 1 14rem' }}>
            <span className="bio-label">Empresa</span>
            <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
              <option value="">Todas</option>
              {(empresas.data?.items ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.razon_social}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <label className="bio-switch">
          <input type="checkbox" checked={soloFaltantes} onChange={(e) => setSoloFaltantes(e.target.checked)} /> Solo faltantes
        </label>
      </div>
      <div className="bio-card" style={{ padding: 0 }}>
        {rows.length === 0 ? (
          <EmptyState icon="fa-boxes-stacked">{stock.isLoading ? 'Cargando…' : 'Sin datos'}</EmptyState>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  {esGrs ? <th>Empresa</th> : null}
                  <th>Insumo</th>
                  <th>Unidad</th>
                  <th className="num">Saldo inicial</th>
                  <th className="num">Entregado</th>
                  <th className="num">Recibido</th>
                  <th className="num">Pendiente</th>
                  <th className="num">Consumido</th>
                  <th className="num">Stock</th>
                  <th className="num">Faltante</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={`${r.empresa_id}-${r.insumo_id}`}>
                    {esGrs ? <td>{r.empresa_razon_social}</td> : null}
                    <td>
                      <i className={`fa-solid ${categorias[r.categoria].icon}`} style={{ color: 'var(--ix2-accent)' }} aria-hidden /> {r.insumo_nombre}
                    </td>
                    <td>{r.unidad}</td>
                    <td className="num">{fmt(r.saldo_inicial)}</td>
                    <td className="num">{fmt(r.entregado)}</td>
                    <td className="num">{fmt(r.recibido)}</td>
                    <td className="num">{r.pendiente ? <span className="bio-badge bio-badge-warn">{fmt(r.pendiente)}</span> : '—'}</td>
                    <td className="num">{fmt(r.consumido)}</td>
                    <td className="num">
                      <strong>{fmt(r.stock)}</strong>
                    </td>
                    <td className="num">{r.faltante > 0 ? <span className="bio-badge bio-badge-danger">{fmt(r.faltante)}</span> : <span className="bio-badge bio-badge-ok">0</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
