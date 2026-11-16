import { useEffect, useMemo, useState } from 'react'
import { ApiClientError } from '../../../shared/api/client'
import { addDays, formatShortDate } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { usePersonalDisponible, useReemplazo } from '../api'
import type { Asignacion, PersonaLite } from '../types'
import { iniciales, turnoLabel } from '../utils'

const MOTIVOS = ['Descanso médico', 'Vacaciones', 'Permiso', 'Falta', 'Cese / renuncia', 'Capacitación']

type Candidato = PersonaLite & { warnings?: string[]; fuera_granja_base?: boolean }

export type ReemplazoTarget = {
  asig: Asignacion
  puntoNombre: string
  turno: string
  /** Fechas en que el titular está asignado en este punto/turno (para sugerir el rango). */
  fechasTitular: string[]
}

export function ReemplazoModal({
  target,
  onClose,
  onDone,
}: {
  target: ReemplazoTarget | null
  onClose: () => void
  onDone: (msg: string) => void
}) {
  const [alcance, setAlcance] = useState<'dia' | 'rango'>('dia')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [mismoPunto, setMismoPunto] = useState(true)
  const [motivo, setMotivo] = useState('')
  const [detalle, setDetalle] = useState('')
  const [reemplazoId, setReemplazoId] = useState<number | null>(null)
  const [q, setQ] = useState('')
  const [error, setError] = useState('')

  const fecha = target?.asig.fecha ?? ''
  useEffect(() => {
    if (!target) return
    const f = target.asig.fecha ?? ''
    const posteriores = target.fechasTitular.filter((d) => d >= f).sort()
    setAlcance('dia')
    setDesde(f)
    setHasta(posteriores[posteriores.length - 1] ?? f)
    setMismoPunto(true)
    setMotivo('')
    setDetalle('')
    setReemplazoId(null)
    setQ('')
    setError('')
  }, [target])

  const disp = usePersonalDisponible(
    target && target.asig.punto_id ? { punto_id: target.asig.punto_id, fecha, turno_cod: target.turno } : null,
  )
  const reemplazo = useReemplazo()

  const candidatos = useMemo(() => {
    const all: Candidato[] = [...((disp.data?.misma_granja ?? []) as Candidato[]), ...((disp.data?.otras ?? []) as Candidato[])]
    const t = q.trim().toLowerCase()
    return all.filter(
      (p) => p.id !== target?.asig.personal_id && (!t || `${p.apellidos} ${p.nombres} ${p.dni ?? ''}`.toLowerCase().includes(t)),
    )
  }, [disp.data, q, target])

  if (!target) return null
  const motivoFinal = [motivo, detalle.trim()].filter(Boolean).join(': ')

  async function guardar() {
    if (!target || !reemplazoId) return
    setError('')
    try {
      const r = await reemplazo.mutateAsync({
        asignacion_id: target.asig.id,
        personal_reemplazo_id: reemplazoId,
        motivo: motivoFinal,
        alcance,
        fecha_desde: alcance === 'rango' ? desde : undefined,
        fecha_hasta: alcance === 'rango' ? hasta : undefined,
        mismo_punto: alcance === 'rango' ? mismoPunto : undefined,
      })
      const extra = r.fallidas
        ? `\n\nNo se pudo reemplazar en ${r.fallidas} turno(s):\n` +
          r.detalle_fallidas.map((f) => `• ${formatShortDate(f.fecha)} ${turnoLabel(f.turno_cod)}: ${f.message}`).join('\n')
        : ''
      onDone(`Reemplazo registrado en ${r.reemplazadas} turno(s).${extra}`)
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo registrar el reemplazo')
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      icon="fa-right-left"
      title={`Reemplazo · ${target.asig.apellidos}, ${target.asig.nombres}`}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button icon="fa-check" disabled={!reemplazoId || !motivo || reemplazo.isPending} onClick={() => void guardar()}>
            {reemplazo.isPending ? 'Guardando…' : 'Registrar reemplazo'}
          </Button>
        </>
      }
    >
      <div className="bio-steps">
        <div className="bio-alert">
          <i className="fa-solid fa-location-dot" aria-hidden />
          <span>
            {target.puntoNombre} · {turnoLabel(target.turno)} · {formatShortDate(fecha)}
          </span>
        </div>

        <div className="bio-step">
          <span className="bio-step-n">1</span>
          <div>
            <p className="bio-step-title">¿Por cuánto tiempo?</p>
            <div className="bio-option-grid">
              <button type="button" className="bio-option" aria-pressed={alcance === 'dia'} onClick={() => setAlcance('dia')}>
                <i className="fa-solid fa-calendar-day" aria-hidden /> Solo este día ({formatShortDate(fecha)})
              </button>
              <button type="button" className="bio-option" aria-pressed={alcance === 'rango'} onClick={() => setAlcance('rango')}>
                <i className="fa-solid fa-calendar-week" aria-hidden /> Rango de fechas
              </button>
            </div>
            {alcance === 'rango' ? (
              <div style={{ display: 'flex', gap: '0.6rem', flexWrap: 'wrap', alignItems: 'flex-end', marginTop: '0.55rem' }}>
                <label>
                  <span className="bio-label">Desde</span>
                  <input type="date" className="bio-input" value={desde} onChange={(e) => setDesde(e.target.value)} />
                </label>
                <label>
                  <span className="bio-label">Hasta</span>
                  <input type="date" className="bio-input" value={hasta} min={desde} max={desde ? addDays(desde, 61) : undefined} onChange={(e) => setHasta(e.target.value)} />
                </label>
                <label className="bio-switch">
                  <input type="checkbox" checked={mismoPunto} onChange={(e) => setMismoPunto(e.target.checked)} />
                  Solo en este punto y turno
                </label>
              </div>
            ) : null}
          </div>
        </div>

        <div className="bio-step">
          <span className="bio-step-n">2</span>
          <div>
            <p className="bio-step-title">Motivo</p>
            <div className="bio-day-toggles">
              {MOTIVOS.map((m) => (
                <button key={m} type="button" className="bio-day-toggle" aria-pressed={motivo === m} onClick={() => setMotivo(m)}>
                  {m}
                </button>
              ))}
            </div>
            <input
              className="bio-input"
              style={{ marginTop: '0.45rem' }}
              placeholder="Detalle (opcional)"
              maxLength={200}
              value={detalle}
              onChange={(e) => setDetalle(e.target.value)}
            />
          </div>
        </div>

        <div className="bio-step">
          <span className="bio-step-n">3</span>
          <div style={{ minWidth: 0 }}>
            <p className="bio-step-title">¿Quién lo reemplaza?</p>
            <input
              className="bio-input"
              style={{ marginBottom: '0.45rem' }}
              placeholder="Buscar…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
            <div className="bio-people-list">
              {disp.isLoading ? (
                <div className="bio-people-item">Buscando personal disponible…</div>
              ) : candidatos.length === 0 ? (
                <div className="bio-people-item">No hay personal disponible para ese turno</div>
              ) : (
                candidatos.map((p) => (
                  <button
                    key={p.id}
                    type="button"
                    className="bio-people-item"
                    aria-pressed={reemplazoId === p.id}
                    onClick={() => setReemplazoId(p.id)}
                  >
                    <input type="radio" readOnly checked={reemplazoId === p.id} tabIndex={-1} />
                    <span className="bio-avatar-sm">{iniciales(p)}</span>
                    <span style={{ flex: 1, minWidth: 0 }}>
                      <strong>
                        {p.apellidos}, {p.nombres}
                      </strong>
                      <span style={{ display: 'block', fontSize: '0.68rem', color: 'var(--ix2-muted)' }}>{p.cargo || 'Sin cargo'}</span>
                    </span>
                    {p.fuera_granja_base ? <span className="bio-badge bio-badge-info">Otra granja base</span> : null}
                    {p.warnings && p.warnings.length ? (
                      <span className="bio-badge bio-badge-warn" title={p.warnings.join('\n')}>
                        <i className="fa-solid fa-triangle-exclamation" aria-hidden /> {p.warnings.length}
                      </span>
                    ) : null}
                  </button>
                ))
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
