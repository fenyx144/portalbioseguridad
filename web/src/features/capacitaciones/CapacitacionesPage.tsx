import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { ConfirmDialog } from '../../shared/ui/ConfirmDialog'
import { EmptyState, Kpi, PageHero } from '../../shared/ui/Kpi'
import { NoticeDialog } from '../../shared/ui/NoticeDialog'
import { useAuth } from '../auth/useAuth'
import { estadoSesion, useCancelarSesion, useCatalogo, useSesiones, type Sesion } from './api'
import { SesionDetalleModal } from './components/SesionDetalleModal'
import { SesionFormModal } from './components/SesionFormModal'

function rangoMes(mes: string) {
  const [y, m] = mes.split('-').map(Number)
  const last = new Date(y, m, 0).getDate()
  return { desde: `${mes}-01`, hasta: `${mes}-${String(last).padStart(2, '0')}` }
}

export function CapacitacionesPage() {
  const { user } = useAuth()
  const esGrs = user?.actor !== 'empresa'
  const [mes, setMes] = useState(() => todayIso().slice(0, 7))
  const [estado, setEstado] = useState('')
  const [empresaId, setEmpresaId] = useState('')
  const [capId, setCapId] = useState('')
  const [form, setForm] = useState<{ open: boolean; sesion?: Sesion | null }>({ open: false })
  const [detalle, setDetalle] = useState<{ id: number; tab: 'convocados' | 'asistencia' | 'evaluacion' } | null>(null)
  const [cancelar, setCancelar] = useState<Sesion | null>(null)
  const [notice, setNotice] = useState<{ msg: string; kind: 'success' | 'error' } | null>(null)

  const { desde, hasta } = rangoMes(mes)
  const sesiones = useSesiones({ desde, hasta, estado, empresa_id: empresaId, capacitacion_id: capId })
  const cat = useCatalogo()
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string }[] }>('/empresas'),
    enabled: esGrs,
  })
  const cancelarM = useCancelarSesion()
  const items = useMemo(() => sesiones.data?.items ?? [], [sesiones.data])
  const gestionar = !!sesiones.data?.puede_gestionar

  const k = items.reduce(
    (a, s) => ({
      prog: a.prog + (s.estado === 'PROGRAMADA' ? 1 : 0),
      real: a.real + (s.estado === 'REALIZADA' ? 1 : 0),
      insc: a.insc + Number(s.num_programados ?? 0),
      asis: a.asis + Number(s.num_asistieron ?? 0),
      apr: a.apr + Number(s.num_aprobados ?? 0),
    }),
    { prog: 0, real: 0, insc: 0, asis: 0, apr: 0 },
  )

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-chalkboard-user"
        title="Capacitaciones"
        subtitle={esGrs ? 'Sesiones de todas las empresas' : user?.empresa_nombre ?? undefined}
        actions={
          gestionar ? (
            <Button icon="fa-calendar-plus" onClick={() => setForm({ open: true, sesion: null })}>
              Programar
            </Button>
          ) : null
        }
      />

      <div className="bio-kpis">
        <Kpi icon="fa-calendar" label="Programadas" value={k.prog} tone="info" />
        <Kpi icon="fa-circle-check" label="Realizadas" value={k.real} />
        <Kpi icon="fa-users" label="Inscritos" value={k.insc} />
        <Kpi icon="fa-clipboard-check" label="Asistieron" value={`${k.asis}`} tone={k.insc && k.asis < k.insc ? 'warn' : undefined} />
        <Kpi icon="fa-star" label="Aprobados" value={k.apr} />
      </div>

      <div className="bio-card bio-filter-bar">
        <label>
          <span className="bio-label">Mes</span>
          <input type="month" className="bio-input" value={mes} onChange={(e) => setMes(e.target.value || todayIso().slice(0, 7))} />
        </label>
        <label style={{ flex: '1 1 12rem' }}>
          <span className="bio-label">Capacitación</span>
          <select className="bio-select" value={capId} onChange={(e) => setCapId(e.target.value)}>
            <option value="">Todas</option>
            {(cat.data?.items ?? []).map((c) => (
              <option key={c.id} value={c.id}>
                {c.nombre}
              </option>
            ))}
          </select>
        </label>
        {esGrs ? (
          <label style={{ flex: '1 1 12rem' }}>
            <span className="bio-label">Empresa</span>
            <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
              <option value="">Todas</option>
              {(empresas.data?.items ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.razon_social}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <label>
          <span className="bio-label">Estado</span>
          <select className="bio-select" value={estado} onChange={(e) => setEstado(e.target.value)}>
            <option value="">Todos</option>
            <option value="PROGRAMADA">Programada</option>
            <option value="REALIZADA">Realizada</option>
            <option value="CANCELADA">Cancelada</option>
          </select>
        </label>
      </div>

      <div className="bio-card" style={{ padding: 0 }}>
        {sesiones.isLoading ? (
          <EmptyState icon="fa-spinner fa-spin">Cargando…</EmptyState>
        ) : items.length === 0 ? (
          <EmptyState icon="fa-chalkboard-user">Sin sesiones en este periodo</EmptyState>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Capacitación</th>
                  {esGrs ? <th>Empresa</th> : null}
                  <th>Lugar</th>
                  <th>Estado</th>
                  <th className="num">Inscritos</th>
                  <th className="num">Asistieron</th>
                  <th className="num">Aprob. / Desap.</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {items.map((s) => {
                  const insc = Number(s.num_programados ?? 0)
                  const asis = Number(s.num_asistieron ?? 0)
                  return (
                    <tr key={s.id}>
                      <td style={{ whiteSpace: 'nowrap' }}>
                        <strong>{formatDate(s.fecha)}</strong>
                        {s.hora_inicio ? <span style={{ color: 'var(--ix2-muted)' }}> · {s.hora_inicio.slice(0, 5)}</span> : null}
                      </td>
                      <td>
                        {s.cap_nombre} <span className="bio-badge bio-badge-muted">{s.cap_codigo}</span>
                      </td>
                      {esGrs ? <td>{s.empresa_razon_social ?? '—'}</td> : null}
                      <td>{s.lugar || '—'}</td>
                      <td>
                        <span className={`bio-badge ${estadoSesion[s.estado].badge}`}>{estadoSesion[s.estado].label}</span>
                      </td>
                      <td className="num">{insc}</td>
                      <td className="num">
                        {s.estado === 'REALIZADA' ? (
                          <span className={`bio-badge ${asis >= insc ? 'bio-badge-ok' : 'bio-badge-warn'}`}>
                            {asis}/{insc}
                          </span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="num">
                        {s.estado === 'REALIZADA' ? (
                          <>
                            <span style={{ color: 'var(--ix2-ok)', fontWeight: 700 }}>{Number(s.num_aprobados ?? 0)}</span> /{' '}
                            <span style={{ color: 'var(--ix2-danger)', fontWeight: 700 }}>{Number(s.num_desaprobados ?? 0)}</span>
                          </>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td style={{ whiteSpace: 'nowrap', textAlign: 'right' }}>
                        <button type="button" className="bio-icon-action" title="Ver inscritos" onClick={() => setDetalle({ id: s.id, tab: 'convocados' })}>
                          <i className="fa-solid fa-eye" aria-hidden />
                        </button>
                        {gestionar && s.estado !== 'CANCELADA' ? (
                          <>
                            <button type="button" className="bio-icon-action" title="Asistencia" onClick={() => setDetalle({ id: s.id, tab: 'asistencia' })}>
                              <i className="fa-solid fa-clipboard-check" aria-hidden />
                            </button>
                            <button
                              type="button"
                              className="bio-icon-action"
                              title="Evaluación"
                              disabled={s.estado !== 'REALIZADA'}
                              onClick={() => setDetalle({ id: s.id, tab: 'evaluacion' })}
                            >
                              <i className="fa-solid fa-star" aria-hidden />
                            </button>
                            <button type="button" className="bio-icon-action" title="Editar" onClick={() => setForm({ open: true, sesion: s })}>
                              <i className="fa-solid fa-pen" aria-hidden />
                            </button>
                            {s.estado === 'PROGRAMADA' ? (
                              <button type="button" className="bio-icon-action danger" title="Cancelar sesión" onClick={() => setCancelar(s)}>
                                <i className="fa-solid fa-ban" aria-hidden />
                              </button>
                            ) : null}
                          </>
                        ) : null}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <SesionFormModal
        open={form.open}
        sesion={form.sesion}
        onClose={() => setForm({ open: false })}
        onSaved={(msg) => {
          setForm({ open: false })
          setNotice({ msg, kind: 'success' })
        }}
      />
      {detalle ? <SesionDetalleModal id={detalle.id} tabInicial={detalle.tab} onClose={() => setDetalle(null)} /> : null}
      <ConfirmDialog
        open={cancelar !== null}
        title="Cancelar sesión"
        message={cancelar ? `${cancelar.cap_nombre} · ${formatDate(cancelar.fecha)}` : ''}
        confirmLabel="Cancelar sesión"
        cancelLabel="Volver"
        onCancel={() => setCancelar(null)}
        onConfirm={() => {
          const s = cancelar
          setCancelar(null)
          if (s)
            cancelarM.mutate(s.id, {
              onError: (e) => setNotice({ kind: 'error', msg: e instanceof ApiClientError ? e.message : 'No se pudo cancelar' }),
            })
        }}
      />
      <NoticeDialog open={notice !== null} kind={notice?.kind} message={notice?.msg ?? ''} onClose={() => setNotice(null)} />
    </div>
  )
}
