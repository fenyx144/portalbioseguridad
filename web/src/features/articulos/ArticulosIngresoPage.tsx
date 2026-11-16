import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { EmptyState, PageHero } from '../../shared/ui/Kpi'
import { Modal } from '../../shared/ui/Modal'
import { useAuth } from '../auth/useAuth'
import { type ArticuloIngreso, type ReglaArticulo, useArticulosIngreso } from './api'

const reglas: { value: ReglaArticulo; label: string; hint: string }[] = [
  { value: 'PERMITIDO', label: 'Permitido', hint: 'Foto o desinfección obligatoria solo si la nota lo indica' },
  { value: 'PROHIBIDO', label: 'Prohibido', hint: 'Anomalía si ingresa con el artículo' },
  { value: 'CRITERIO', label: 'Criterio del guardia', hint: 'Referencia; decide el operario' },
]

const vacio = {
  codigo: '',
  nombre: '',
  regla: 'CRITERIO' as ReglaArticulo,
  nota: '',
  orden: '0',
  estado: 'ACTIVO' as 'ACTIVO' | 'INACTIVO',
}

function reglaBadge(regla: ReglaArticulo) {
  if (regla === 'PERMITIDO') return 'bio-badge-ok'
  if (regla === 'PROHIBIDO') return 'bio-badge-danger'
  return 'bio-badge-muted'
}

export function ArticulosIngresoPage() {
  const { user } = useAuth()
  const esGrs = (user?.actor ?? 'grs') === 'grs'
  const qc = useQueryClient()
  const list = useArticulosIngreso(true)
  const [edit, setEdit] = useState<ArticuloIngreso | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(vacio)
  const [error, setError] = useState('')

  const save = useMutation({
    mutationFn: () => {
      const body = {
        codigo: form.codigo.trim(),
        nombre: form.nombre.trim(),
        regla: form.regla,
        nota: form.nota.trim(),
        orden: Number(form.orden) || 0,
        estado: form.estado,
      }
      return edit
        ? api.patch(`/articulos-ingreso/${edit.id}`, body)
        : api.post('/articulos-ingreso', body)
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['articulos-ingreso'] })
      setOpen(false)
    },
    onError: (e) => setError(e instanceof ApiClientError ? e.message : 'No se pudo guardar'),
  })

  const abrir = (a: ArticuloIngreso | null) => {
    setEdit(a)
    setError('')
    setForm(
      a
        ? {
            codigo: a.codigo,
            nombre: a.nombre,
            regla: a.regla,
            nota: a.nota,
            orden: String(a.orden),
            estado: a.estado,
          }
        : vacio,
    )
    setOpen(true)
  }

  const set =
    (k: keyof typeof form) => (e: { target: { value: string } }) =>
      setForm((f) => ({ ...f, [k]: e.target.value }))

  const items = list.data?.items ?? []

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-suitcase"
        title="Artículos en ingreso"
        subtitle="Catálogo descargado por la app móvil (control de artículos)."
        actions={
          esGrs ? (
            <Button icon="fa-plus" onClick={() => abrir(null)}>
              Nuevo
            </Button>
          ) : null
        }
      />
      <p className="bio-help">
        <strong>Permitido</strong> y <strong>Prohibido</strong> guían la app; <strong>Criterio del guardia</strong> es solo referencia para el operario.
      </p>
      <div className="bio-card" style={{ padding: 0 }}>
        {items.length === 0 ? (
          <EmptyState icon="fa-suitcase">
            {list.isLoading ? 'Cargando…' : 'Sin artículos. Ejecute la migración 011 o cree registros.'}
          </EmptyState>
        ) : (
          <div className="bio-table-wrap">
            <table className="bio-table">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Artículo</th>
                  <th>Regla</th>
                  <th>Nota</th>
                  <th className="num">Orden</th>
                  <th>Estado</th>
                  {esGrs ? <th /> : null}
                </tr>
              </thead>
              <tbody>
                {items.map((a) => (
                  <tr key={a.id}>
                    <td>
                      <span className="bio-badge bio-badge-accent">{a.codigo}</span>
                    </td>
                    <td>{a.nombre}</td>
                    <td>
                      <span className={`bio-badge ${reglaBadge(a.regla)}`}>
                        {reglas.find((r) => r.value === a.regla)?.label ?? a.regla}
                      </span>
                    </td>
                    <td>{a.nota || '—'}</td>
                    <td className="num">{a.orden}</td>
                    <td>
                      <span className={`bio-badge ${a.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-muted'}`}>
                        {a.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}
                      </span>
                    </td>
                    {esGrs ? (
                      <td style={{ textAlign: 'right' }}>
                        <button type="button" className="bio-icon-action" title="Editar" onClick={() => abrir(a)}>
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
      {esGrs ? (
        <Modal
          open={open}
          onClose={() => setOpen(false)}
          icon="fa-suitcase"
          title={edit ? 'Editar artículo' : 'Nuevo artículo'}
          footer={
            <>
              <Button variant="ghost" onClick={() => setOpen(false)}>
                Cancelar
              </Button>
              <Button
                icon="fa-check"
                disabled={!form.codigo || !form.nombre || save.isPending}
                onClick={() => save.mutate()}
              >
                Guardar
              </Button>
            </>
          }
        >
          {error ? <p className="bio-error">{error}</p> : null}
          <div className="bio-form-grid">
            <label>
              <span className="bio-label">Código</span>
              <input className="bio-input" maxLength={20} value={form.codigo} onChange={set('codigo')} />
            </label>
            <label>
              <span className="bio-label">Orden</span>
              <input type="number" min={0} className="bio-input" value={form.orden} onChange={set('orden')} />
            </label>
            <label style={{ gridColumn: '1 / -1' }}>
              <span className="bio-label">Nombre / descripción</span>
              <input className="bio-input" maxLength={120} value={form.nombre} onChange={set('nombre')} />
            </label>
            <label>
              <span className="bio-label">Regla en granja</span>
              <select className="bio-select" value={form.regla} onChange={set('regla')}>
                {reglas.map((r) => (
                  <option key={r.value} value={r.value}>
                    {r.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="bio-label">Estado</span>
              <select className="bio-select" value={form.estado} onChange={set('estado')}>
                <option value="ACTIVO">Activo</option>
                <option value="INACTIVO">Inactivo</option>
              </select>
            </label>
            <label style={{ gridColumn: '1 / -1' }}>
              <span className="bio-label">Nota para el operario (opcional)</span>
              <input className="bio-input" maxLength={255} value={form.nota} onChange={set('nota')} />
            </label>
          </div>
        </Modal>
      ) : null}
    </div>
  )
}