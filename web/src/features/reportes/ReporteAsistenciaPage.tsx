import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Kpi } from '../../shared/ui/Kpi'
import { PageHeader } from '../../shared/ui/PageHeader'
import { useAuth } from '../auth/useAuth'
import { personalReporteTabs, personalTabs, ProcessTabs } from '../layout/processNav'

type Fila = {
  personal_id: number
  dni: string
  nombres: string
  apellidos: string
  empresa_codigo: string
  empresa_razon_social: string
  granjas: string
  planificados: number
  asistencias: number
  tardanzas: number
  faltas: number
  alcoholemias: number
}

type Reporte = {
  desde: string
  hasta: string
  tolerancia_min: number
  kpis: {
    trabajadores: number
    planificados: number
    asistencias: number
    tardanzas: number
    faltas: number
    alcoholemias: number
  }
  items: Fila[]
}

function mesInicio() {
  return todayIso().slice(0, 8) + '01'
}

function excel(filas: Fila[], desde: string, hasta: string) {
  const cols = ['DNI', 'Trabajador', 'Empresa', 'Granjas', 'Planificados', 'Asistencias', 'Tardanzas', 'Faltas', 'Alcoholemias']
  const esc = (v: string | number) =>
    String(v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
  const head = cols.map((c) => `<Cell><Data ss:Type="String">${esc(c)}</Data></Cell>`).join('')
  const body = filas
    .map((r) => {
      const vals: Array<string | number> = [
        r.dni,
        `${r.apellidos} ${r.nombres}`.trim(),
        r.empresa_razon_social || r.empresa_codigo,
        r.granjas,
        r.planificados,
        r.asistencias,
        r.tardanzas,
        r.faltas,
        r.alcoholemias,
      ]
      return `<Row>${vals
        .map((v) =>
          typeof v === 'number'
            ? `<Cell><Data ss:Type="Number">${v}</Data></Cell>`
            : `<Cell><Data ss:Type="String">${esc(v)}</Data></Cell>`,
        )
        .join('')}</Row>`
    })
    .join('')
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Worksheet ss:Name="Asistencias"><Table>
<Row>${head}</Row>
${body}
</Table></Worksheet></Workbook>`
  const blob = new Blob([xml], { type: 'application/vnd.ms-excel' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `asistencias-${desde}-${hasta}.xls`
  a.click()
  URL.revokeObjectURL(a.href)
}

export function ReporteAsistenciaPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [desde, setDesde] = useState(mesInicio())
  const [hasta, setHasta] = useState(todayIso())
  const [empresaId, setEmpresaId] = useState('')
  const [granja, setGranja] = useState('')
  const [q, setQ] = useState('')
  const [tol, setTol] = useState<string | null>(null)
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: isGrs,
  })
  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
    enabled: isGrs,
  })
  const report = useQuery({
    queryKey: ['reporte-asistencia', desde, hasta, empresaId, granja, q, tol],
    queryFn: () =>
      api.get<Reporte>('/reportes/personal-asistencia', {
        desde,
        hasta,
        empresa_id: empresaId,
        granja,
        q,
        tolerancia: tol ?? '',
      }),
    enabled: isGrs,
  })
  useEffect(() => {
    if (tol === null && report.data) setTol(String(report.data.tolerancia_min))
  }, [report.data, tol])

  if (!isGrs) return <Navigate to="/personal" replace />

  const data = report.data
  const rows = data?.items ?? []
  const k = data?.kpis

  return (
    <div>
      <PageHeader
        title="Asistencias"
        actions={
          <Button variant="ghost" size="sm" disabled={rows.length === 0} onClick={() => excel(rows, desde, hasta)}>
            <i className="fa-solid fa-file-excel" aria-hidden /> Excel
          </Button>
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />
      <ProcessTabs items={personalReporteTabs} label="Reportes de personal" />
      <div className="bio-toolbar">
        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <Select value={empresaId} onChange={(e) => setEmpresaId(e.target.value)} style={{ maxWidth: '14rem' }}>
          <option value="">Empresa</option>
          {empresas.data?.items.map((e) => (
            <option key={e.id} value={e.id}>
              {e.codigo} · {e.razon_social}
            </option>
          ))}
        </Select>
        <Select value={granja} onChange={(e) => setGranja(e.target.value)} style={{ maxWidth: '14rem' }}>
          <option value="">Granja</option>
          {granjas.data?.items.map((g) => (
            <option key={g.codigo} value={g.codigo}>
              {g.codigo} · {g.nombre}
            </option>
          ))}
        </Select>
        <Input placeholder="Trabajador" value={q} onChange={(e) => setQ(e.target.value)} style={{ maxWidth: '12rem' }} />
        <Input
          type="number"
          min={0}
          value={tol ?? ''}
          onChange={(e) => setTol(e.target.value)}
          style={{ maxWidth: '6rem' }}
          aria-label="Tolerancia"
          placeholder="Min"
        />
      </div>
      <div className="bio-kpis">
        <Kpi icon="fa-users" label="Trabajadores" value={k?.trabajadores ?? 0} />
        <Kpi icon="fa-calendar-check" label="Asistencias" value={k?.asistencias ?? 0} tone="ok" />
        <Kpi icon="fa-clock" label="Tardanzas" value={k?.tardanzas ?? 0} tone={k?.tardanzas ? 'warn' : undefined} />
        <Kpi icon="fa-user-xmark" label="Faltas" value={k?.faltas ?? 0} tone={k?.faltas ? 'danger' : undefined} />
        <Kpi icon="fa-wine-bottle" label="Alcoholemias" value={k?.alcoholemias ?? 0} tone={k?.alcoholemias ? 'danger' : undefined} />
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Trabajador</th>
              <th>Empresa</th>
              <th>Granjas</th>
              <th className="num">Planificados</th>
              <th className="num">Asistencias</th>
              <th className="num">Tardanzas</th>
              <th className="num">Faltas</th>
              <th className="num">Alcoholemias</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.personal_id}>
                <td>{r.dni}</td>
                <td>
                  {r.apellidos} {r.nombres}
                </td>
                <td>{r.empresa_razon_social || r.empresa_codigo || '—'}</td>
                <td>{r.granjas || '—'}</td>
                <td className="num">{r.planificados}</td>
                <td className="num">{r.asistencias}</td>
                <td className="num">{r.tardanzas}</td>
                <td className="num">{r.faltas}</td>
                <td className="num">{r.alcoholemias}</td>
              </tr>
            ))}
            {rows.length === 0 ? (
              <tr>
                <td colSpan={9} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  {report.isLoading ? 'Cargando…' : 'Sin registros'}
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
