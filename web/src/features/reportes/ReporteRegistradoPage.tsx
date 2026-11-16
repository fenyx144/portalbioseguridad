import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { Input, Select } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { useAuth } from '../auth/useAuth'
import { etiquetaEmpresaPersonal, type Personal } from '../personal/types'
import { ReporteExportBar } from './ReporteExportBar'
import { ReporteSectionNav } from './ReporteSectionNav'

export function ReporteRegistradoPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [empresa, setEmpresa] = useState('')
  const [empresaId, setEmpresaId] = useState('')
  const [estado, setEstado] = useState('')
  const [granja, setGranja] = useState('')
  const [q, setQ] = useState('')
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: isGrs,
  })
  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })

  const report = useQuery({
    queryKey: ['reporte-reg', empresa, empresaId, estado, granja, q],
    queryFn: () =>
      api.get<{ items: Personal[] }>('/reportes/personal-registrado', {
        empresa,
        empresa_id: empresaId,
        estado,
        granja_base: granja,
        q,
      }),
  })

  const exportCsv = () => {
    const rows = report.data?.items ?? []
    if (rows.length === 0) return
    const headers = ['dni', 'apellidos', 'nombres', 'cargo', 'contratista', 'estado', 'granja_base_cod']
    const lines = [
      headers.join(','),
      ...rows.map((r) =>
        headers
          .map((h) => {
            const v = h === 'contratista' ? etiquetaEmpresaPersonal(r) : String((r as Record<string, unknown>)[h] ?? '')
            return `"${v.replace(/"/g, '""')}"`
          })
          .join(','),
      ),
    ]
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'personal-registrado.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div>
      <PageHeader
        title="Personal registrado"
        actions={
          <ReporteExportBar
            layout="header"
            pdfPath="/reportes/personal-registrado.pdf"
            pdfParams={{ empresa, empresa_id: empresaId, estado, granja_base: granja, q }}
            downloadName="personal-registrado.pdf"
            onCsv={exportCsv}
            csvDisabled={(report.data?.items.length ?? 0) === 0}
          />
        }
      />

      <ReporteSectionNav />

      <div className="bio-toolbar">
        <Input placeholder="Buscar…" value={q} onChange={(e) => setQ(e.target.value)} style={{ maxWidth: '14rem' }} />
        {isGrs ? (
          <Select value={empresaId} onChange={(e) => setEmpresaId(e.target.value)} style={{ maxWidth: '14rem' }}>
            <option value="">Contratista</option>
            {empresas.data?.items.map((e) => (
              <option key={e.id} value={e.id}>
                {e.codigo} · {e.razon_social}
              </option>
            ))}
          </Select>
        ) : null}
        <Select value={empresa} onChange={(e) => setEmpresa(e.target.value)} style={{ maxWidth: '7.5rem' }}>
          <option value="">Origen</option>
          <option value="GRS">GRS</option>
          <option value="TERCERO">Contratista</option>
        </Select>
        <Select value={estado} onChange={(e) => setEstado(e.target.value)} style={{ maxWidth: '7.5rem' }}>
          <option value="">Estado</option>
          <option value="ACTIVO">Activo</option>
          <option value="CESADO">Cesado</option>
        </Select>
        <Select value={granja} onChange={(e) => setGranja(e.target.value)} style={{ maxWidth: '9rem' }}>
          <option value="">Granja base</option>
          {granjas.data?.items.map((g) => (
            <option key={g.codigo} value={g.codigo}>
              {g.codigo}
            </option>
          ))}
        </Select>
      </div>

      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Cargo</th>
              <th>Contratista</th>
              <th>Estado</th>
              <th>Granja base</th>
            </tr>
          </thead>
          <tbody>
            {report.isLoading ? (
              <tr>
                <td colSpan={6} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Cargando…
                </td>
              </tr>
            ) : (
              report.data?.items.map((r, i) => (
                <tr key={i}>
                  <td style={{ fontFamily: 'ui-monospace, monospace', fontSize: '0.75rem' }}>{r.dni}</td>
                  <td>
                    {r.apellidos}, {r.nombres}
                  </td>
                  <td>{r.cargo}</td>
                  <td>{etiquetaEmpresaPersonal(r)}</td>
                  <td>{r.estado}</td>
                  <td>{r.granja_base_cod ? ((g) => (g ? `${g.codigo} - ${g.nombre}` : r.granja_base_cod))(granjas.data?.items.find((x) => x.codigo === r.granja_base_cod)) : '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
