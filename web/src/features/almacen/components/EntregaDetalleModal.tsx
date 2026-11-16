import { useEffect, useState } from 'react'
import { ApiClientError } from '../../../shared/api/client'
import { formatDate } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { categorias, estadosEntrega, fmt, num, useConfirmarRecepcion, useEntrega } from '../api'

export function EntregaDetalleModal({
  id,
  puedeConfirmar,
  onClose,
  onDone,
}: {
  id: number | null
  puedeConfirmar: boolean
  onClose: () => void
  onDone: (msg: string) => void
}) {
  const det = useEntrega(id)
  const confirmar = useConfirmarRecepcion()
  const [rec, setRec] = useState<Record<number, string>>({})
  const [anomalia, setAnomalia] = useState('')
  const [error, setError] = useState('')

  const e = det.data?.entrega
  const items = det.data?.items ?? []
  useEffect(() => {
    setRec(Object.fromEntries((det.data?.items ?? []).map((i) => [i.id, String(num(i.cantidad))])))
    setAnomalia('')
    setError('')
  }, [det.data])

  if (id === null) return null
  const pendiente = e?.estado === 'PENDIENTE'
  const editable = puedeConfirmar && pendiente
  const difiere = items.some((i) => Math.abs(Number(rec[i.id] ?? 0) - num(i.cantidad)) > 0.001)

  async function submit() {
    setError('')
    try {
      const r = await confirmar.mutateAsync({
        id: id!,
        anomalia,
        items: items.map((i) => ({ detalle_id: i.id, cantidad_recibida: Number(rec[i.id] ?? 0) })),
      })
      onDone(r.estado === 'RECIBIDA' ? 'Recepción confirmada.' : 'Recepción registrada con observaciones.')
    } catch (err) {
      setError(err instanceof ApiClientError ? err.message : 'No se pudo confirmar')
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      icon="fa-box-open"
      title={e ? `Entrega #${e.id} · ${formatDate(e.fecha)}` : 'Entrega'}
      footer={
        editable ? (
          <>
            <Button variant="ghost" onClick={onClose}>
              Cerrar
            </Button>
            <Button icon="fa-check" disabled={confirmar.isPending || (difiere && !anomalia.trim())} onClick={() => void submit()}>
              Confirmar recepción
            </Button>
          </>
        ) : undefined
      }
    >
      {!e ? (
        <p>Cargando…</p>
      ) : (
        <div style={{ display: 'grid', gap: '0.8rem' }}>
          <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
            <span className={`bio-badge ${estadosEntrega[e.estado].badge}`}>{estadosEntrega[e.estado].label}</span>
            <span className="bio-badge bio-badge-info">
              <i className="fa-solid fa-building" aria-hidden /> {e.empresa_razon_social}
            </span>
            {e.granja_cod ? <span className="bio-badge bio-badge-muted">{e.granja_cod}</span> : null}
            {e.recibido_en ? (
              <span className="bio-badge bio-badge-muted">
                Recibido {e.recibido_en.slice(0, 16)} · {e.recibido_por}
              </span>
            ) : null}
          </div>
          {e.observacion ? <div className="bio-alert">{e.observacion}</div> : null}
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Insumo</th>
                  <th>Unidad</th>
                  <th className="num">Entregado</th>
                  <th className="num">Recibido</th>
                </tr>
              </thead>
              <tbody>
                {items.map((i) => {
                  const r = i.cantidad_recibida === null ? null : num(i.cantidad_recibida)
                  return (
                    <tr key={i.id}>
                      <td>
                        <i className={`fa-solid ${categorias[i.categoria].icon}`} style={{ color: 'var(--ix2-accent)' }} aria-hidden /> {i.insumo_nombre}
                      </td>
                      <td>{i.unidad}</td>
                      <td className="num">{fmt(i.cantidad)}</td>
                      <td className="num">
                        {editable ? (
                          <input
                            type="number"
                            min={0}
                            step="any"
                            className="bio-input bio-input-num"
                            value={rec[i.id] ?? ''}
                            onChange={(ev) => setRec((x) => ({ ...x, [i.id]: ev.target.value }))}
                          />
                        ) : r === null ? (
                          '—'
                        ) : (
                          <span style={{ color: r < num(i.cantidad) ? 'var(--ix2-danger)' : undefined, fontWeight: 700 }}>{fmt(r)}</span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          {editable ? (
            <label>
              <span className="bio-label">Anomalías{difiere ? ' *' : ''}</span>
              <textarea className="bio-input" rows={3} maxLength={500} value={anomalia} onChange={(ev) => setAnomalia(ev.target.value)} />
            </label>
          ) : e.anomalia ? (
            <div className="bio-alert" data-tone="danger">
              <i className="fa-solid fa-triangle-exclamation" aria-hidden /> {e.anomalia}
            </div>
          ) : null}
          {error ? (
            <div className="bio-alert" data-tone="danger">
              {error}
            </div>
          ) : null}
        </div>
      )}
    </Modal>
  )
}
