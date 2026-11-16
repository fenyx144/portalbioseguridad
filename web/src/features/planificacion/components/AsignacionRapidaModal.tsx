import { useEffect, useMemo, useState } from 'react'
import { ApiClientError } from '../../../shared/api/client'
import { dayLetter, dayNum, dayShort, formatShortDate } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { useAsignarRapido, usePersonalActivo } from '../api'
import type { Fila, Grid, LoteResultado } from '../types'
import { coberturaFilaTurno, iniciales, ocupacionPorPersona, turnoIcon, turnoLabel } from '../utils'

export type RapidaPreset = { puntoId?: number; turno?: string; fecha?: string }

type Props = {
  open: boolean
  onClose: () => void
  filas: Fila[]
  dias: string[]
  /** Grilla completa (todas las granjas) para avisar cruces de personal. */
  gridCompleto?: Grid
  preset?: RapidaPreset
}

export function AsignacionRapidaModal({ open, onClose, filas, dias, gridCompleto, preset }: Props) {
  const opciones = useMemo(
    () => filas.flatMap((f) => f.turnos.map((t) => ({ fila: f, turno: t.turno_cod, req: t.personal_requerido }))),
    [filas],
  )
  const [sel, setSel] = useState<{ puntoId: number; turno: string } | null>(null)
  const turnosDisp = useMemo(() => [...new Set(opciones.map((o) => o.turno))], [opciones])
  const [diasSel, setDiasSel] = useState<string[]>(dias)
  const [personas, setPersonas] = useState<number[]>([])
  const [q, setQ] = useState('')
  const [cargo, setCargo] = useState('')
  const [resultado, setResultado] = useState<LoteResultado | null>(null)
  const [error, setError] = useState('')

  const personal = usePersonalActivo(open)
  const asignar = useAsignarRapido()

  useEffect(() => {
    if (!open) return
    const first = opciones[0]
    setSel(
      preset?.puntoId && preset.turno
        ? { puntoId: preset.puntoId, turno: preset.turno }
        : first
          ? { puntoId: first.fila.punto.id, turno: first.turno }
          : null,
    )
    setDiasSel(preset?.fecha ? [preset.fecha] : dias)
    setPersonas([])
    setQ('')
    setCargo('')
    setResultado(null)
    setError('')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const filaSel = filas.find((f) => f.punto.id === sel?.puntoId)
  const ocupacion = useMemo(() => ocupacionPorPersona(gridCompleto), [gridCompleto])
  const items = personal.data?.items ?? []
  const cargos = useMemo(() => [...new Set(items.map((p) => p.cargo).filter(Boolean))].sort(), [items])
  const filtrados = useMemo(() => {
    const t = q.trim().toLowerCase()
    return items.filter(
      (p) =>
        (!cargo || p.cargo === cargo) &&
        (!t || `${p.apellidos} ${p.nombres} ${p.dni ?? ''}`.toLowerCase().includes(t)),
    )
  }, [items, q, cargo])

  const celda = (d: string) => (filaSel && sel ? filaSel.celdas[d]?.[sel.turno] : undefined)
  const total = personas.length * diasSel.length

  const toggleDia = (d: string) =>
    setDiasSel((cur) => (cur.includes(d) ? cur.filter((x) => x !== d) : [...cur, d].sort()))
  const togglePersona = (id: number) =>
    setPersonas((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))

  async function enviar(force = false, subset?: { personal_id: number; fecha: string }[]) {
    if (!sel) return
    setError('')
    try {
      if (subset) {
        // Reintento forzado: agrupar por persona
        const porPersona = new Map<number, string[]>()
        subset.forEach((s) => porPersona.set(s.personal_id, [...(porPersona.get(s.personal_id) ?? []), s.fecha]))
        let creadas = 0
        const fallidas: LoteResultado['detalle_fallidas'] = []
        for (const [pid, fechas] of porPersona) {
          const r = await asignar.mutateAsync({ personal_ids: [pid], punto_id: sel.puntoId, turno_cod: sel.turno, fechas, force_warnings: true })
          creadas += r.creadas
          fallidas.push(...r.detalle_fallidas)
        }
        setResultado({
          creadas: (resultado?.creadas ?? 0) + creadas,
          fallidas: fallidas.length,
          detalle_fallidas: fallidas,
        })
        return
      }
      const r = await asignar.mutateAsync({
        personal_ids: personas,
        punto_id: sel.puntoId,
        turno_cod: sel.turno,
        fechas: diasSel,
        force_warnings: force,
      })
      setResultado(r)
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo guardar la asignación')
    }
  }

  const nombrePersona = (id: number) => {
    const p = items.find((x) => x.id === id)
    return p ? `${p.apellidos}, ${p.nombres}` : `#${id}`
  }

  if (resultado) {
    const advertencias = resultado.detalle_fallidas.filter((f) => f.code === 'warnings')
    const bloqueos = resultado.detalle_fallidas.filter((f) => f.code !== 'warnings')
    return (
      <Modal
        open={open}
        onClose={onClose}
        title="Resultado de la asignación"
        icon="fa-clipboard-check"
        size="lg"
        footer={
          <>
            {advertencias.length > 0 ? (
              <Button variant="soft" icon="fa-bolt" disabled={asignar.isPending} onClick={() => void enviar(true, advertencias)}>
                Asignar igual ({advertencias.length} con advertencia)
              </Button>
            ) : null}
            <Button onClick={onClose} icon="fa-check">
              Listo
            </Button>
          </>
        }
      >
        <div className="bio-kpis" style={{ marginBottom: '0.8rem' }}>
          <div className="bio-kpi">
            <span className="bio-kpi-ic">
              <i className="fa-solid fa-circle-check" aria-hidden />
            </span>
            <div>
              <div className="bio-kpi-val">{resultado.creadas}</div>
              <div className="bio-kpi-lbl">turnos asignados</div>
            </div>
          </div>
          <div className="bio-kpi" data-tone={resultado.fallidas ? 'warn' : undefined}>
            <span className="bio-kpi-ic">
              <i className="fa-solid fa-triangle-exclamation" aria-hidden />
            </span>
            <div>
              <div className="bio-kpi-val">{resultado.fallidas}</div>
              <div className="bio-kpi-lbl">no asignados</div>
            </div>
          </div>
        </div>
        {resultado.detalle_fallidas.length === 0 ? (
          <div className="bio-alert" data-tone="ok">
            <i className="fa-solid fa-circle-check" aria-hidden /> Todo quedó asignado correctamente.
          </div>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Persona</th>
                  <th>Fecha</th>
                  <th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                {[...bloqueos, ...advertencias].map((f, i) => (
                  <tr key={i}>
                    <td>{nombrePersona(f.personal_id)}</td>
                    <td>{formatShortDate(f.fecha)}</td>
                    <td>
                      <span className={`bio-badge ${f.code === 'warnings' ? 'bio-badge-warn' : 'bio-badge-danger'}`}>
                        {f.code === 'warnings' ? 'Advertencia' : 'Bloqueado'}
                      </span>{' '}
                      {f.message}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {error ? (
          <div className="bio-alert" data-tone="danger" style={{ marginTop: '0.6rem' }}>
            {error}
          </div>
        ) : null}
      </Modal>
    )
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Asignación rápida"
      icon="fa-bolt"
      size="xl"
      footer={
        <>
          <span style={{ marginRight: 'auto' }} />
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button icon="fa-check" disabled={!sel || total === 0 || asignar.isPending} onClick={() => void enviar(false)}>
            {asignar.isPending ? 'Asignando…' : 'Asignar'}
          </Button>
        </>
      }
    >
      <div className="bio-steps">
        <div className="bio-step">
          <span className="bio-step-n">1</span>
          <div>
            <p className="bio-step-title">Punto y turno</p>
            {turnosDisp.length > 1 ? (
              <div className="bio-filter-chips" role="group" style={{ marginBottom: '0.6rem' }}>
                {turnosDisp.map((t) => (
                  <button
                    key={t}
                    type="button"
                    className="bio-filter-chip"
                    aria-pressed={sel?.turno === t}
                    onClick={() => {
                      const o = opciones.find((x) => x.turno === t && x.fila.punto.id === sel?.puntoId) ?? opciones.find((x) => x.turno === t)
                      if (o) setSel({ puntoId: o.fila.punto.id, turno: t })
                    }}
                  >
                    <i className={`fa-solid ${turnoIcon(t)}`} aria-hidden /> {turnoLabel(t)}
                  </button>
                ))}
              </div>
            ) : null}
            <div className="bio-option-grid">
              {opciones.filter((o) => !sel || o.turno === sel.turno).map((o) => {
                const cov = coberturaFilaTurno(o.fila, dias, o.turno)
                const activo = sel?.puntoId === o.fila.punto.id && sel.turno === o.turno
                return (
                  <button
                    key={`${o.fila.punto.id}-${o.turno}`}
                    type="button"
                    className="bio-option"
                    aria-pressed={activo}
                    onClick={() => setSel({ puntoId: o.fila.punto.id, turno: o.turno })}
                  >
                    <i
                      className={`fa-solid ${turnoIcon(o.turno)}`}
                      style={{ color: o.turno === 'NOCHE' ? 'var(--ix2-noche)' : 'var(--ix2-dia)' }}
                      aria-hidden
                    />
                    <span style={{ flex: 1, minWidth: 0 }}>
                      <strong style={{ display: 'block' }}>{o.fila.punto.nombre}</strong>
                      <span style={{ color: 'var(--ix2-muted)', fontSize: '0.7rem' }}>
                        {o.req} {o.req === 1 ? 'persona' : 'personas'}
                      </span>
                    </span>
                    <span className={`bio-badge ${cov.cub >= cov.req ? 'bio-badge-ok' : cov.cub > 0 ? 'bio-badge-warn' : 'bio-badge-danger'}`}>
                      {cov.cub}/{cov.req}
                    </span>
                  </button>
                )
              })}
            </div>
          </div>
        </div>

        <div className="bio-step">
          <span className="bio-step-n">2</span>
          <div>
            <p className="bio-step-title">
              Días
            </p>
            <div className="bio-day-toggles">
              {dias.map((d) => {
                const c = celda(d)
                const lleno = !!c && c.asignado >= c.requerido
                return (
                  <button
                    key={d}
                    type="button"
                    className="bio-day-toggle"
                    aria-pressed={diasSel.includes(d)}
                    data-lleno={lleno}
                    onClick={() => toggleDia(d)}
                    title={c ? `${c.asignado}/${c.requerido} asignados` : ''}
                  >
                    {dayShort(d)}
                    <small>
                      {dayNum(d)} · {c ? `${c.asignado}/${c.requerido}` : '—'}
                    </small>
                  </button>
                )
              })}
              <Button variant="ghost" size="sm" onClick={() => setDiasSel(dias)}>
                Todos
              </Button>
              <Button variant="ghost" size="sm" onClick={() => setDiasSel(dias.slice(0, 5))}>
                Lun–Vie
              </Button>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setDiasSel(dias.filter((d) => {
                  const c = celda(d)
                  return !c || c.asignado < c.requerido
                }))}
              >
                Solo faltantes
              </Button>
            </div>
          </div>
        </div>

        <div className="bio-step">
          <span className="bio-step-n">3</span>
          <div style={{ minWidth: 0 }}>
            <p className="bio-step-title">Personal ({personas.length} seleccionado/s)</p>
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', marginBottom: '0.5rem' }}>
              <input
                className="bio-input"
                style={{ flex: '1 1 14rem' }}
                placeholder="Buscar por apellido, nombre o DNI…"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
              <select className="bio-select" style={{ flex: '0 1 12rem' }} value={cargo} onChange={(e) => setCargo(e.target.value)}>
                <option value="">Todos los cargos</option>
                {cargos.map((c) => (
                  <option key={c} value={c}>
                    {c}
                  </option>
                ))}
              </select>
            </div>
            {personas.length > 0 ? (
              <div className="bio-selected-chips" style={{ marginBottom: '0.5rem' }}>
                {personas.map((id) => (
                  <span key={id}>
                    {nombrePersona(id)}
                    <button type="button" aria-label="Quitar" onClick={() => togglePersona(id)}>
                      <i className="fa-solid fa-xmark" aria-hidden />
                    </button>
                  </span>
                ))}
              </div>
            ) : null}
            <div className="bio-people-list">
              {personal.isLoading ? (
                <div className="bio-people-item">Cargando personal…</div>
              ) : filtrados.length === 0 ? (
                <div className="bio-people-item">Sin resultados</div>
              ) : (
                filtrados.map((p) => {
                  const occ = (ocupacion.get(p.id) ?? []).filter((o) => diasSel.includes(o.fecha))
                  const aqui = occ.filter((o) => filaSel && o.punto === filaSel.punto.nombre && o.turno === sel?.turno)
                  const otros = occ.filter((o) => !aqui.includes(o))
                  const pressed = personas.includes(p.id)
                  return (
                    <button key={p.id} type="button" className="bio-people-item" aria-pressed={pressed} onClick={() => togglePersona(p.id)}>
                      <input type="checkbox" readOnly checked={pressed} tabIndex={-1} />
                      <span className="bio-avatar-sm">{iniciales(p)}</span>
                      <span style={{ flex: 1, minWidth: 0 }}>
                        <strong>
                          {p.apellidos}, {p.nombres}
                        </strong>
                        <span style={{ display: 'block', fontSize: '0.68rem', color: 'var(--ix2-muted)' }}>
                          {p.cargo || 'Sin cargo'}
                          {p.dni ? ` · DNI ${p.dni}` : ''}
                        </span>
                      </span>
                      {aqui.length > 0 ? (
                        <span className="bio-badge bio-badge-ok" title="Ya asignado en este punto y turno">
                          Aquí: {aqui.map((o) => dayLetter(o.fecha)).join(' ')}
                        </span>
                      ) : null}
                      {otros.length > 0 ? (
                        <span
                          className="bio-badge bio-badge-warn"
                          title={otros.map((o) => `${formatShortDate(o.fecha)} ${turnoLabel(o.turno)} · ${o.punto}`).join('\n')}
                        >
                          <i className="fa-solid fa-calendar-xmark" aria-hidden /> Ocupado {otros.map((o) => dayLetter(o.fecha)).join(' ')}
                        </span>
                      ) : null}
                    </button>
                  )
                })
              )}
            </div>
          </div>
        </div>
        {error ? (
          <div className="bio-alert" data-tone="danger">
            <i className="fa-solid fa-circle-exclamation" aria-hidden /> {error}
          </div>
        ) : null}
      </div>
    </Modal>
  )
}
