import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { SuccessDialog } from '../../shared/ui/SuccessDialog'
import { useAuth } from '../auth/useAuth'
import { Navigate } from 'react-router-dom'

type CargoReqRow = { cargo: string; cantidad: number }

type Punto = {
  id: number
  granja_cod: string
  codigo: string
  nombre: string
  tipo_cod: string
  ubicacion: string
  estado: string
  personal_dia?: number
  personal_noche?: number
  cargos_dia?: string
  cargos_noche?: string
}

type PuntoDetalle = {
  punto: Punto
  turnos: { turno_cod: string; personal_requerido: number }[]
  cargos_requeridos?: { turno_cod: string; cargo: string; cantidad: number }[]
  capacitacion_ids: number[]
}

type FormState = {
  nombre: string
  tipo_cod: string
  ubicacion: string
  estado: string
  personal_dia: number
  personal_noche: number
  detallar: boolean
  cargos_dia: CargoReqRow[]
  cargos_noche: CargoReqRow[]
  cap_ids: number[]
}

const emptyForm = (): FormState => ({
  nombre: '',
  tipo_cod: 'GARITA',
  ubicacion: '',
  estado: 'ACTIVO',
  personal_dia: 1,
  personal_noche: 0,
  detallar: false,
  cargos_dia: [{ cargo: '', cantidad: 1 }],
  cargos_noche: [{ cargo: '', cantidad: 1 }],
  cap_ids: [],
})

function cargosFromDetalle(
  turno: 'DIA' | 'NOCHE',
  cargos: PuntoDetalle['cargos_requeridos'],
  turnos: PuntoDetalle['turnos'],
): CargoReqRow[] {
  void turnos
  const rows = (cargos ?? []).filter((c) => c.turno_cod === turno)
  if (rows.length > 0) {
    return rows.map((c) => ({ cargo: c.cargo, cantidad: c.cantidad }))
  }
  return [{ cargo: '', cantidad: 1 }]
}

function buildCargosRequeridos(form: FormState): { turno_cod: string; cargo: string; cantidad: number }[] {
  const out: { turno_cod: string; cargo: string; cantidad: number }[] = []
  for (const row of form.cargos_dia) {
    const cargo = row.cargo.trim()
    if (!cargo) continue
    out.push({ turno_cod: 'DIA', cargo, cantidad: Math.max(1, Math.min(99, row.cantidad)) })
  }
  for (const row of form.cargos_noche) {
    const cargo = row.cargo.trim()
    if (!cargo) continue
    out.push({ turno_cod: 'NOCHE', cargo, cantidad: Math.max(1, Math.min(99, row.cantidad)) })
  }
  return out
}

