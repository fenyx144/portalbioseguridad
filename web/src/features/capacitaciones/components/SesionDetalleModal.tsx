import { useEffect, useMemo, useState } from 'react'
import { ApiClientError } from '../../../shared/api/client'
import { formatDate } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { Tabs } from '../../../shared/ui/Tabs'
import {
  estadoSesion,
  resultadoBadge,
  useAgregarParticipantes,
  useAsistencia,
  useEvaluacion,
  useQuitarParticipante,
  useSesionDetalle,
  type Resultado,
} from '../api'
import { ConvocadosPicker, type Convocados } from './ConvocadosPicker'

type Tab = 'convocados' | 'asistencia' | 'evaluacion'

export function SesionDetalleModal({
  id,
  tabInicial = 'convocados',
  onClose,
}: {
  id: number | null
  tabInicial?: Tab
  onClose: () => void
}) {
  const det = useSesionDetalle(id)
  const [tab, setTab] = useState<Tab>(tabInicial)
  const [agregando, setAgregando] = useState(false)
  const [conv, setConv] = useState<Convocados>({ cargos: [], personal_ids: [] })
  const [asist, setAsist] = useState<Record<number, boolean>>({})
  const [notas, setNotas] = useState<Record<number, { nota: string; resultado: Resultado | ''; obs: string }>>({})
  const [msg, setMsg] = useState<{ tone: 'ok' | 'danger'; text: string } | null>(null)

  const agregar = useAgregarParticipantes()
  const quitar = useQuitarParticipante()
  const asistencia = useAsistencia()
  const evaluacion = useEvaluacion()

  const s = det.data?.sesion
  const parts = useMemo(() => det.data?.participantes ?? [], [det.data])
  const gestionar = !!det.data?.puede_gestionar
  const min = Number(s?.nota_minima ?? 14)

  useEffect(() => {
    setTab(tabInicial)
    setAgregando(false)
    setMsg(null)
  }, [id, tabInicial])

  useEffect(() => {
    setAsist(Object.fromEntries(parts.map((p) => [p.personal_id, Number(p.asistio) === 1])))
    setNotas(
      Object.fromEntries(
        parts.map((p) => [
          p.personal_id,
          { nota: p.nota === null || p.nota === undefined ? '' : String(Number(p.nota)), resultado: (p.nota === null && p.resultado !== 'PENDIENTE' ? p.resultado : '') as Resultado | '', obs: p.observacion ?? '' },
        ]),
      ),
    )
  }, [parts])

  if (id === null) return null
  const asistentes = parts.filter((p) => Number(p.asistio) === 1)
  const fail = (e: unknown) => setMsg({ tone: 'danger', text: e instanceof ApiClientError ? e.message : 'Error al guardar' })

  const resultadoCalc = (pid: number): Resultado => {
    const n = notas[pid]
    if (n?.resultado) return n.resultado
    if (!n || n.nota === '') return 'PENDIENTE'
    return Number(n.nota) >= min ? 'APROBADO' : 'DESAPROBADO'
  }

  async function guardarAsistencia() {
    try {
      const r = await asistencia.mutateAsync({
        id: id!,
        participantes: parts.map((p) => ({ personal_id: p.personal_id, asistio: !!asist[p.personal_id] })),
      })
      setMsg({ tone: 'ok', text: `Asistencia guardada: ${r.asistieron} de ${parts.length}.` })
    } catch (e) {
      fail(e)
    }
  }

  async function guardarEvaluacion() {
    try {
      const r = await evaluacion.mutateAsync({
        id: id!,
        items: asistentes.map((p) => {
          const n = notas[p.personal_id]
          return {
            personal_id: p.personal_id,
            nota: n && n.nota !== '' ? Number(n.nota) : null,
            resultado: n?.resultado || undefined,
            observacion: n?.obs ?? '',
          }
        }),
      })
      setMsg({ tone: 'ok', text: `Evaluaciones guardadas: ${r.guardados}.` })
    } catch (e) {
      fail(e)
    }
  }

  async function agregarConv() {
    try {
      const r = await agregar.mutateAsync({ id: id!, ...conv })
      setMsg({ tone: 'ok', text: `Agregados: ${r.agregados}.` })
      setConv({ cargos: [], personal_ids: [] })
      setAgregando(false)
    } catch (e) {
      fail(e)
    }
  }

  const footer =
    gestionar && tab === 'asistencia' ? (
      <Button icon="fa-clipboard-check" disabled={asistencia.isPending || parts.length === 0} onClick={() => void guardarAsistencia()}>
        Guardar asistencia
      </Button>
    ) : gestionar && tab === 'evaluacion' && s?.estado === 'REALIZADA' ? (
      <Button icon="fa-star" disabled={evaluacion.isPending || asistentes.length === 0} onClick={() => void guardarEvaluacion()}>
        Guardar evaluación
      </Button>
    ) : gestionar && tab === 'convocados' && agregando ? (
      <>
        <Button variant="ghost" onClick={() => setAgregando(false)}>
          Cancelar
        </Button>
        <Button icon="fa-user-plus" disabled={agregar.isPending || (conv.cargos.length === 0 && conv.personal_ids.length === 0)} onClick={() => void agregarConv()}>
          Agregar
        </Button>
      </>
    ) : undefined

  return (
    <Modal
      open
      onClose={onClose}
      size="xl"
      icon="fa-chalkboard-user"
      title={s ? `${s.cap_nombre} · ${formatDate(s.fecha)}` : 'Sesión'}
      footer={footer}
    >
      {det.isLoading || !s ? (
        <p>Cargando…</p>
      ) : (
        <div style={{ display: 'grid', gap: '0.8rem' }}>
          <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap', alignItems: 'center' }}>
            <span className={`bio-badge ${estadoSesion[s.estado].badge}`}>{estadoSesion[s.estado].label}</span>
            {s.hora_inicio ? (
              <span className="bio-badge bio-badge-muted">
                <i className="fa-regular fa-clock" aria-hidden /> {s.hora_inicio.slice(0, 5)}
              </span>
            ) : null}
            {s.lugar ? (
              <span className="bio-badge bio-badge-muted">
                <i className="fa-solid fa-location-dot" aria-hidden /> {s.lugar}
              </span>
            ) : null}
            {s.empresa_razon_social ? (
              <span className="bio-badge bio-badge-info">
                <i className="fa-solid fa-building" aria-hidden /> {s.empresa_razon_social}
              </span>
            ) : null}
            <span className="bio-badge bio-badge-accent">Nota mín. {min}</span>
          </div>

          <Tabs
            items={[
              { value: 'convocados', label: 'Convocados', icon: 'fa-users', count: parts.length },
              { value: 'asistencia', label: 'Asistencia', icon: 'fa-clipboard-check', count: asistentes.length },
              { value: 'evaluacion', label: 'Evaluación', icon: 'fa-star', count: asistentes.filter((p) => p.resultado !== 'PENDIENTE').length },
            ]}
            value={tab}
            onChange={(t) => {
              setTab(t)
              setMsg(null)
            }}
          />

          {msg ? (
            <div className="bio-alert" data-tone={msg.tone}>
              <i className={`fa-solid ${msg.tone === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation'}`} aria-hidden /> {msg.text}
            </div>
          ) : null}

          {tab === 'convocados' ? (
            agregando ? (
              <ConvocadosPicker value={conv} onChange={setConv} excluir={parts.map((p) => p.personal_id)} />
            ) : (
              <>
                {gestionar ? (
                  <div>
                    <Button variant="soft" size="sm" icon="fa-user-plus" onClick={() => setAgregando(true)}>
                      Agregar convocados
                    </Button>
                  </div>
                ) : null}
                <div className="bio-table-wrap">
                  <table className="bio-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Apellidos y nombres</th>
                        <th>DNI</th>
                        <th>Cargo</th>
                        <th>Asistencia</th>
                        <th>Resultado</th>
                        {gestionar ? <th /> : null}
                      </tr>
                    </thead>
                    <tbody>
                      {parts.map((p, i) => (
                        <tr key={p.personal_id}>
                          <td>{i + 1}</td>
                          <td>
                            {p.apellidos}, {p.nombres}
                          </td>
                          <td>{p.dni}</td>
                          <td>{p.cargo}</td>
                          <td>
                            {s.estado === 'PROGRAMADA' ? (
                              <span className="bio-badge bio-badge-muted">—</span>
                            ) : Number(p.asistio) === 1 ? (
                              <span className="bio-badge bio-badge-ok">Asistió</span>
                            ) : (
                              <span className="bio-badge bio-badge-danger">Faltó</span>
                            )}
                          </td>
                          <td>
                            {Number(p.asistio) === 1 ? (
                              <span className={`bio-badge ${resultadoBadge[p.resultado]}`}>
                                {p.resultado}
                                {p.nota !== null && p.nota !== undefined ? ` · ${Number(p.nota)}` : ''}
                              </span>
                            ) : null}
                          </td>
                          {gestionar ? (
                            <td style={{ textAlign: 'right' }}>
                              {Number(p.asistio) !== 1 ? (
                                <button
                                  type="button"
                                  className="bio-icon-action danger"
                                  title="Quitar"
                                  onClick={() => quitar.mutate({ id: id!, personalId: p.personal_id }, { onError: fail })}
                                >
                                  <i className="fa-solid fa-user-minus" aria-hidden />
                                </button>
                              ) : null}
                            </td>
                          ) : null}
                        </tr>
                      ))}
                      {parts.length === 0 ? (
                        <tr>
                          <td colSpan={7} style={{ textAlign: 'center', color: 'var(--ix2-muted)' }}>
                            Sin convocados
                          </td>
                        </tr>
                      ) : null}
                    </tbody>
                  </table>
                </div>
              </>
            )
          ) : null}

          {tab === 'asistencia' ? (
            <>
              {gestionar ? (
                <div style={{ display: 'flex', gap: '0.4rem' }}>
                  <Button variant="ghost" size="sm" icon="fa-check-double" onClick={() => setAsist(Object.fromEntries(parts.map((p) => [p.personal_id, true])))}>
                    Todos
                  </Button>
                  <Button variant="ghost" size="sm" icon="fa-xmark" onClick={() => setAsist(Object.fromEntries(parts.map((p) => [p.personal_id, false])))}>
                    Ninguno
                  </Button>
                </div>
              ) : null}
              <div className="bio-people-list" style={{ maxHeight: '50vh' }}>
                {parts.map((p) => (
                  <button
                    key={p.personal_id}
                    type="button"
                    className="bio-people-item"
                    aria-pressed={!!asist[p.personal_id]}
                    disabled={!gestionar}
                    onClick={() => setAsist((a) => ({ ...a, [p.personal_id]: !a[p.personal_id] }))}
                  >
                    <input type="checkbox" readOnly checked={!!asist[p.personal_id]} tabIndex={-1} />
                    <span style={{ flex: 1 }}>
                      <strong>
                        {p.apellidos}, {p.nombres}
                      </strong>
                      <span style={{ display: 'block', fontSize: '0.68rem', color: 'var(--ix2-muted)' }}>
                        {p.cargo} · DNI {p.dni}
                      </span>
                    </span>
                    {asist[p.personal_id] ? <span className="bio-badge bio-badge-ok">Asistió</span> : null}
                  </button>
                ))}
              </div>
            </>
          ) : null}

          {tab === 'evaluacion' ? (
            s.estado !== 'REALIZADA' ? (
              <div className="bio-alert" data-tone="warn">
                <i className="fa-solid fa-clipboard-check" aria-hidden /> Registre la asistencia primero.
              </div>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>Apellidos y nombres</th>
                      <th>Cargo</th>
                      <th className="num">Nota</th>
                      <th>Resultado</th>
                      <th>Observación</th>
                    </tr>
                  </thead>
                  <tbody>
                    {asistentes.map((p) => {
                      const n = notas[p.personal_id] ?? { nota: '', resultado: '', obs: '' }
                      const r = resultadoCalc(p.personal_id)
                      const upd = (patch: Partial<typeof n>) => setNotas((x) => ({ ...x, [p.personal_id]: { ...n, ...patch } }))
                      return (
                        <tr key={p.personal_id}>
                          <td>
                            {p.apellidos}, {p.nombres}
                          </td>
                          <td>{p.cargo}</td>
                          <td className="num">
                            <input
                              type="number"
                              min={0}
                              max={20}
                              step={0.5}
                              className="bio-input bio-input-num"
                              disabled={!gestionar}
                              value={n.nota}
                              onChange={(e) => upd({ nota: e.target.value, resultado: '' })}
                            />
                          </td>
                          <td>
                            {gestionar ? (
                              <select
                                className="bio-select"
                                value={n.resultado || r}
                                onChange={(e) => upd({ resultado: e.target.value as Resultado })}
                              >
                                <option value="PENDIENTE">Pendiente</option>
                                <option value="APROBADO">Aprobado</option>
                                <option value="DESAPROBADO">Desaprobado</option>
                              </select>
                            ) : (
                              <span className={`bio-badge ${resultadoBadge[p.resultado]}`}>{p.resultado}</span>
                            )}
                          </td>
                          <td>
                            <input className="bio-input" maxLength={255} disabled={!gestionar} value={n.obs} onChange={(e) => upd({ obs: e.target.value })} />
                          </td>
                        </tr>
                      )
                    })}
                    {asistentes.length === 0 ? (
                      <tr>
                        <td colSpan={5} style={{ textAlign: 'center', color: 'var(--ix2-muted)' }}>
                          Sin asistentes
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            )
          ) : null}
        </div>
      )}
    </Modal>
  )
}
