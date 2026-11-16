import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { todayIso } from '../../shared/lib/dates'
import { Input } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalReporteTabs, personalTabs, ProcessTabs } from '../layout/processNav'

type Row = {
  tipo: string
  marcado_en: string
  turno_cod: string
  motivo: string
  dni: string
  nombres: string
  apellidos: string
  cargo: string
  empresa_razon_social?: string
  hora_inicio?: string
  hora_fin?: string
}

export function ReporteHorariosPage() {
  const [desde, setDesde] = useState(todayIso())
  const [hasta, setHasta] = useState(todayIso())
  const report = useQuery({
    queryKey: ['horarios', desde, hasta],
    queryFn: () => api.get<{ items: Row[]; tolerancia_min: number }>('/reportes/horarios', { desde, hasta }),
  })
  const rows = report.data?.items ?? []
  return (
    <div>
      <PageHeader title="Incumplimiento de horarios" />
      <ProcessTabs items={personalTabs} label="Personal" />
      <ProcessTabs items={personalReporteTabs} label="Reportes de personal" />
      <div className="bio-toolbar">
        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <span style={{ fontSize: '0.8125rem', color: 'var(--ix2-muted)' }}>Tolerancia {report.data?.tolerancia_min ?? 15} min</span>
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Marcación</th>
              <th>Tipo</th>
              <th>Turno</th>
              <th>Motivo</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Cargo</th>
              <th>Empresa</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, i) => (
              <tr key={i}>
                <td>{r.marcado_en?.replace('T', ' ').slice(0, 16)}</td>
                <td>{r.tipo}</td>
                <td>{r.turno_cod}</td>
                <td>{r.motivo}</td>
                <td>{r.dni}</td>
                <td>
                  {r.apellidos}, {r.nombres}
                </td>
                <td>{r.cargo}</td>
                <td>{r.empresa_razon_social || '—'}</td>
              </tr>
            ))}
            {rows.length === 0 ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Sin incumplimientos
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