export function PuntosPage() {
  const { user, isLoading } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [granjaSel, setGranjaSel] = useState('')
  const [modal, setModal] = useState<'create' | 'edit' | 'copy' | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [exito, setExito] = useState<string | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [origenCopia, setOrigenCopia] = useState('')
  const qc = useQueryClient()

  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })

  const itemsGranja = useMemo(() => granjas.data?.items ?? [], [granjas.data])
  const activeGranja = granjaSel || itemsGranja[0]?.codigo || ''

  useEffect(() => {
    if (!granjaSel && itemsGranja[0]) setGranjaSel(itemsGranja[0].codigo)
  }, [granjaSel, itemsGranja])

  const tipos = useQuery({
    queryKey: ['puntos-tipos'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/puntos/tipos'),
  })

  const caps = useQuery({
    queryKey: ['capacitaciones'],
    queryFn: () => api.get<{ items: { id: number; nombre: string }[] }>('/capacitaciones'),
  })

  const puntos = useQuery({
    queryKey: ['puntos', activeGranja],
    queryFn: () => api.get<{ items: Punto[] }>('/puntos', { granja_cod: activeGranja }),
    enabled: activeGranja !== '',
  })

  const detalle = useQuery({
    queryKey: ['punto', editId],
    queryFn: () => api.get<PuntoDetalle>(`/puntos/${editId}`),
    enabled: editId !== null && modal === 'edit',
  })

  useEffect(() => {
    const d = detalle.data
    if (modal !== 'edit' || !d) return
    setForm({
      nombre: d.punto.nombre,
      tipo_cod: d.punto.tipo_cod,
      ubicacion: d.punto.ubicacion,
      estado: d.punto.estado,
      personal_dia: d.turnos.find((t) => t.turno_cod === 'DIA')?.personal_requerido ?? 0,
      personal_noche: d.turnos.find((t) => t.turno_cod === 'NOCHE')?.personal_requerido ?? 0,
      detallar: (d.cargos_requeridos ?? []).length > 0,
      cargos_dia: cargosFromDetalle('DIA', d.cargos_requeridos, d.turnos),
      cargos_noche: cargosFromDetalle('NOCHE', d.cargos_requeridos, d.turnos),
      cap_ids: d.capacitacion_ids,
    })
  }, [detalle.data, modal])

  const bodyFromForm = () => {
    const cargos_requeridos = form.detallar ? buildCargosRequeridos(form) : []
    const sum = (rows: CargoReqRow[]) =>
      form.detallar ? rows.reduce((n, r) => (r.cargo.trim() ? n + Math.max(1, r.cantidad) : n), 0) : 0
    return {
      nombre: form.nombre,
      tipo_cod: form.tipo_cod,
      ubicacion: form.ubicacion,
      estado: form.estado,
      cargos_requeridos,
      turnos: [
        { turno_cod: 'DIA', personal_requerido: Math.max(form.personal_dia || 0, sum(form.cargos_dia)) },
        { turno_cod: 'NOCHE', personal_requerido: Math.max(form.personal_noche || 0, sum(form.cargos_noche)) },
      ],
      capacitacion_ids: form.cap_ids,
    }
  }

  const guardar = useMutation({
    mutationFn: async () => {
      const body = bodyFromForm()
      if (modal === 'edit' && editId) return api.patch(`/puntos/${editId}`, body)
      return api.post('/puntos', { ...body, granja_cod: activeGranja })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['puntos'] })
      setExito(modal === 'edit' ? 'Punto actualizado correctamente' : 'Punto registrado correctamente')
      setModal(null)
      setEditId(null)
    },
  })

  const desactivar = useMutation({
    mutationFn: (id: number) => api.post(`/puntos/${id}/desactivar`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['puntos'] }),
  })

  const copiar = useMutation({
    mutationFn: () => api.post(`/granjas/${activeGranja}/puntos/copiar`, { granja_origen_cod: origenCopia }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['puntos'] })
      setModal(null)
    },
  })

  if (!isLoading && user?.actor === 'empresa') {
    return <Navigate to="/" replace />
  }

  return (
    <div className="bio-split">
      <GranjaAside items={itemsGranja} active={activeGranja} onSelect={setGranjaSel} />

      <div className="bio-split-body">
        <PuntosHeader
          readOnly={!isGrs}
          onCopy={isGrs ? () => setModal('copy') : undefined}
          onNew={isGrs ? () => { setForm(emptyForm()); setModal('create') } : undefined}
        />

        <PuntosTable
          loading={puntos.isLoading}
          items={puntos.data?.items ?? []}
          readOnly={!isGrs}
          onEdit={
            isGrs
              ? (p: Punto) => {
                  setEditId(p.id)
                  setModal('edit')
                }
              : undefined
          }
          onDesactivar={isGrs ? (id: number) => desactivar.mutate(id) : undefined}
        />
      </div>

      <Modal open={modal === 'create' || modal === 'edit'} title={modal === 'edit' ? 'Editar punto' : 'Nuevo punto'} onClose={() => setModal(null)} wide>
        {modal === 'edit' && detalle.isLoading ? (
          <p style={{ fontSize: '0.8125rem', color: 'var(--ix2-muted)' }}>Cargando…</p>
        ) : (
          <PuntoFormFields form={form} setForm={setForm} tipos={tipos.data?.items ?? []} caps={caps.data?.items ?? []} />
        )}
        <Button className="mt-4" onClick={() => guardar.mutate()} disabled={guardar.isPending}>
          Guardar
        </Button>
        {guardar.isError && <p style={{ marginTop: '0.5rem', fontSize: '0.8125rem', color: '#b91c1c' }}>{(guardar.error as Error).message}</p>}
      </Modal>

      <SuccessDialog message={exito} onClose={() => setExito(null)} />
      <Modal open={modal === 'copy'} title="Copiar puntos" onClose={() => setModal(null)}>
        <label className="block text-sm">
          Granja origen
          <Select className="mt-1" value={origenCopia} onChange={(e) => setOrigenCopia(e.target.value)}>
            <option value="">Seleccione</option>
            {itemsGranja.filter((g) => g.codigo !== activeGranja).map((g) => (
              <option key={g.codigo} value={g.codigo}>
                {g.codigo} — {g.nombre}
              </option>
            ))}
          </Select>
        </label>
        <Button className="mt-3" disabled={!origenCopia} onClick={() => copiar.mutate()}>
          Copiar puntos activos
        </Button>
      </Modal>
    </div>
  )
}

