import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { EmptyState, PageHero } from '../../shared/ui/Kpi'
import { Modal } from '../../shared/ui/Modal'
import { NoticeDialog } from '../../shared/ui/NoticeDialog'
import { Tabs } from '../../shared/ui/Tabs'
import { categorias, fmt, useGuardarInsumo, useGuardarSaldos, useInsumos, useStock, type Categoria, type Insumo } from './api'

const vacio: Omit<Insumo, 'id'> = { codigo: '', nombre: '', categoria: 'INDUMENTARIA', unidad: 'UND', estado: 'ACTIVO' }

export function InsumosPage() {
  const [tab, setTab] = useState<'catalogo' | 'saldos'>('catalogo')
  const insumos = useInsumos(true)
  const guardar = useGuardarInsumo()
  const [edit, setEdit] = useState<Insumo | null>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(vacio)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState<string | null>(null)

  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; razon_social: string }[] }>('/empresas'),
  })
  const [empresaId, setEmpresaId] = useState('')
  const stock = useStock(empresaId || '0')
  const guardarSaldos = useGuardarSaldos()
  const [saldos, setSaldos] = useState<Record<number, string>>({})

  useEffect(() => {
    if (!empresaId) return setSaldos({})
    setSaldos(
      Object.fromEntries((stock.data?.items ?? []).filter((r) => String(r.empresa_id) === empresaId).map((r) => [r.insumo_id, String(r.saldo_inicial)])),
    )
  }, [stock.data, empresaId])

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
  async function submitSaldos() {
    try {
      const r = await guardarSaldos.mutateAsync({
        empresa_id: Number(empresaId),
        items: Object.entries(saldos)
          .filter(([, v]) => v !== '')
          .map(([k, v]) => ({ insumo_id: Number(k), saldo_inicial: Number(v) })),
      })
      setNotice(`Saldos guardados: ${r.guardados}`)
    } catch (e) {
      setNotice(e instanceof ApiClientError ? e.message : 'No se pudo guardar')
    }
  }
  const items = insumos.data?.items ?? []

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-boxes-packing"
        title="Insumos"
        actions={
          tab === 'catalogo' ? (
            <Button icon="fa-plus" onClick={() => abrir(null)}>
              Nuevo insumo
            </Button>
          ) : (
            <Button icon="fa-floppy-disk" disabled={!empresaId || guardarSaldos.isPending} onClick={() => void submitSaldos()}>
              Guardar saldos
            </Button>
          )
        }
      />
      <Tabs
        items={[
          { value: 'catalogo', label: 'Catálogo', icon: 'fa-list', count: items.length },
          { value: 'saldos', label: 'Saldo inicial por empresa', icon: 'fa-scale-balanced' },
        ]}
        value={tab}
        onChange={setTab}
      />
      {tab === 'saldos' ? (
        <div className="bio-card bio-filter-bar">
          <label>
            <span className="bio-label">Empresa</span>
            <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
              <option value="">Seleccione…</option>
              {(empresas.data?.items ?? []).map((e) => (
                <option key={e.id} value={e.id}>
                  {e.razon_social}
                </option>
              ))}
            </select>
          </label>
        </div>
      ) : null}
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
                  {tab === 'catalogo' ? <th>Estado</th> : <th className="num">Saldo inicial</th>}
                  {tab === 'catalogo' ? <th /> : null}
                </tr>
              </thead>
              <tbody>
                {items
                  .filter((i) => tab === 'catalogo' || i.estado === 'ACTIVO')
                  .map((i) => (
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
                      {tab === 'catalogo' ? (
                        <>
                          <td>
                            <span className={`bio-badge ${i.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-muted'}`}>{i.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</span>
                          </td>
                          <td style={{ textAlign: 'right' }}>
                            <button type="button" className="bio-icon-action" title="Editar" onClick={() => abrir(i)}>
                              <i className="fa-solid fa-pen" aria-hidden />
                            </button>
                          </td>
                        </>
                      ) : (
                        <td className="num">
                          <input
                            type="number"
                            min={0}
                            step="any"
                            className="bio-input bio-input-num"
                            disabled={!empresaId}
                            placeholder={empresaId ? fmt(0) : ''}
                            value={saldos[i.id] ?? ''}
                            onChange={(e) => setSaldos((s) => ({ ...s, [i.id]: e.target.value }))}
                          />
                        </td>
                      )}
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
      <NoticeDialog open={notice !== null} message={notice ?? ''} onClose={() => setNotice(null)} />
    </div>
  )
}
