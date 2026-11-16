import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { EmptyState, PageHero } from '../../shared/ui/Kpi'
import { Modal } from '../../shared/ui/Modal'
import { useCatalogo, type Cap } from './api'

const vacio = { codigo: '', nombre: '', vigencia_meses: '12', estado: 'ACTIVO', tipo: 'OTRO' }

export function CapacitacionCatalogoPage() {
  const qc = useQueryClient()
  const list = useCatalogo(true)
  const puedeEditar = list.data?.permisos?.catalogo_editar ?? false
  const [edit, setEdit] = useState<Cap | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(vacio)
  const [error, setError] = useState('')

  const save = useMutation({
    mutationFn: () => {
      const body = { ...form, vigencia_meses: Number(form.vigencia_meses) || 0 }
      return edit ? api.patch(`/capacitaciones/${edit.id}`, body) : api.post('/capacitaciones', body)
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['capacitaciones'] })
      setOpen(false)
    },
    onError: (e) => setError(e instanceof ApiClientError ? e.message : 'No se pudo guardar'),
  })

  const abrir = (c: Cap | null) => {
    setEdit(c)
    setError('')
    setForm(c ? { codigo: c.codigo, nombre: c.nombre, vigencia_meses: String(c.vigencia_meses), estado: c.estado, tipo: c.tipo || 'OTRO' } : vacio)
    setOpen(true)
  }
  const set = (k: keyof typeof form) => (e: { target: { value: string } }) => setForm((f) => ({ ...f, [k]: e.target.value }))
  const items = list.data?.items ?? []

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-book-open"
        title="Catálogo de capacitaciones"
        actions={
          puedeEditar ? (
            <Button icon="fa-plus" onClick={() => abrir(null)}>
              Nueva
            </Button>
          ) : null
        }
      />
      <div className="bio-card" style={{ padding: 0 }}>
        {items.length === 0 ? (
          <EmptyState icon="fa-book-open">{list.isLoading ? 'Cargando…' : 'Sin capacitaciones'}</EmptyState>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th className="num">Vigencia (meses)</th>
                  <th>Estado</th>
                  {puedeEditar ? <th /> : null}
                </tr>
              </thead>
              <tbody>
                {items.map((c) => (
                  <tr key={c.id}>
                    <td>
                      <span className="bio-badge bio-badge-accent">{c.codigo}</span>
                    </td>
                    <td>{c.nombre}</td>
                    <td>{c.tipo || 'OTRO'}</td>
                    <td className="num">{c.vigencia_meses || '—'}</td>
                    <td>
                      <span className={`bio-badge ${c.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-muted'}`}>{c.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</span>
                    </td>
                    {puedeEditar ? (
                      <td style={{ textAlign: 'right' }}>
                        <button type="button" className="bio-icon-action" title="Editar" onClick={() => abrir(c)}>
                          <i className="fa-solid fa-pen" aria-hidden />
                        </button>
                      </td>
                    ) : null}
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
        icon="fa-book-open"
        title={edit ? 'Editar capacitación' : 'Nueva capacitación'}
        footer={
          <>
            <Button variant="ghost" onClick={() => setOpen(false)}>
              Cancelar
            </Button>
            <Button icon="fa-check" disabled={!form.codigo || !form.nombre || save.isPending} onClick={() => save.mutate()}>
              Guardar
            </Button>
          </>
        }
      >
        <div className="bio-form-grid">
          <label>
            <span className="bio-label">Código</span>
            <input className="bio-input" maxLength={20} value={form.codigo} onChange={set('codigo')} />
          </label>
          <label>
            <span className="bio-label">Vigencia (meses)</span>
            <input type="number" min={0} className="bio-input" value={form.vigencia_meses} onChange={set('vigencia_meses')} />
          </label>
          <label style={{ gridColumn: '1 / -1' }}>
            <span className="bio-label">Nombre</span>
            <input className="bio-input" maxLength={120} value={form.nombre} onChange={set('nombre')} />
          </label>
          <label>
            <span className="bio-label">Tipo</span>
            <select className="bio-select" value={form.tipo} onChange={set('tipo')}>
              <option value="INDUCCION">Inducción</option>
              <option value="FORMACION">Formación</option>
              <option value="EVALUACION">Evaluación</option>
              <option value="OTRO">Otro</option>
            </select>
          </label>
          <label>
            <span className="bio-label">Estado</span>
            <select className="bio-select" value={form.estado} onChange={set('estado')}>
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
