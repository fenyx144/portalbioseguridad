import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { EmptyState, PageHero } from '../../shared/ui/Kpi'
import { useAuth } from '../auth/useAuth'

type Granja = { codigo: string; nombre: string; g3: string }
type Persona = { dni: string; nombre: string; activo: number }
type Vehiculo = { placa: string; descripcion: string; activo: number }
type Visita = {
  id: number
  fecha: string
  granja: string
  granja_nombre: string
  proceso: string
  num_personas: number
  personas: { dni: string; nombre: string }[]
  placas: string[]
  razon: string
  usuario: string
}
type Contexto = {
  rol: 'grs' | 'empresa'
  ruc: string
  razon: string
  proceso: string
  empresa_id: number
  granjas: Granja[]
  personal: Persona[]
  vehiculos: Vehiculo[]
  empresas: { id: number; codigo: string; razon_social: string; ruc: string }[]
}

function addDays(iso: string, n: number) {
  const d = new Date(iso + 'T12:00:00')
  d.setDate(d.getDate() + n)
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${m}-${day}`
}

export function ProveedorPlanPage() {
  const { user } = useAuth()
  const esGrs = (user?.actor ?? 'grs') !== 'empresa'
  const qc = useQueryClient()
  const [tab, setTab] = useState<'visitas' | 'personal' | 'vehiculos'>('visitas')
  const [empresaId, setEmpresaId] = useState('')
  const [desde, setDesde] = useState(todayIso())
  const [hasta, setHasta] = useState(addDays(todayIso(), 30))
  const [granja, setGranja] = useState('')
  const [aviso, setAviso] = useState<{ ok: boolean; msg: string } | null>(null)

  const ctx = useQuery({
    queryKey: ['proveedor-plan', 'ctx', empresaId],
    queryFn: () => api.get<Contexto>('/proveedor/plan/contexto', esGrs ? { empresa_id: empresaId } : undefined),
  })
  const visitas = useQuery({
    queryKey: ['proveedor-plan', 'lista', empresaId, desde, hasta, granja],
    queryFn: () =>
      api.get<{ items: Visita[] }>('/proveedor/plan', {
        empresa_id: esGrs ? empresaId : undefined,
        desde,
        hasta,
        granja,
      }),
  })

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['proveedor-plan'] })
  }
  const mut = useMutation({
    mutationFn: (fn: () => Promise<unknown>) => fn(),
    onSuccess: () => {
      setAviso({ ok: true, msg: 'Registrado' })
      invalidar()
    },
    onError: (e) => setAviso({ ok: false, msg: e instanceof ApiClientError ? e.message : 'Error' }),
  })

  const scope = esGrs && empresaId ? { empresa_id: Number(empresaId) } : {}
  const personal = (ctx.data?.personal ?? []).filter((p) => p.activo === 1)
  const flota = (ctx.data?.vehiculos ?? []).filter((v) => v.activo === 1)
  const granjas = ctx.data?.granjas ?? []
  const nombreGranja = useMemo(() => {
    const map = new Map(granjas.map((g) => [g.g3, `${g.codigo} - ${g.nombre}`]))
    return (cod: string, nombre: string) => map.get(cod) || (nombre ? `${cod} - ${nombre}` : cod)
  }, [granjas])

  return (
    <div className="bio-almacen" style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-truck-field"
        title="Planificación del proveedor"
        subtitle={ctx.data?.razon || (user?.actor === 'empresa' ? user.empresa_nombre : undefined)}
      />
      <div className="bio-tabs" role="tablist">
        {(
          [
            ['visitas', 'Visitas'],
            ['personal', 'Personal'],
            ['vehiculos', 'Vehículos'],
          ] as const
        ).map(([id, label]) => (
          <button key={id} type="button" role="tab" className={`bio-tab${tab === id ? ' bio-tab--on' : ''}`} onClick={() => setTab(id)}>
            {label}
          </button>
        ))}
      </div>

      {esGrs ? (
        <div className="bio-card bio-filter-bar">
          <label style={{ flex: '1 1 18rem' }}>
            <span className="bio-label">Proveedor</span>
            <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
              <option value="">Todos</option>
              {(ctx.data?.empresas ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.codigo} - {e.razon_social}
                </option>
              ))}
            </select>
          </label>
        </div>
      ) : null}

      {tab === 'visitas' ? (
        <>
          <div className="bio-card bio-filter-bar">
            <label>
              <span className="bio-label">Desde</span>
              <input className="bio-input" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </label>
            <label>
              <span className="bio-label">Hasta</span>
              <input className="bio-input" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </label>
            <label style={{ flex: '1 1 14rem' }}>
              <span className="bio-label">Granja</span>
              <select className="bio-select" value={granja} onChange={(e) => setGranja(e.target.value)}>
                <option value="">Todas</option>
                {granjas.map((g) => (
                  <option key={g.codigo} value={g.g3 || g.codigo}>
                    {g.codigo} - {g.nombre}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <NuevaVisita
            key={ctx.data?.proceso || 'nueva'}
            granjas={granjas}
            personal={personal}
            vehiculos={flota}
            proceso={ctx.data?.proceso ?? ''}
            disabled={esGrs && !empresaId}
            pending={mut.isPending}
            onSave={(body) => mut.mutate(() => api.post('/proveedor/plan', { ...scope, ...body }))}
          />
          <div className="bio-card" style={{ padding: 0 }}>
            {(visitas.data?.items ?? []).length === 0 ? (
              <EmptyState icon="fa-calendar-day">{visitas.isLoading ? 'Cargando…' : 'Sin visitas'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Granja</th>
                      {esGrs && !empresaId ? <th>Proveedor</th> : null}
                      <th>Proceso</th>
                      <th className="num">Personas</th>
                      <th>Personal</th>
                      <th>Placas</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {(visitas.data?.items ?? []).map((v) => (
                      <tr key={v.id}>
                        <td>{formatDate(v.fecha)}</td>
                        <td>{nombreGranja(v.granja, v.granja_nombre)}</td>
                        {esGrs && !empresaId ? <td>{v.razon}</td> : null}
                        <td>{v.proceso || '—'}</td>
                        <td className="num">{v.num_personas}</td>
                        <td>{v.personas.map((p) => p.nombre || p.dni).join(', ') || '—'}</td>
                        <td>{v.placas.join(', ') || '—'}</td>
                        <td>
                          <button
                            type="button"
                            className="bio-icon-action"
                            title="Eliminar"
                            onClick={() =>
                              mut.mutate(() =>
                                api.del(`/proveedor/plan/${v.id}${esGrs && empresaId ? `?empresa_id=${empresaId}` : ''}`),
                              )
                            }
                          >
                            <i className="fa-solid fa-trash" aria-hidden />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      ) : null}

      {tab === 'personal' ? (
        <Nomina
          titulo="Personal"
          disabled={esGrs && !empresaId}
          pending={mut.isPending}
          filas={(ctx.data?.personal ?? []).map((p) => ({ id: p.dni, a: p.dni, b: p.nombre, activo: p.activo }))}
          campos={[
            { key: 'dni', label: 'DNI' },
            { key: 'nombre', label: 'Nombre' },
          ]}
          onAdd={(f) => mut.mutate(() => api.post('/proveedor/personal', { ...scope, dni: f.dni, nombre: f.nombre }))}
          onBaja={(id) => mut.mutate(() => api.post('/proveedor/personal/baja', { ...scope, dni: id }))}
        />
      ) : null}

      {tab === 'vehiculos' ? (
        <Nomina
          titulo="Vehículos"
          disabled={esGrs && !empresaId}
          pending={mut.isPending}
          filas={(ctx.data?.vehiculos ?? []).map((v) => ({ id: v.placa, a: v.placa, b: v.descripcion, activo: v.activo }))}
          campos={[
            { key: 'placa', label: 'Placa' },
            { key: 'descripcion', label: 'Descripción' },
          ]}
          onAdd={(f) => mut.mutate(() => api.post('/proveedor/vehiculos', { ...scope, placa: f.placa, descripcion: f.descripcion }))}
          onBaja={(id) => mut.mutate(() => api.post('/proveedor/vehiculos/baja', { ...scope, placa: id }))}
        />
      ) : null}

      {aviso ? <span className={`bio-badge ${aviso.ok ? 'bio-badge-ok' : 'bio-badge-danger'}`}>{aviso.msg}</span> : null}
    </div>
  )
}

function NuevaVisita({
  granjas,
  personal,
  vehiculos,
  proceso,
  disabled,
  pending,
  onSave,
}: {
  granjas: Granja[]
  personal: Persona[]
  vehiculos: Vehiculo[]
  proceso: string
  disabled: boolean
  pending: boolean
  onSave: (body: Record<string, unknown>) => void
}) {
  const [gSel, setGSel] = useState<string[]>([])
  const [pSel, setPSel] = useState<string[]>([])
  const [vSel, setVSel] = useState<string[]>([])
  const [desde, setDesde] = useState(todayIso())
  const [hasta, setHasta] = useState(todayIso())
  const [proc, setProc] = useState(proceso)
  const toggle = (list: string[], set: (v: string[]) => void, id: string) =>
    set(list.includes(id) ? list.filter((x) => x !== id) : [...list, id])

  return (
    <div className="bio-card bio-card-pad" style={{ display: 'grid', gap: '0.75rem' }}>
      <strong>Nueva visita</strong>
      <div className="bio-filter-bar" style={{ padding: 0, border: 0, background: 'transparent' }}>
        <label>
          <span className="bio-label">Desde</span>
          <input className="bio-input" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        </label>
        <label>
          <span className="bio-label">Hasta</span>
          <input className="bio-input" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        </label>
        <label style={{ flex: '1 1 14rem' }}>
          <span className="bio-label">Proceso</span>
          <input className="bio-input" value={proc} onChange={(e) => setProc(e.target.value)} />
        </label>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(14rem, 1fr))', gap: '0.75rem' }}>
        <CheckList titulo="Granjas" items={granjas.map((g) => ({ id: g.g3 || g.codigo, label: `${g.codigo} - ${g.nombre}` }))} sel={gSel} onToggle={(id) => toggle(gSel, setGSel, id)} />
        <CheckList titulo="Personal" items={personal.map((p) => ({ id: p.dni, label: `${p.dni} ${p.nombre}`.trim() }))} sel={pSel} onToggle={(id) => toggle(pSel, setPSel, id)} />
        <CheckList titulo="Vehículos" items={vehiculos.map((v) => ({ id: v.placa, label: v.descripcion ? `${v.placa} · ${v.descripcion}` : v.placa }))} sel={vSel} onToggle={(id) => toggle(vSel, setVSel, id)} />
      </div>
      <div>
        <Button
          icon="fa-check"
          disabled={disabled || pending}
          onClick={() => onSave({ granjas: gSel, dnis: pSel, placas: vSel, desde, hasta, proceso: proc, motivo: proc })}
        >
          Guardar
        </Button>
      </div>
    </div>
  )
}

function CheckList({ titulo, items, sel, onToggle }: { titulo: string; items: { id: string; label: string }[]; sel: string[]; onToggle: (id: string) => void }) {
  const [q, setQ] = useState('')
  const filas = items.filter((i) => i.label.toLowerCase().includes(q.toLowerCase()))
  return (
    <div>
      <span className="bio-label">{titulo}</span>
      <input className="bio-input" value={q} onChange={(e) => setQ(e.target.value)} style={{ margin: '0.35rem 0' }} />
      <div style={{ maxHeight: '14rem', overflow: 'auto', display: 'grid', gap: '0.25rem' }}>
        {filas.map((i) => (
          <label key={i.id} style={{ display: 'flex', gap: '0.4rem', alignItems: 'center' }}>
            <input type="checkbox" checked={sel.includes(i.id)} onChange={() => onToggle(i.id)} />
            <span>{i.label}</span>
          </label>
        ))}
      </div>
    </div>
  )
}

function Nomina({
  titulo,
  filas,
  campos,
  disabled,
  pending,
  onAdd,
  onBaja,
}: {
  titulo: string
  filas: { id: string; a: string; b: string; activo: number }[]
  campos: { key: string; label: string }[]
  disabled: boolean
  pending: boolean
  onAdd: (f: Record<string, string>) => void
  onBaja: (id: string) => void
}) {
  const [form, setForm] = useState<Record<string, string>>({})
  return (
    <div className="bio-card bio-card-pad" style={{ display: 'grid', gap: '0.75rem' }}>
      <strong>{titulo}</strong>
      <div className="bio-filter-bar" style={{ padding: 0, border: 0, background: 'transparent' }}>
        {campos.map((c) => (
          <label key={c.key}>
            <span className="bio-label">{c.label}</span>
            <input className="bio-input" value={form[c.key] ?? ''} onChange={(e) => setForm({ ...form, [c.key]: e.target.value })} />
          </label>
        ))}
        <Button icon="fa-plus" disabled={disabled || pending} onClick={() => onAdd(form)}>
          Agregar
        </Button>
      </div>
      <div className="bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              {campos.map((c) => (
                <th key={c.key}>{c.label}</th>
              ))}
              <th>Estado</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {filas.map((f) => (
              <tr key={f.id}>
                <td>{f.a}</td>
                <td>{f.b || '—'}</td>
                <td>{f.activo ? 'Activo' : 'Inactivo'}</td>
                <td>
                  {f.activo ? (
                    <button type="button" className="bio-icon-action" title="Retirar" onClick={() => onBaja(f.id)}>
                      <i className="fa-solid fa-user-minus" aria-hidden />
                    </button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
