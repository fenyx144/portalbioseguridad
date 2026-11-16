import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { Kpi } from '../../shared/ui/Kpi'
import { Select } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { fmt, type StockRow } from '../almacen/api'
import { useAuth } from '../auth/useAuth'
import { almacenTabs, ProcessTabs } from '../layout/processNav'
import { ReporteExportBar } from './ReporteExportBar'

export function ReporteStockIndumentariaPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [empresaId, setEmpresaId] = useState('')
  const [soloFaltantes, setSoloFaltantes] = useState(false)

  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: isGrs,
  })

  const report = useQuery({
    queryKey: ['reporte-stock-indumentaria', empresaId],
    queryFn: () => api.get<{ items: StockRow[] }>('/reportes/stock-indumentaria', { empresa_id: empresaId }),
    enabled: isGrs,
  })

  const rows = useMemo(
    () => (report.data?.items ?? []).filter((r) => !soloFaltantes || r.faltante > 0),
    [report.data, soloFaltantes],
  )

  const porProveedor = useMemo(() => {
    const map = new Map<number, { codigo: string; razon: string; items: StockRow[] }>()
    for (const r of rows) {
      let g = map.get(r.empresa_id)
      if (!g) {
        g = { codigo: r.empresa_codigo, razon: r.empresa_razon_social, items: [] }
        map.set(r.empresa_id, g)
      }
      g.items.push(r)
    }
    return [...map.values()].sort((a, b) => a.razon.localeCompare(b.razon, 'es'))
  }, [rows])

  const all = report.data?.items ?? []
  const conFaltante = all.filter((r) => r.faltante > 0).length
  const proveedores = new Set(all.map((r) => r.empresa_id)).size

  const exportCsv = () => {
    if (rows.length === 0) return
    const cols: [string, (r: StockRow) => string][] = [
      ['Proveedor', (r) => r.empresa_razon_social],
      ['Código proveedor', (r) => r.empresa_codigo],
      ['Código insumo', (r) => r.insumo_codigo],
      ['Insumo', (r) => r.insumo_nombre],
      ['Unidad', (r) => r.unidad],
      ['Saldo inicial', (r) => String(r.saldo_inicial)],
      ['Entregado', (r) => String(r.entregado)],
      ['Recibido', (r) => String(r.recibido)],
      ['Pendiente', (r) => String(r.pendiente)],
      ['Consumido', (r) => String(r.consumido)],
      ['Stock', (r) => String(r.stock)],
      ['Faltante', (r) => String(r.faltante)],
    ]
    const q = (v: string) => `"${v.replace(/"/g, '""')}"`
    const lines = [cols.map(([h]) => q(h)).join(','), ...rows.map((r) => cols.map(([, f]) => q(f(r))).join(','))]
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'stock-indumentaria-proveedor.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  if (!isGrs) {
    return <Navigate to="/almacen/stock" replace />
  }

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHeader
        title="Indumentaria por proveedor"
        //description="Stock de prendas y calzado por empresa contratista (saldo inicial, entregas GRS y consumo en granja)."
        actions={
          <ReporteExportBar
            layout="header"
            pdfPath="/reportes/stock-indumentaria.pdf"
            pdfParams={{ empresa_id: empresaId }}
            downloadName="stock-indumentaria-proveedor.pdf"
            onCsv={exportCsv}
            csvDisabled={rows.length === 0}
          />
        }
      />

      <ProcessTabs items={almacenTabs} label="Almacén" />

      <div className="bio-kpis">
        <Kpi icon="fa-building" label="Proveedores con stock" value={proveedores} tone="info" />
        <Kpi icon="fa-shirt" label="Líneas de indumentaria" value={all.length} tone="info" />
        <Kpi icon="fa-circle-exclamation" label="Con faltante" value={conFaltante} tone={conFaltante ? 'danger' : 'ok'} />
      </div>

      <div className="bio-toolbar">
        <Select value={empresaId} onChange={(e) => setEmpresaId(e.target.value)} style={{ maxWidth: '16rem' }}>
          <option value="">Todos los proveedores</option>
          {empresas.data?.items.map((e) => (
            <option key={e.id} value={e.id}>
              {e.codigo} · {e.razon_social}
            </option>
          ))}
        </Select>
        <label className="bio-switch">
          <input type="checkbox" checked={soloFaltantes} onChange={(e) => setSoloFaltantes(e.target.checked)} /> Solo faltantes
        </label>
      </div>

      {report.isLoading ? (
        <p style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>Cargando…</p>
      ) : porProveedor.length === 0 ? (
        <p style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>Sin datos de indumentaria</p>
      ) : (
        porProveedor.map((prov) => (
          <div key={prov.codigo + prov.razon} className="bio-card" style={{ padding: 0 }}>
            <div className="bio-card-head" style={{ padding: '0.65rem 1rem', borderBottom: '1px solid var(--ix2-border)' }}>
              <strong>
                <i className="fa-solid fa-building" style={{ color: 'var(--ix2-accent)', marginRight: '0.35rem' }} aria-hidden />
                {prov.codigo} · {prov.razon}
              </strong>
              <span className="bio-badge bio-badge-muted">{prov.items.length} insumo(s)</span>
            </div>
            <div className="bio-table-wrap">
              <table className="bio-table">
                <thead>
                  <tr>
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
                  {prov.items.map((r) => (
                    <tr key={`${r.empresa_id}-${r.insumo_id}`}>
                      <td>
                        <span className="bio-muted" style={{ fontSize: '0.72rem', marginRight: '0.35rem' }}>
                          {r.insumo_codigo}
                        </span>
                        {r.insumo_nombre}
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
                      <td className="num">
                        {r.faltante > 0 ? (
                          <span className="bio-badge bio-badge-danger">{fmt(r.faltante)}</span>
                        ) : (
                          <span className="bio-badge bio-badge-ok">0</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        ))
      )}
    </div>
  )
}
