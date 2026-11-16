import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalReporteTabs, personalTabs, ProcessTabs } from '../layout/processNav'
import type { Personal } from '../personal/types'

type Row = {
  id: number
  dni: string
  nombres: string
  apellidos: string
  fecha: string
  hora: string | null
  resultado: string
  valor: string | null
  accion: string
  origen: string
  empresa_razon_social?: string
}

export function AlcoholemiaPage({ reporte = false }: { reporte?: boolean }) {
  const qc = useQueryClient()
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [resultado, setResultado] = useState('')
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    personal_id: '',
    fecha: todayIso(),
    hora: '',
    resultado: 'NEGATIVO',
    valor: '',
    observacion: '',
    accion: '',
    granja_cod: '',
  })
  const list = useQuery({
    queryKey: ['alcoholemia', desde, hasta, resultado, reporte],
    queryFn: () => api.get<{ items: Row[] }>(reporte ? '/reportes/alcoholemia' : '/alcoholemia', { desde, hasta, resultado }),
  })
  const personal = useQuery({
    queryKey: ['personal-op'],
    queryFn: () => api.get<{ items: Personal[] }>('/personal', { estado: 'ACTIVO', per_page: 500 }),
    enabled: open,
  })
  const crear = useMutation({
    mutationFn: () => api.post('/alcoholemia', { ...form, personal_id: Number(form.personal_id), valor: form.valor || undefined }),
    onSuccess: () => {
      setOpen(false)
      void qc.invalidateQueries({ queryKey: ['alcoholemia'] })
    },
  })
  const rows = list.data?.items ?? []

  return (
    <div>
      <PageHeader
        title="Alcoholemia"
        actions={
          reporte ? null : (
            <Button icon="fa-plus" onClick={() => setOpen(true)}>
              Registrar
            </Button>
          )
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />
      {reporte ? <ProcessTabs items={personalReporteTabs} label="Reportes de personal" /> : null}
      <div className="bio-toolbar">
        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <Select value={resultado} onChange={(e) => setResultado(e.target.value)} style={{ maxWidth: '10rem' }}>
          <option value="">Resultado</option>
          <option value="NEGATIVO">Negativo</option>
          <option value="POSITIVO">Positivo</option>
          <option value="NO_SE_REALIZO">No se realizó</option>
        </Select>
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Resultado</th>
              <th>Valor</th>
              <th>Acción</th>
              <th>Origen</th>
              <th>Empresa</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td>{formatDate(r.fecha)}</td>
                <td>{r.hora?.slice(0, 5) || '—'}</td>
                <td>{r.dni}</td>
                <td>
                  {r.apellidos}, {r.nombres}
                </td>
                <td>{r.resultado}</td>
                <td>{r.valor ?? '—'}</td>
                <td>{r.accion || '—'}</td>
                <td>{r.origen}</td>
                <td>{r.empresa_razon_social || '—'}</td>
              </tr>
            ))}
            {rows.length === 0 ? (
              <tr>
                <td colSpan={9} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Sin registros
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
      <Modal open={open} title="Control de alcoholemia" onClose={() => setOpen(false)}>
        <div className="grid gap-3">
          <Select value={form.personal_id} onChange={(e) => setForm((f) => ({ ...f, personal_id: e.target.value }))}>
            <option value="">Personal</option>
            {(personal.data?.items ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.dni} · {p.apellidos}, {p.nombres}
              </option>
            ))}
          </Select>
          <Input type="date" value={form.fecha} onChange={(e) => setForm((f) => ({ ...f, fecha: e.target.value }))} />
          <Input type="time" value={form.hora} onChange={(e) => setForm((f) => ({ ...f, hora: e.target.value }))} />
          <Select value={form.resultado} onChange={(e) => setForm((f) => ({ ...f, resultado: e.target.value }))}>
            <option value="NEGATIVO">Negativo</option>
            <option value="POSITIVO">Positivo</option>
            <option value="NO_SE_REALIZO">No se realizó</option>
          </Select>
          <Input placeholder="Valor" value={form.valor} onChange={(e) => setForm((f) => ({ ...f, valor: e.target.value }))} />
          <Input placeholder="Acción" value={form.accion} onChange={(e) => setForm((f) => ({ ...f, accion: e.target.value }))} />
          <Input placeholder="Observación" value={form.observacion} onChange={(e) => setForm((f) => ({ ...f, observacion: e.target.value }))} />
          <Button disabled={!form.personal_id || crear.isPending} onClick={() => crear.mutate()}>
            Guardar
          </Button>
          {crear.isError ? <p className="text-sm text-red-600">{(crear.error as Error).message}</p> : null}
        </div>
      </Modal>
    </div>
  )
}
