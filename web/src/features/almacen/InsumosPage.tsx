import { useState } from 'react'
import { ApiClientError } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { EmptyState, PageHero } from '../../shared/ui/Kpi'
import { Modal } from '../../shared/ui/Modal'
import { categorias, useGuardarInsumo, useInsumos, type Categoria, type Insumo } from './api'

const vacio: Omit<Insumo, 'id'> = { codigo: '', nombre: '', categoria: 'INDUMENTARIA', unidad: 'UND', estado: 'ACTIVO' }

export function InsumosPage() {
  const insumos = useInsumos(true)
  const guardar = useGuardarInsumo()
  const [edit, setEdit] = useState<Insumo | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(vacio)
  const [error, setError] = useState('')

  const abrir = (i: Insumo | null) => {
    setEdit(i)
    setError('')
    setForm(i ? { codigo: i.codigo, nombre: i.nombre, categoria: i.categoria, unidad: i.unidad, estado: i.estado } : vacio)
    setOpen(true)
  }
  async function submit() {
    try {
      await guardar.mutateAsync({ id: edit?.id, body: form })
      setOpen(false)
    } catch (e) {
      setError(e instanceof ApiClientError ? e.message : 'No se pudo guardar')
    }
  }
  const items = insumos.data?.items ?? []

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-boxes-packing"
        title="Insumos"
        actions={
          <Button icon="fa-plus" onClick={() => abrir(null)}>
            Nuevo insumo
          </Button>
        }
      />
      <div className="bio-card" style={{ padding: 0 }}>
        {items.length === 0 ? (
          <EmptyState icon="fa-boxes-packing">{insumos.isLoading ? 'Cargando…' : 'Sin insumos'}</EmptyState>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Nombre</th>
                  <th>Categoría</th>
                  <th>Unidad</th>
                  <th>Estado</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {items.map((i) => (
                  <tr key={i.id}>
                    <td>
                      <span className="bio-badge bio-badge-muted">{i.codigo}</span>
                    </td>
                    <td>{i.nombre}</td>
                    <td>
                      <span className={`bio-badge ${categorias[i.categoria].badge}`}>
                        <i className={`fa-solid ${categorias[i.categoria].icon}`} aria-hidden /> {categorias[i.categoria].label}
                      </span>
                    </td>
                    <td>{i.unidad}</td>
                    <td>
                      <span className={`bio-badge ${i.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-muted'}`}>{i.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <button type="button" className="bio-icon-action" title="Editar" onClick={() => abrir(i)}>
                        <i className="fa-solid fa-pen" aria-hidden />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        icon="fa-box"
        title={edit ? 'Editar insumo' : 'Nuevo insumo'}
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)}>
              Cancelar
            </Button>
            <Button icon="fa-check" disabled={!form.codigo || !form.nombre || guardar.isPending} onClick={() => void submit()}>
              Guardar
            </Button>
          </>
        }
      >
        <div className="bio-form-grid">
          <label>
            <span className="bio-label">Código</span>
            <input className="bio-input" maxLength={20} value={form.codigo} onChange={(e) => setForm({ ...form, codigo: e.target.value })} />
          </label>
          <label>
            <span className="bio-label">Unidad</span>
            <input className="bio-input" maxLength={20} value={form.unidad} onChange={(e) => setForm({ ...form, unidad: e.target.value })} />
          </label>
          <label style={{ gridColumn: '1 / -1' }}>
            <span className="bio-label">Nombre</span>
            <input className="bio-input" maxLength={120} value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} />
          </label>
          <label>
            <span className="bio-label">Categoría</span>
            <select className="bio-select" value={form.categoria} onChange={(e) => setForm({ ...form, categoria: e.target.value as Categoria })}>
              {Object.entries(categorias).map(([k, v]) => (
                <option key={k} value={k}>
                  {v.label}
                </option>
              ))}
            </select>
          </label>
          <label>
            <span className="bio-label">Estado</span>
            <select className="bio-select" value={form.estado} onChange={(e) => setForm({ ...form, estado: e.target.value as Insumo['estado'] })}>
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
          </label>
        </div>
        {error ? (
          <div className="bio-alert" data-tone="danger" style={{ marginTop: '0.6rem' }}>
            {error}
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
