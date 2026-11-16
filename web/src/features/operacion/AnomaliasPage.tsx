import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalReporteTabs, personalTabs, planTabs, ProcessTabs } from '../layout/processNav'

type Row = {
  id: number
  ambito: string
  fecha: string
  descripcion: string
  accion: string
  estado: string
  granja_nombre: string
  puesto_cod: string
  dni?: string
  nombres?: string
  apellidos?: string
  empresa_razon_social?: string
}

const AMBITOS = ['PLANIFICACION', 'REQUERIMIENTO', 'ASISTENCIA', 'ALCOHOLEMIA', 'GENERAL'] as const

export function AnomaliasPage({ reporte = false }: { reporte?: boolean }) {
  const loc = useLocation()
  const dePlan = loc.pathname.startsWith('/planificacion')
  const qc = useQueryClient()
  const [ambito, setAmbito] = useState(dePlan ? 'PLANIFICACION' : '')
  const [estado, setEstado] = useState('')
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({
    ambito: dePlan ? 'PLANIFICACION' : 'REQUERIMIENTO',
    fecha: todayIso(),
    descripcion: '',
    accion: '',
    granja_nombre: '',
    puesto_cod: '',
  })
  const list = useQuery({
    queryKey: ['anomalias', ambito, estado, reporte],
    queryFn: () => api.get<{ items: Row[] }>(reporte ? '/reportes/anomalias' : '/anomalias', { ambito, estado }),
  })
  const crear = useMutation({
    mutationFn: () => api.post('/anomalias', form),
    onSuccess: () => {
      setOpen(false)
      setForm((f) => ({ ...f, descripcion: '', accion: '' }))
      void qc.invalidateQueries({ queryKey: ['anomalias'] })
    },
  })
  const patch = useMutation({
    mutationFn: (r: { id: number; estado: string; accion: string }) => api.patch(`/anomalias/${r.id}`, { estado: r.estado, accion: r.accion }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['anomalias'] }),
  })
  const rows = list.data?.items ?? []

  return (
    <div>
      <PageHeader
        title="Anomalías"
        actions={
          reporte ? null : (
            <Button icon="fa-plus" onClick={() => setOpen(true)}>
              Registrar
            </Button>
          )
        }
      />
      <ProcessTabs items={dePlan ? planTabs : personalTabs} label={dePlan ? 'Planificación' : 'Personal'} />
      {reporte ? <ProcessTabs items={personalReporteTabs} label="Reportes de personal" /> : null}
      <div className="bio-toolbar">
        <Select value={ambito} onChange={(e) => setAmbito(e.target.value)} style={{ maxWidth: '12rem' }}>
          <option value="">Ámbito</option>
          {AMBITOS.map((a) => (
            <option key={a} value={a}>
              {a}
            </option>
          ))}
        </Select>
        <Select value={estado} onChange={(e) => setEstado(e.target.value)} style={{ maxWidth: '10rem' }}>
          <option value="">Estado</option>
          <option value="ABIERTA">Abierta</option>
          <option value="EN_PROCESO">En proceso</option>
          <option value="CERRADA">Cerrada</option>
        </Select>
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Ámbito</th>
              <th>Descripción</th>
              <th>Acción</th>
              <th>Estado</th>
              <th>Granja</th>
              <th>Persona</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td>{formatDate(r.fecha)}</td>
                <td>{r.ambito}</td>
                <td>{r.descripcion}</td>
                <td>{r.accion || '—'}</td>
                <td>{r.estado}</td>
                <td>{r.granja_nombre || '—'}</td>
                <td>{r.dni ? `${r.apellidos}, ${r.nombres}` : '—'}</td>
                <td>
                  {r.estado !== 'CERRADA' ? (
                    <Button size="sm" variant="ghost" onClick={() => patch.mutate({ id: r.id, estado: 'CERRADA', accion: r.accion })}>
                      Cerrar
                    </Button>
                  ) : null}
                </td>
              </tr>
            ))}
            {rows.length === 0 ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Sin anomalías
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
      <Modal open={open} title="Anomalía" onClose={() => setOpen(false)}>
        <div className="grid gap-3">
          <Select value={form.ambito} onChange={(e) => setForm((f) => ({ ...f, ambito: e.target.value }))}>
            {AMBITOS.map((a) => (
              <option key={a} value={a}>
                {a}
              </option>
            ))}
          </Select>
          <Input type="date" value={form.fecha} onChange={(e) => setForm((f) => ({ ...f, fecha: e.target.value }))} />
          <Input placeholder="Granja" value={form.granja_nombre} onChange={(e) => setForm((f) => ({ ...f, granja_nombre: e.target.value }))} />
          <Input placeholder="Descripción" value={form.descripcion} onChange={(e) => setForm((f) => ({ ...f, descripcion: e.target.value }))} />
          <Input placeholder="Acción desarrollada" value={form.accion} onChange={(e) => setForm((f) => ({ ...f, accion: e.target.value }))} />
          <Button disabled={!form.descripcion.trim() || crear.isPending} onClick={() => crear.mutate()}>
            Guardar
          </Button>
          {crear.isError ? <p className="text-sm text-red-600">{(crear.error as Error).message}</p> : null}
        </div>
      </Modal>
    </div>
  )
}
