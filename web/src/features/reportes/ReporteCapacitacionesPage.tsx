import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { formatDate } from '../../shared/lib/dates'
import { useAuth } from '../auth/useAuth'
import { Input, Select } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { ReporteExportBar } from './ReporteExportBar'
import { ReporteSectionNav } from './ReporteSectionNav'

export function ReporteCapacitacionesPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [q, setQ] = useState('')
  const [vigencia, setVigencia] = useState('')
  const [resultado, setResultado] = useState('')
  const [empresaId, setEmpresaId] = useState('')
  const [capId, setCapId] = useState('')
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: isGrs,
  })

  const caps = useQuery({
    queryKey: ['capacitaciones'],
    queryFn: () => api.get<{ items: { id: number; nombre: string }[] }>('/capacitaciones'),
  })

  const report = useQuery({
    queryKey: ['reporte-cap', q, vigencia, resultado, empresaId, capId],
    queryFn: () =>
      api.get<{ items: Record<string, string>[] }>('/reportes/capacitaciones', {
        q,
        estado_vigencia: vigencia,
        resultado,
        empresa_id: empresaId,
        capacitacion_id: capId,
      }),
  })

  const exportCsv = () => {
    const rows = report.data?.items ?? []
    if (rows.length === 0) return
    const headers = Object.keys(rows[0])
    const lines = [headers.join(','), ...rows.map((r) => headers.map((h) => `"${String(r[h] ?? '').replace(/"/g, '""')}"`).join(','))]
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'capacitaciones-personal.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div>
      <PageHeader
        title="Reporte de capacitaciones"
        actions={
          <ReporteExportBar
            layout="header"
            pdfPath="/reportes/capacitaciones.pdf"
            pdfParams={{
              q,
              estado_vigencia: vigencia,
              resultado,
              empresa_id: empresaId,
              capacitacion_id: capId,
            }}
            downloadName="capacitaciones.pdf"
            onCsv={exportCsv}
            csvDisabled={(report.data?.items.length ?? 0) === 0}
          />
        }
      />

      <ReporteSectionNav />

      <div className="bio-toolbar">
        <Input placeholder="Buscar DNI o nombre…" value={q} onChange={(e) => setQ(e.target.value)} style={{ maxWidth: '14rem' }} />
        <Select value={capId} onChange={(e) => setCapId(e.target.value)} style={{ maxWidth: '12rem' }}>
          <option value="">Capacitación</option>
          {caps.data?.items.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nombre}
            </option>
          ))}
        </Select>
        <Select value={vigencia} onChange={(e) => setVigencia(e.target.value)} style={{ maxWidth: '10rem' }}>
          <option value="">Vigencia</option>
          <option value="VIGENTE">Vigente</option>
          <option value="VENCIDA">Vencida</option>
        </Select>
        <Select value={resultado} onChange={(e) => setResultado(e.target.value)} style={{ maxWidth: '9rem' }}>
          <option value="">Resultado</option>
          <option value="APROBADO">Aprobado</option>
          <option value="DESAPROBADO">Desaprobado</option>
          <option value="PENDIENTE">Pendiente</option>
        </Select>
        {isGrs ? (
          <Select value={empresaId} onChange={(e) => setEmpresaId(e.target.value)} style={{ maxWidth: '11rem' }}>
            <option value="">Empresa</option>
            {empresas.data?.items.map((e) => (
              <option key={e.id} value={e.id}>
                {e.codigo}
              </option>
            ))}
          </Select>
        ) : null}
      </div>

      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              {isGrs ? <th>Empresa</th> : null}
              <th>DNI</th>
              <th>Nombre</th>
              <th>Cargo</th>
              <th>Capacitación</th>
              <th>Realizada</th>
              <th>Evaluación</th>
              <th>Vence</th>
              <th>Resultado</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            {report.isLoading ? (
              <tr>
                <td colSpan={isGrs ? 10 : 9} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Cargando…
                </td>
              </tr>
            ) : (
              report.data?.items.map((r, i) => (
                <tr key={i}>
                  {isGrs ? <td>{r.empresa_codigo ?? '—'}</td> : null}
                  <td style={{ fontFamily: 'ui-monospace, monospace', fontSize: '0.75rem' }}>{r.dni}</td>
                  <td>
                    {r.apellidos}, {r.nombres}
                  </td>
                  <td>{r.cargo}</td>
                  <td>
                    {r.cap_codigo} — {r.cap_nombre}
                  </td>
                  <td>{formatDate(r.fecha_realizada as string)}</td>
                  <td>{formatDate(r.fecha_evaluacion as string | null)}</td>
                  <td>{formatDate(r.fecha_vence as string | null)}</td>
                  <td>{r.resultado}</td>
                  <td>
                    <span
                      className={`bio-badge ${
                        r.estado_vigencia === 'VENCIDA' ? 'bio-badge-warn' : r.estado_vigencia === 'VIGENTE' ? 'bio-badge-ok' : ''
                      }`}
                    >
                      {r.estado_vigencia}
                    </span>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
