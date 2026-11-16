import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Input, Select } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { planTabs, ProcessTabs } from '../layout/processNav'
import { ReporteExportBar } from './ReporteExportBar'

export function ReporteAsignadoPage() {
  const [desde, setDesde] = useState(todayIso())
  const [hasta, setHasta] = useState(todayIso())
  const [granja, setGranja] = useState('')
  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })

  const report = useQuery({
    queryKey: ['reporte-asig', desde, hasta, granja],
    queryFn: () =>
      api.get<{ items: Record<string, string | number>[] }>('/reportes/personal-asignado', {
        fecha_desde: desde,
        fecha_hasta: hasta,
        granja_cod: granja,
      }),
  })

  const exportCsv = () => {
    const rows = report.data?.items ?? []
    if (rows.length === 0) return
    const cols: [string, (r: Record<string, string | number>) => unknown][] = [
      ['Fecha', (r) => formatDate(String(r.fecha))],
      ['Turno', (r) => r.turno_cod],
      ['Granja', (r) => (r.granja_nombre ? `${r.granja_cod} · ${r.granja_nombre}` : r.granja_cod)],
      ['Punto', (r) => r.punto_nombre],
      ['DNI', (r) => r.dni],
      ['Nombre', (r) => `${r.apellidos}, ${r.nombres}`],
      ['Empresa', (r) => r.empresa_nombre || r.empresa],
    ]
    const q = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`
    const lines = [cols.map(([h]) => q(h)).join(','), ...rows.map((r) => cols.map(([, f]) => q(f(r))).join(','))]
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'personal-asignado.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div>
      <PageHeader
        title="Personal asignado"
        actions={
          <ReporteExportBar
            layout="header"
            pdfPath="/reportes/personal-asignado.pdf"
            pdfParams={{ fecha_desde: desde, fecha_hasta: hasta, granja_cod: granja }}
            downloadName="personal-asignado.pdf"
            onCsv={exportCsv}
            csvDisabled={(report.data?.items.length ?? 0) === 0}
          />
        }
      />

      <ProcessTabs items={planTabs} label="Planificación" />

      <div className="bio-toolbar">
        <div className="bio-field" style={{ minWidth: '9rem' }}>
          <label htmlFor="desde">Desde</label>
          <Input id="desde" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        </div>
        <div className="bio-field" style={{ minWidth: '9rem' }}>
          <label htmlFor="hasta">Hasta</label>
          <Input id="hasta" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        </div>
        <div className="bio-field" style={{ minWidth: '9rem' }}>
          <label htmlFor="granja">Granja</label>
          <Select id="granja" value={granja} onChange={(e) => setGranja(e.target.value)}>
            <option value="">Todas</option>
            {granjas.data?.items.map((g) => (
              <option key={g.codigo} value={g.codigo}>
                {g.codigo} · {g.nombre}
              </option>
            ))}
          </Select>
        </div>
      </div>

      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Turno</th>
              <th>Granja</th>
              <th>Punto</th>
              <th>DNI</th>
      <th>Nombre</th>
      <th>Cargo</th>
      <th>Empresa</th>
            </tr>
          </thead>
          <tbody>
            {report.isLoading ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Cargando…
                </td>
              </tr>
            ) : report.data?.items.length === 0 ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Sin asignaciones en el período
                </td>
              </tr>
            ) : (
              report.data?.items.map((r, i) => (
                <tr key={i}>
                  <td>{formatDate(String(r.fecha))}</td>
                  <td>{r.turno_cod}</td>
                  <td>{r.granja_nombre ? `${r.granja_cod} · ${r.granja_nombre}` : r.granja_cod}</td>
                  <td>{r.punto_nombre}</td>
                  <td style={{ fontFamily: 'ui-monospace, monospace', fontSize: '0.75rem' }}>{r.dni}</td>
                  <td>
                    {r.apellidos}, {r.nombres}
                  </td>
                  <td>{r.cargo}</td>
                  <td>{r.empresa_nombre || r.empresa}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