function GranjaAside({ items, active, onSelect }: { items: { codigo: string; nombre: string }[]; active: string; onSelect: (c: string) => void }) {
  return (
    <div className="bio-split-nav bio-card" style={{ padding: '0.5rem 0.4rem' }}>
      <h2 style={{ margin: '0 0 0.5rem', padding: '0 0.35rem', fontSize: '0.8125rem', fontWeight: 700 }}>Granjas</h2>
      <ul style={{ listStyle: 'none', margin: 0, padding: 0, maxHeight: '70vh', overflow: 'auto' }}>
        {items.map((g) => (
          <li key={g.codigo}>
            <button
              type="button"
              className={`bio-granja-item ${g.codigo === active ? 'active' : ''}`}
              onClick={() => onSelect(g.codigo)}
            >
              <span className="bio-granja-code">{g.codigo}</span>
              {g.nombre}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

function PuntosHeader({
  readOnly,
  onCopy,
  onNew,
}: {
  readOnly?: boolean
  onCopy?: () => void
  onNew?: () => void
}) {
  if (readOnly || !onCopy || !onNew) {
    return null
  }
  return (
    <PageHeader
      actions={
        <>
          <Button variant="ghost" onClick={onCopy}>
            <i className="fa-solid fa-copy" aria-hidden /> Copiar
          </Button>
          <Button onClick={onNew}>
            <i className="fa-solid fa-plus" aria-hidden /> Nuevo punto
          </Button>
        </>
      }
    />
  )
}

function PuntosTable({
  loading,
  items,
  readOnly,
  onEdit,
  onDesactivar,
}: {
  loading: boolean
  items: Punto[]
  readOnly?: boolean
  onEdit?: (p: Punto) => void
  onDesactivar?: (id: number) => void
}) {
  return (
    <div className="bio-card bio-table-wrap">
      <table className="bio-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th style={{ textAlign: 'center' }}>Día</th>
            <th style={{ textAlign: 'center' }}>Noche</th>
            <th>Estado</th>
            {!readOnly ? <th /> : null}
          </tr>
        </thead>
        <tbody>
          {loading ? (
            <tr>
              <td colSpan={readOnly ? 5 : 6} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                Cargando…
              </td>
            </tr>
          ) : (
            items.map((p) => (
              <tr key={p.id}>
                <td>{p.nombre}</td>
                <td>{p.tipo_cod}</td>
                <td style={{ fontSize: '0.75rem' }}>
                  {Number(p.personal_dia ?? 0) > 0 ? (
                    <span className="bio-badge bio-badge-dia">
                      <i className="fa-solid fa-sun" aria-hidden /> {p.personal_dia}
                    </span>
                  ) : (
                    <span style={{ color: 'var(--ix2-muted)' }}>—</span>
                  )}
                  {p.cargos_dia?.trim() ? <div style={{ color: 'var(--ix2-muted)', marginTop: '0.15rem' }}>{p.cargos_dia}</div> : null}
                </td>
                <td style={{ fontSize: '0.75rem' }}>
                  {Number(p.personal_noche ?? 0) > 0 ? (
                    <span className="bio-badge bio-badge-noche">
                      <i className="fa-solid fa-moon" aria-hidden /> {p.personal_noche}
                    </span>
                  ) : (
                    <span style={{ color: 'var(--ix2-muted)' }}>—</span>
                  )}
                  {p.cargos_noche?.trim() ? <div style={{ color: 'var(--ix2-muted)', marginTop: '0.15rem' }}>{p.cargos_noche}</div> : null}
                </td>
                <td>
                  <span className={`bio-badge ${p.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-warn'}`}>{p.estado}</span>
                </td>
                {!readOnly && onEdit && onDesactivar ? (
                  <td style={{ textAlign: 'right' }}>
                    <Button variant="ghost" size="sm" onClick={() => onEdit(p)}>
                      Editar
                    </Button>
                    {p.estado === 'ACTIVO' && (
                      <Button variant="ghost" size="sm" onClick={() => onDesactivar(p.id)}>
                        Desactivar
                      </Button>
                    )}
                  </td>
                ) : null}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}

function PuntoFormFields({
  form,
  setForm,
  tipos,
  caps,
}: {
  form: FormState
  setForm: React.Dispatch<React.SetStateAction<FormState>>
  tipos: { codigo: string; nombre: string }[]
  caps: { id: number; nombre: string }[]
}) {
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <label className="text-sm">
        Nombre
        <Input className="mt-1" value={form.nombre} onChange={(e) => setForm((s) => ({ ...s, nombre: e.target.value }))} />
      </label>
      <label className="text-sm">
        Tipo
        <Select className="mt-1" value={form.tipo_cod} onChange={(e) => setForm((s) => ({ ...s, tipo_cod: e.target.value }))}>
          {tipos.filter((t) => t.codigo !== 'BALANZA').map((t) => (
            <option key={t.codigo} value={t.codigo}>
              {t.nombre}
            </option>
          ))}
        </Select>
      </label>
      <label className="text-sm">
        Estado
        <Select className="mt-1" value={form.estado} onChange={(e) => setForm((s) => ({ ...s, estado: e.target.value }))}>
          <option value="ACTIVO">Activo</option>
          <option value="INACTIVO">Inactivo</option>
        </Select>
      </label>
      <div className="sm:col-span-2" style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
        <label className="text-sm">
          <span className="bio-badge bio-badge-dia">
            <i className="fa-solid fa-sun" aria-hidden /> Personas turno día
          </span>
          <Input
            type="number"
            min={0}
            max={99}
            className="mt-1"
            style={{ width: '7rem' }}
            value={form.personal_dia}
            onChange={(e) => setForm((s) => ({ ...s, personal_dia: Math.max(0, Number(e.target.value)) }))}
          />
        </label>
        <label className="text-sm">
          <span className="bio-badge bio-badge-noche">
            <i className="fa-solid fa-moon" aria-hidden /> Personas turno noche
          </span>
          <Input
            type="number"
            min={0}
            max={99}
            className="mt-1"
            style={{ width: '7rem' }}
            value={form.personal_noche}
            onChange={(e) => setForm((s) => ({ ...s, personal_noche: Math.max(0, Number(e.target.value)) }))}
          />
        </label>
        <label className="bio-switch" style={{ alignSelf: 'flex-end', marginLeft: 'auto' }}>
          <input type="checkbox" checked={form.detallar} onChange={(e) => setForm((s) => ({ ...s, detallar: e.target.checked }))} />
          Detallar por cargo (opcional)
        </label>
      </div>
      {form.detallar ? (
        <>
          <CargoReqEditor
            title="Cargos turno día"
            rows={form.cargos_dia}
            onChange={(cargos_dia) => setForm((s) => ({ ...s, cargos_dia }))}
          />
          <CargoReqEditor
            title="Cargos turno noche"
            rows={form.cargos_noche}
            onChange={(cargos_noche) => setForm((s) => ({ ...s, cargos_noche }))}
          />
        </>
      ) : null}
      <fieldset className="sm:col-span-2 text-sm">
        <legend className="mb-1">Capacitaciones obligatorias</legend>
        <CapChecklist caps={caps} form={form} setForm={setForm} />
      </fieldset>
    </div>
  )
}

function CargoReqEditor({
  title,
  rows,
  onChange,
}: {
  title: string
  rows: CargoReqRow[]
  onChange: (rows: CargoReqRow[]) => void
}) {
  const total = rows.reduce((n, r) => (r.cargo.trim() ? n + Math.max(1, r.cantidad) : n), 0)
  return (
    <div className="sm:col-span-2 text-sm">
      <div className="flex items-baseline justify-between gap-2">
        <span className="font-medium">{title}</span>
        <span className="text-xs text-[var(--ix2-muted)]">Total: {total || '—'}</span>
      </div>
      <ul style={{ listStyle: 'none', margin: '0.35rem 0 0', padding: 0 }} className="flex flex-col gap-2">
        {rows.map((row, i) => (
          <li key={i} className="flex flex-wrap items-center gap-2">
            <Input
              placeholder="Cargo"
              value={row.cargo}
              onChange={(e) => {
                const next = [...rows]
                next[i] = { ...row, cargo: e.target.value }
                onChange(next)
              }}
              style={{ flex: '1 1 10rem', minWidth: '8rem' }}
            />
            <Input
              type="number"
              min={1}
              max={99}
              value={row.cantidad}
              onChange={(e) => {
                const next = [...rows]
                next[i] = { ...row, cantidad: Number(e.target.value) }
                onChange(next)
              }}
              style={{ width: '4.5rem' }}
            />
            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={rows.length <= 1}
              onClick={() => onChange(rows.filter((_, j) => j !== i))}
            >
              Quitar
            </Button>
          </li>
        ))}
      </ul>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="mt-2"
        onClick={() => onChange([...rows, { cargo: '', cantidad: 1 }])}
      >
        <i className="fa-solid fa-plus" aria-hidden /> Agregar cargo
      </Button>
    </div>
  )
}

function CapChecklist({
  caps,
  form,
  setForm,
}: {
  caps: { id: number; nombre: string }[]
  form: FormState
  setForm: React.Dispatch<React.SetStateAction<FormState>>
}) {
  return (
    <div className="flex flex-wrap gap-2">
      {caps.map((c) => {
        const checked = form.cap_ids.includes(c.id)
        return (
          <label key={c.id} className="flex items-center gap-1 rounded border border-[var(--color-bio-border)] px-2 py-1">
            <input
              type="checkbox"
              checked={checked}
              onChange={() =>
                setForm((s) => ({
                  ...s,
                  cap_ids: checked ? s.cap_ids.filter((x) => x !== c.id) : [...s.cap_ids, c.id],
                }))
              }
            />
            {c.nombre}
          </label>
        )
      })}
    </div>
  )
}
