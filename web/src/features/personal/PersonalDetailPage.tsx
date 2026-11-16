import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Modal } from '../../shared/ui/Modal'
import { PersonalForm, type PersonalSubmitPayload } from './PersonalForm'
import type { Personal } from './types'
import { etiquetaEmpresaPersonal } from './types'
import { useState } from 'react'
import { Input, Select } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { SuccessDialog } from '../../shared/ui/SuccessDialog'
import { PersonalAvatar } from '../../shared/ui/PersonalAvatar'
import { useAuth } from '../auth/useAuth'

export function PersonalDetailPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const { id } = useParams()
  const pid = Number(id)
  const [edit, setEdit] = useState(false)
  const [exito, setExito] = useState<string | null>(null)
  const [capOpen, setCapOpen] = useState(false)
  const qc = useQueryClient()

  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })

  const ficha = useQuery({
    queryKey: ['personal', pid, 'ficha'],
    queryFn: () =>
      api.get<{
        personal: Personal
        antiguedad_dias: number
        capacitaciones: unknown[]
        asignaciones: unknown[]
      }>(`/personal/${pid}/ficha`),
    enabled: pid > 0,
  })

  const caps = useQuery({
    queryKey: ['capacitaciones'],
    queryFn: () =>
      api.get<{ items: { id: number; nombre: string; vigencia_meses?: number }[]; permisos?: { registro_editar: boolean } }>(
        '/capacitaciones',
      ),
  })

  const puedeRegistrarCap = !isGrs && (caps.data?.permisos?.registro_editar ?? true)

  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string; estado: string }[] }>('/empresas'),
    enabled: isGrs,
  })

  const actualizar = useMutation({
    mutationFn: (body: PersonalSubmitPayload) => api.patch(`/personal/${pid}`, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['personal'] })
      setEdit(false)
      setExito('Personal actualizado correctamente')
    },
  })

  const foto = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData()
      fd.append('foto', file)
      return api.post(`/personal/${pid}/foto`, fd)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['personal', pid, 'ficha'] })
      setExito('Foto actualizada')
    },
  })

  const [capForm, setCapForm] = useState({
    capacitacion_id: '',
    fecha_realizada: '',
    fecha_evaluacion: '',
    fecha_vence: '',
    resultado: 'APROBADO',
    observacion: '',
  })

  const addCap = useMutation({
    mutationFn: () =>
      api.post(`/personal/${pid}/capacitaciones`, {
        capacitacion_id: Number(capForm.capacitacion_id),
        fecha_realizada: capForm.fecha_realizada,
        fecha_evaluacion: capForm.fecha_evaluacion || capForm.fecha_realizada,
        fecha_vence: capForm.fecha_vence || null,
        resultado: capForm.resultado,
        observacion: capForm.observacion,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['personal', pid, 'ficha'] })
      qc.invalidateQueries({ queryKey: ['cap-reg-emp'] })
      setCapOpen(false)
      setExito('Capacitación registrada')
    },
  })

  const p = ficha.data?.personal
  if (ficha.isLoading) return <p className="text-sm text-[var(--color-bio-muted)]">Cargando ficha…</p>
  if (!p) return <p className="text-sm text-red-600">No encontrado</p>

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${p.apellidos}, ${p.nombres}`}
        description={`DNI ${p.dni} · Antigüedad ${ficha.data?.antiguedad_dias ?? 0} días`}
        actions={
          <Button variant="ghost" onClick={() => setEdit(true)}>
            Editar
          </Button>
        }
      />

      <div className="grid gap-4 lg:grid-cols-[200px_1fr]">
        <div className="rounded-lg border border-[var(--color-bio-border)] bg-[var(--color-bio-surface)] p-3 text-center">
          <PersonalAvatar
            fotoRuta={p.foto_ruta}
            alt={`${p.nombres} ${p.apellidos}`}
            size={160}
            className="mx-auto rounded-md object-cover ring-1 ring-[var(--color-bio-border)]"
          />
          <label className="mt-3 block cursor-pointer text-sm text-[var(--color-bio-accent)]">
            {foto.isPending ? 'Subiendo…' : 'Cambiar foto'}
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              disabled={foto.isPending}
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) foto.mutate(file)
                e.target.value = ''
              }}
            />
          </label>
        </div>
        <div className="space-y-4">
          <section className="rounded-lg border border-[var(--color-bio-border)] bg-[var(--color-bio-surface)] p-4 text-sm">
            <dl className="grid gap-2 sm:grid-cols-2">
              <div>
                <dt className="text-[var(--color-bio-muted)]">Cargo</dt>
                <dd>{p.cargo}</dd>
              </div>
              <div>
                <dt className="text-[var(--color-bio-muted)]">Contratista</dt>
                <dd>{etiquetaEmpresaPersonal(p)}</dd>
              </div>
              <div>
                <dt className="text-[var(--color-bio-muted)]">Estado</dt>
                <dd>{p.estado}</dd>
              </div>
              <div>
                <dt className="text-[var(--color-bio-muted)]">Granja base</dt>
                <dd>{p.granja_base_cod ? ((g) => (g ? `${g.codigo} - ${g.nombre}` : p.granja_base_cod))(granjas.data?.items.find((x) => x.codigo === p.granja_base_cod)) : '—'}</dd>
              </div>
            </dl>
          </section>

          <section>
            <div className="mb-2 flex items-center justify-between gap-2">
              <h3 className="text-sm font-medium">Capacitaciones</h3>
              {puedeRegistrarCap ? (
                <Button size="sm" onClick={() => setCapOpen(true)}>
                  Registrar
                </Button>
              ) : isGrs ? (
                <span style={{ fontSize: '0.75rem', color: 'var(--ix2-muted)' }}>Registro a cargo del contratista</span>
              ) : null}
            </div>
            <ul className="divide-y divide-[var(--color-bio-border)] rounded-lg border border-[var(--color-bio-border)] bg-[var(--color-bio-surface)] text-sm">
              {(ficha.data?.capacitaciones as {
                id: number
                codigo: string
                nombre: string
                fecha_realizada: string
                fecha_evaluacion: string | null
                fecha_vence: string | null
                resultado: string
              }[]).length === 0 ? (
                <li className="px-3 py-4 text-[var(--color-bio-muted)]">Sin registros</li>
              ) : (
                (ficha.data?.capacitaciones as {
                  id: number
                  codigo: string
                  nombre: string
                  fecha_realizada: string
                  fecha_evaluacion: string | null
                  fecha_vence: string | null
                  resultado: string
                }[]).map((c) => {
                  const hoy = todayIso()
                  const vencida = c.fecha_vence != null && c.fecha_vence !== '' && c.fecha_vence < hoy
                  return (
                    <li key={c.id} className="px-3 py-2">
                      <span className="font-medium">
                        {c.codigo} — {c.nombre}
                      </span>
                      <span className="text-[var(--color-bio-muted)]">
                        {' '}
                        · realizada {formatDate(c.fecha_realizada)}
                        {c.fecha_evaluacion ? ` · eval. ${formatDate(c.fecha_evaluacion)}` : ''}
                        {c.fecha_vence ? ` · vence ${formatDate(c.fecha_vence)}` : ''}
                        {' · '}
                        {c.resultado}
                      </span>
                      {vencida ? <span className="bio-badge bio-badge-warn" style={{ marginLeft: '0.35rem' }}>Vencida</span> : null}
                    </li>
                  )
                })
              )}
            </ul>
          </section>

          <section>
            <h3 className="mb-2 text-sm font-medium">Historial de asignaciones</h3>
            <ul className="max-h-64 overflow-auto rounded-lg border border-[var(--color-bio-border)] bg-[var(--color-bio-surface)] text-sm">
              {(ficha.data?.asignaciones as { fecha: string; turno_cod: string; punto_nombre: string; granja_cod: string }[]).length === 0 ? (
                <li className="px-3 py-4 text-[var(--color-bio-muted)]">Sin asignaciones aún</li>
              ) : (
                (ficha.data?.asignaciones as { fecha: string; turno_cod: string; punto_nombre: string; granja_cod: string }[]).map((a, i) => (
                  <li key={i} className="border-t border-[var(--color-bio-border)] px-3 py-2 first:border-0">
                    {formatDate(a.fecha)} · {a.turno_cod} · {a.granja_cod} — {a.punto_nombre}
                  </li>
                ))
              )}
            </ul>
          </section>
        </div>
      </div>

      <Modal open={edit} title="Editar trabajador" onClose={() => setEdit(false)} wide>
        <PersonalForm
          initial={p}
          granjas={granjas.data?.items ?? []}
          empresas={empresas.data?.items}
          permitirContratista={isGrs}
          onSubmit={(v) => actualizar.mutate(v)}
          loading={actualizar.isPending}
        />
      </Modal>

      <SuccessDialog message={exito} onClose={() => setExito(null)} />
      <Modal open={capOpen} title="Capacitación / evaluación" onClose={() => setCapOpen(false)}>
        <div className="space-y-3">
          <label className="block text-sm">
            Capacitación
            <Select className="mt-1" value={capForm.capacitacion_id} onChange={(e) => setCapForm((s) => ({ ...s, capacitacion_id: e.target.value }))}>
              <option value="">Seleccione</option>
              {caps.data?.items.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
            </Select>
          </label>
          <label className="block text-sm">
            Fecha realizada
            <Input className="mt-1" type="date" value={capForm.fecha_realizada} onChange={(e) => setCapForm((s) => ({ ...s, fecha_realizada: e.target.value }))} />
          </label>
          <label className="block text-sm">
            Fecha evaluación
            <Input className="mt-1" type="date" value={capForm.fecha_evaluacion} onChange={(e) => setCapForm((s) => ({ ...s, fecha_evaluacion: e.target.value }))} />
          </label>
          <label className="block text-sm">
            Resultado
            <Select className="mt-1" value={capForm.resultado} onChange={(e) => setCapForm((s) => ({ ...s, resultado: e.target.value }))}>
              <option value="APROBADO">Aprobado</option>
              <option value="DESAPROBADO">Desaprobado</option>
              <option value="PENDIENTE">Pendiente</option>
            </Select>
          </label>
          <label className="block text-sm">
            Fecha vence (opcional; si vacío se calcula según vigencia del catálogo)
            <Input className="mt-1" type="date" value={capForm.fecha_vence} onChange={(e) => setCapForm((s) => ({ ...s, fecha_vence: e.target.value }))} />
          </label>
          <label className="block text-sm">
            Observación
            <Input className="mt-1" value={capForm.observacion} onChange={(e) => setCapForm((s) => ({ ...s, observacion: e.target.value }))} />
          </label>
          <Button onClick={() => addCap.mutate()} disabled={!capForm.capacitacion_id || !capForm.fecha_realizada || addCap.isPending}>
            Guardar
          </Button>
        </div>
      </Modal>
    </div>
  )
}
