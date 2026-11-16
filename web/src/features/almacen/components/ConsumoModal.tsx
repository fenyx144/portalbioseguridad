import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { ApiClientError, api } from '../../../shared/api/client'
import { todayIso } from '../../../shared/lib/dates'
import { Button } from '../../../shared/ui/Button'
import { Modal } from '../../../shared/ui/Modal'
import { useCrearConsumo, useInsumos } from '../api'

type Props = {
  open: boolean
  onClose: () => void
  esGrs?: boolean
}

export function ConsumoModal({ open, onClose, esGrs }: Props) {
  const insumos = useInsumos()
  const crear = useCrearConsumo()
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; razon_social: string }[] }>('/empresas'),
    enabled: esGrs && open,
  })
  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
    enabled: open,
  })
  const [f, setF] = useState({
    empresa_id: '',
    insumo_id: '',
    cantidad: '',
    fecha: todayIso(),
    granja_cod: '',
    observacion: '',
  })
  const [error, setError] = useState('')
  useEffect(() => {
    if (open) {
      setF({ empresa_id: '', insumo_id: '', cantidad: '', fecha: todayIso(), granja_cod: '', observacion: '' })
      setError('')
    }
  }, [open])
  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF((x) => ({ ...x, [k]: e.target.value }))

  async function submit() {
    try {
      await crear.mutateAsync({
        ...(esGrs && f.empresa_id ? { empresa_id: Number(f.empresa_id) } : {}),
        insumo_id: Number(f.insumo_id),
        cantidad: Number(f.cantidad),
        fecha: f.fecha,
        granja_cod: f.granja_cod,
        observacion: f.observacion,
      })
      onClose()
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo registrar')
    }
  }

  const puedeGuardar =
    f.insumo_id &&
    Number(f.cantidad) > 0 &&
    f.fecha &&
    (!esGrs || f.empresa_id) &&
    !crear.isPending

  return (
    <Modal
      open={open}
      onClose={onClose}
      icon="fa-arrow-right-from-bracket"
      title="Registrar consumo"
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button icon="fa-check" disabled={!puedeGuardar} onClick={() => void submit()}>
            Guardar
          </Button>
        </>
      }
    >
      <div className="bio-form-grid" style={{ gap: '0.85rem' }}>
        {esGrs ? (
          <label style={{ gridColumn: '1 / -1' }}>
            <span className="bio-label">Empresa contratista</span>
            <select className="bio-select" value={f.empresa_id} onChange={set('empresa_id')}>
              <option value="">Seleccione…</option>
              {(empresas.data?.items ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.razon_social}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        <label style={{ gridColumn: '1 / -1' }}>
          <span className="bio-label">Insumo</span>
          <select className="bio-select" value={f.insumo_id} onChange={set('insumo_id')}>
            <option value="">Seleccione…</option>
            {(insumos.data?.items ?? []).map((i) => (
              <option key={i.id} value={i.id}>
                {i.nombre} ({i.unidad})
              </option>
            ))}
          </select>
        </label>
        <label>
          <span className="bio-label">Cantidad</span>
          <input type="number" min={1} step={1} className="bio-input" value={f.cantidad} onChange={set('cantidad')} />
        </label>
        <label>
          <span className="bio-label">Fecha</span>
          <input type="date" className="bio-input" value={f.fecha} onChange={set('fecha')} />
        </label>
        <label style={{ gridColumn: '1 / -1' }}>
          <span className="bio-label">Granja</span>
          <select className="bio-select" value={f.granja_cod} onChange={set('granja_cod')}>
            <option value="">Sin granja / general</option>
            {(granjas.data?.items ?? []).map((g) => (
              <option key={g.codigo} value={g.codigo}>
                {g.codigo} · {g.nombre}
              </option>
            ))}
          </select>
        </label>
        <label style={{ gridColumn: '1 / -1' }}>
          <span className="bio-label">Observación</span>
          <input className="bio-input" maxLength={255} value={f.observacion} onChange={set('observacion')} />
        </label>
      </div>
      {error ? (
        <div className="bio-alert" data-tone="danger" style={{ marginTop: '0.6rem' }}>
          {error}
        </div>
      ) : null}
    </Modal>
  )
}
