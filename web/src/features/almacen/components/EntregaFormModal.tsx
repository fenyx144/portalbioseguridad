import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { ApiClientError, api } from '../../../shared/api/client'
import { todayIso } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { categorias, useCrearEntrega, useInsumos } from '../api'

export function EntregaFormModal({ open, onClose, onSaved }: { open: boolean; onClose: () => void; onSaved: (id: number) => void }) {
  const insumos = useInsumos()
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string; estado: string }[] }>('/empresas'),
    enabled: open,
  })
  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
    enabled: open,
  })
  const crear = useCrearEntrega()
  const [f, setF] = useState({ empresa_id: '', granja_cod: '', fecha: todayIso(), observacion: '' })
  const [cant, setCant] = useState<Record<number, string>>({})
  const [error, setError] = useState('')

  useEffect(() => {
    if (open) {
      setF({ empresa_id: '', granja_cod: '', fecha: todayIso(), observacion: '' })
      setCant({})
      setError('')
    }
  }, [open])

  const items = Object.entries(cant)
    .map(([id, v]) => ({ insumo_id: Number(id), cantidad: Number(v) }))
    .filter((i) => i.cantidad > 0)

  async function submit() {
    setError('')
    try {
      const r = await crear.mutateAsync({ ...f, empresa_id: Number(f.empresa_id), items })
      onSaved(r.id)
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo registrar')
    }
  }

  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((x) => ({ ...x, [k]: e.target.value }))

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      icon="fa-truck-ramp-box"
      title="Nueva entrega de insumos"
      footer={
        <>
          <span style={{ marginRight: 'auto', fontSize: '0.78rem', color: 'var(--ix2-muted)' }}>{items.length} insumo(s)</span>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button icon="fa-check" disabled={!f.empresa_id || items.length === 0 || crear.isPending} onClick={() => void submit()}>
            Registrar entrega
          </Button>
        </>
      }
    >
      <div style={{ display: 'grid', gap: '0.9rem' }}>
        <div className="bio-form-grid">
          <label>
            <span className="bio-label">Empresa</span>
            <select className="bio-select" value={f.empresa_id} onChange={set('empresa_id')}>
              <option value="">Seleccione…</option>
              {(empresas.data?.items ?? [])
                .filter((e) => e.estado === 'ACTIVO')
                .map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.razon_social}
                  </option>
                ))}
            </select>
          </label>
          <label>
            <span className="bio-label">Granja</span>
            <select className="bio-select" value={f.granja_cod} onChange={set('granja_cod')}>
              <option value="">—</option>
              {(granjas.data?.items ?? []).map((g) => (
                <option key={g.codigo} value={g.codigo}>
                  {g.nombre}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="bio-label">Fecha</span>
            <input type="date" className="bio-input" value={f.fecha} onChange={set('fecha')} />
          </label>
          <label>
            <span className="bio-label">Observación</span>
            <input className="bio-input" maxLength={255} value={f.observacion} onChange={set('observacion')} />
          </label>
        </div>
        <div className="bio-table-wrap">
          <table className="bio-table">
            <thead>
              <tr>
                <th>Insumo</th>
                <th>Categoría</th>
                <th>Unidad</th>
                <th className="num">Cantidad</th>
              </tr>
            </thead>
            <tbody>
              {(insumos.data?.items ?? []).map((i) => (
                <tr key={i.id}>
                  <td>
                    <strong>{i.nombre}</strong> <span style={{ color: 'var(--ix2-muted)' }}>{i.codigo}</span>
                  </td>
                  <td>
                    <span className={`bio-badge ${categorias[i.categoria].badge}`}>
                      <i className={`fa-solid ${categorias[i.categoria].icon}`} aria-hidden /> {categorias[i.categoria].label}
                    </span>
                  </td>
                  <td>{i.unidad}</td>
                  <td className="num">
                    <input
                      type="number"
                      min={0}
                      step="any"
                      className="bio-input bio-input-num"
                      value={cant[i.id] ?? ''}
                      onChange={(e) => setCant((c) => ({ ...c, [i.id]: e.target.value }))}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
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
