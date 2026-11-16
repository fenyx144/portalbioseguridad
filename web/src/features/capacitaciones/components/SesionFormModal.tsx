import { useEffect, useState } from 'react'
import { ApiClientError } from '../../../shared/api/client'
import { todayIso } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { useCatalogo, useGuardarSesion, type Sesion } from '../api'
import { ConvocadosPicker, type Convocados } from './ConvocadosPicker'

export function SesionFormModal({
  open,
  sesion,
  onClose,
  onSaved,
}: {
  open: boolean
  sesion?: Sesion | null
  onClose: () => void
  onSaved: (msg: string) => void
}) {
  const cat = useCatalogo()
  const guardar = useGuardarSesion()
  const [f, setF] = useState({ capacitacion_id: '', fecha: todayIso(), hora_inicio: '', lugar: '', nota_minima: '14', observacion: '' })
  const [conv, setConv] = useState<Convocados>({ cargos: [], personal_ids: [] })
  const [error, setError] = useState('')

  useEffect(() => {
    if (!open) return
    setError('')
    setConv({ cargos: [], personal_ids: [] })
    setF(
      sesion
        ? {
            capacitacion_id: String(sesion.capacitacion_id),
            fecha: sesion.fecha,
            hora_inicio: (sesion.hora_inicio ?? '').slice(0, 5),
            lugar: sesion.lugar ?? '',
            nota_minima: String(Number(sesion.nota_minima ?? 14)),
            observacion: sesion.observacion ?? '',
          }
        : { capacitacion_id: '', fecha: todayIso(), hora_inicio: '', lugar: '', nota_minima: '14', observacion: '' },
    )
  }, [open, sesion])

  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((x) => ({ ...x, [k]: e.target.value }))
  const nuevo = !sesion
  const valido = f.capacitacion_id && f.fecha && (!nuevo || conv.cargos.length > 0 || conv.personal_ids.length > 0)

  async function submit() {
    setError('')
    try {
      const r = await guardar.mutateAsync({
        id: sesion?.id,
        body: {
          capacitacion_id: Number(f.capacitacion_id),
          fecha: f.fecha,
          hora_inicio: f.hora_inicio || undefined,
          lugar: f.lugar,
          nota_minima: Number(f.nota_minima || 14),
          observacion: f.observacion,
          ...(nuevo ? { cargos: conv.cargos, personal_ids: conv.personal_ids } : {}),
        },
      })
      onSaved(nuevo ? `Sesión programada con ${(r as { convocados?: number }).convocados ?? 0} convocado(s).` : 'Sesión actualizada.')
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo guardar')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      icon="fa-calendar-plus"
      title={nuevo ? 'Programar capacitación' : 'Editar sesión'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button icon="fa-check" disabled={!valido || guardar.isPending} onClick={() => void submit()}>
            {guardar.isPending ? 'Guardando…' : 'Guardar'}
          </Button>
        </>
      }
    >
      <div style={{ display: 'grid', gap: '0.9rem' }}>
        <div className="bio-form-grid">
          <label style={{ gridColumn: '1 / -1' }}>
            <span className="bio-label">Capacitación</span>
            <select className="bio-select" value={f.capacitacion_id} onChange={set('capacitacion_id')}>
              <option value="">Seleccione…</option>
              {(cat.data?.items ?? []).map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre} ({c.codigo})
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="bio-label">Fecha</span>
            <input type="date" className="bio-input" value={f.fecha} onChange={set('fecha')} />
          </label>
          <label>
            <span className="bio-label">Hora</span>
            <input type="time" className="bio-input" value={f.hora_inicio} onChange={set('hora_inicio')} />
          </label>
          <label>
            <span className="bio-label">Lugar</span>
            <input className="bio-input" maxLength={120} value={f.lugar} onChange={set('lugar')} />
          </label>
          <label>
            <span className="bio-label">Nota mínima (0–20)</span>
            <input type="number" min={0} max={20} step={0.5} className="bio-input" value={f.nota_minima} onChange={set('nota_minima')} />
          </label>
          <label style={{ gridColumn: '1 / -1' }}>
            <span className="bio-label">Observación</span>
            <input className="bio-input" maxLength={255} value={f.observacion} onChange={set('observacion')} />
          </label>
        </div>
        {nuevo ? (
          <div>
            <span className="bio-label">Convocados</span>
            <ConvocadosPicker value={conv} onChange={setConv} />
          </div>
        ) : null}
        {error ? (
          <div className="bio-alert" data-tone="danger">
            <i className="fa-solid fa-circle-exclamation" aria-hidden /> {error}
          </div>
        ) : null}
      </div>
    </Modal>
  )
}
