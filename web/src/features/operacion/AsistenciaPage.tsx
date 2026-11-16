import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../../shared/api/client'
import { todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalTabs, ProcessTabs } from '../layout/processNav'
import type { Personal } from '../personal/types'

type Marca = {
  id: number
  dni: string
  nombres: string
  apellidos: string
  cargo: string
  tipo: string
  marcado_en: string
  origen: string
  granja_cod: string
  puesto_cod: string
  empresa_razon_social?: string
  observacion: string
}

export function AsistenciaPage() {
  const qc = useQueryClient()
  const [desde, setDesde] = useState(todayIso())
  const [hasta, setHasta] = useState(todayIso())
  const [tipo, setTipo] = useState('')
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ personal_id: '', tipo: 'ENTRADA', marcado_en: '', granja_cod: '', puesto_cod: '', observacion: '' })
  const list = useQuery({
    queryKey: ['asistencia', desde, hasta, tipo],
    queryFn: () => api.get<{ items: Marca[] }>('/asistencia', { desde, hasta, tipo }),
  })
  const personal = useQuery({
    queryKey: ['personal-op'],
    queryFn: () => api.get<{ items: Personal[] }>('/personal', { estado: 'ACTIVO', per_page: 500 }),
    enabled: open,
  })
  const puestos = useQuery({
    queryKey: ['puestos'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/puestos'),
    enabled: open,
  })
  const crear = useMutation({
    mutationFn: () =>
      api.post('/asistencia', {
        ...form,
        personal_id: Number(form.personal_id),
        marcado_en: form.marcado_en || undefined,
      }),
    onSuccess: () => {
      setOpen(false)
      void qc.invalidateQueries({ queryKey: ['asistencia'] })
    },
  })

  return (
    <div>
      <PageHeader
        title="Entrada y salida"
        actions={
          <Button icon="fa-plus" onClick={() => setOpen(true)}>
            Registrar
          </Button>
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />
      <div className="bio-toolbar">
        <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        <Select value={tipo} onChange={(e) => setTipo(e.target.value)} style={{ maxWidth: '9rem' }}>
          <option value="">Tipo</option>
          <option value="ENTRADA">Entrada</option>
          <option value="SALIDA">Salida</option>
        </Select>
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Cuando</th>
              <th>Tipo</th>
              <th>Origen</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Puesto</th>
              <th>Granja</th>
              <th>Empresa</th>
            </tr>
          </thead>
          <tbody>
            {(list.data?.items ?? []).map((r) => (
              <tr key={r.id}>
                <td>{r.marcado_en?.replace('T', ' ').slice(0, 16)}</td>
                <td>{r.tipo}</td>
                <td>{r.origen}</td>
                <td>{r.dni}</td>
                <td>
                  {r.apellidos}, {r.nombres}
                </td>
                <td>{r.puesto_cod || r.cargo}</td>
                <td>{r.granja_cod || '—'}</td>
                <td>{r.empresa_razon_social || '—'}</td>
              </tr>
            ))}
            {(list.data?.items.length ?? 0) === 0 ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Sin marcaciones
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>
      <Modal open={open} title="Marcación" onClose={() => setOpen(false)}>
        <div className="grid gap-3">
          <Select value={form.personal_id} onChange={(e) => setForm((f) => ({ ...f, personal_id: e.target.value }))}>
            <option value="">Personal</option>
            {(personal.data?.items ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.dni} · {p.apellidos}, {p.nombres}
              </option>
            ))}
          </Select>
          <Select value={form.tipo} onChange={(e) => setForm((f) => ({ ...f, tipo: e.target.value }))}>
            <option value="ENTRADA">Entrada</option>
            <option value="SALIDA">Salida</option>
          </Select>
          <Input type="datetime-local" value={form.marcado_en} onChange={(e) => setForm((f) => ({ ...f, marcado_en: e.target.value }))} />
          <Select value={form.puesto_cod} onChange={(e) => setForm((f) => ({ ...f, puesto_cod: e.target.value }))}>
            <option value="">Puesto</option>
            {(puestos.data?.items ?? []).map((p) => (
              <option key={p.codigo} value={p.codigo}>
                {p.nombre}
              </option>
            ))}
          </Select>
          <Input placeholder="Granja" value={form.granja_cod} onChange={(e) => setForm((f) => ({ ...f, granja_cod: e.target.value }))} />
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
