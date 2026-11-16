import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalReporteTabs, personalTabs, ProcessTabs } from '../layout/processNav'

type Row = {
  fecha: string
  turno_cod: string
  granja_cod: string
  punto_nombre: string
  dni: string
  nombres: string
  apellidos: string
  cargo: string
  empresa_razon_social?: string
}

export function ReporteFaltantePage() {
  const [fecha, setFecha] = useState(todayIso())
  const report = useQuery({
    queryKey: ['faltante', fecha],
    queryFn: () => api.get<{ items: Row[] }>('/reportes/faltante', { fecha }),
  })
  const rows = report.data?.items ?? []
  return (
    <div>
      <PageHeader
        title="Personal faltante"
        actions={
          <Button
            variant="ghost"
            size="sm"
            disabled={rows.length === 0}
            onClick={() => {
              const h = ['fecha', 'turno', 'granja', 'punto', 'dni', 'nombre', 'cargo', 'empresa']
              const lines = [
                h.join(','),
                ...rows.map((r) =>
                  [r.fecha, r.turno_cod, r.granja_cod, r.punto_nombre, r.dni, `${r.apellidos} ${r.nombres}`, r.cargo, r.empresa_razon_social ?? '']
                    .map((v) => `"${String(v).replace(/"/g, '""')}"`)
                    .join(','),
                ),
              ]
              const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
              const a = document.createElement('a')
              a.href = URL.createObjectURL(blob)
              a.download = 'personal-faltante.csv'
              a.click()
            }}
          >
            CSV
          </Button>
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />
      <ProcessTabs items={personalReporteTabs} label="Reportes de personal" />
      <div className="bio-toolbar">
        <Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
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
            {rows.map((r, i) => (
              <tr key={i}>
                <td>{formatDate(r.fecha)}</td>
                <td>{r.turno_cod}</td>
                <td>{r.granja_cod}</td>
                <td>{r.punto_nombre}</td>
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
                  Sin faltantes
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
