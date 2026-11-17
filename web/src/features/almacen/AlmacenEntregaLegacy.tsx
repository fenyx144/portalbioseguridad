/**
 * Entrega GRS → proveedor/tercero. Fuera de uso: GRS ya no envía insumos ni
 * indumentaria al proveedor. Conservado, sin ruta. No borrar.
 */
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { ApiClientError, api } from '../../shared/api/client'
import { formatDate } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { ConfirmDialog } from '../../shared/ui/ConfirmDialog'
import { EmptyState, Kpi, PageHero } from '../../shared/ui/Kpi'
import { NoticeDialog } from '../../shared/ui/NoticeDialog'
import { Tabs } from '../../shared/ui/Tabs'
import { useAuth } from '../auth/useAuth'
import { almacenTabs, ProcessTabs } from '../layout/processNav'
import { estadosEntrega, fmt, useAnularEntrega, useConsumos, useEliminarConsumo, useEntregas, type Entrega } from './api'
import { ConsumoModal } from './components/ConsumoModal'
import { EntregaDetalleModal } from './components/EntregaDetalleModal'
import { EntregaFormModal } from './components/EntregaFormModal'

export function AlmacenEntregaLegacy() {
  const { user } = useAuth()
  const esGrs = user?.actor !== 'empresa'
  const [tab, setTab] = useState<'entregas' | 'consumos'>('entregas')
  const [estado, setEstado] = useState(esGrs ? '' : 'PENDIENTE')
  const [empresaId, setEmpresaId] = useState('')
  const [nueva, setNueva] = useState(false)
  const [consumo, setConsumo] = useState(false)
  const [detalle, setDetalle] = useState<number | null>(null)
  const [anular, setAnular] = useState<Entrega | null>(null)
  const [notice, setNotice] = useState<{ msg: string; kind: 'success' | 'error' } | null>(null)

  const entregas = useEntregas({ estado, empresa_id: empresaId })
  const todas = useEntregas({ empresa_id: empresaId })
  const consumos = useConsumos(tab === 'consumos')
  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; razon_social: string }[] }>('/empresas'),
    enabled: esGrs,
  })
  const anularM = useAnularEntrega()
  const eliminarConsumo = useEliminarConsumo()
  const all = todas.data?.items ?? []
  const cuenta = (e: string) => all.filter((x) => x.estado === e).length
  const items = entregas.data?.items ?? []
  const err = (e: unknown) => setNotice({ kind: 'error', msg: e instanceof ApiClientError ? e.message : 'Error' })

  return (
    <div style={{ display: 'grid', gap: '1rem' }}>
      <PageHero
        icon="fa-warehouse"
        title={esGrs ? 'Entregas de insumos' : 'Almacén'}
        subtitle={esGrs ? undefined : user?.empresa_nombre ?? undefined}
        actions={
          <>
            {esGrs ? (
              <Button icon="fa-plus" onClick={() => setNueva(true)}>
                Nueva entrega
              </Button>
            ) : null}
            <Button variant="soft" icon="fa-arrow-right-from-bracket" onClick={() => setConsumo(true)}>
              Registrar consumo
            </Button>
          </>
        }
      />
      <ProcessTabs items={almacenTabs} label="Almacén" />
      <div className="bio-kpis">
        <Kpi icon="fa-hourglass-half" label="Pendientes de recepción" value={cuenta('PENDIENTE')} tone={cuenta('PENDIENTE') ? 'warn' : undefined} />
        <Kpi icon="fa-circle-check" label="Recibidas" value={cuenta('RECIBIDA')} />
        <Kpi icon="fa-triangle-exclamation" label="Con anomalías" value={cuenta('OBSERVADA')} tone={cuenta('OBSERVADA') ? 'danger' : undefined} />
      </div>

      <Tabs
        items={[
          {
            value: 'entregas',
            label: esGrs ? 'Entregas' : 'Entregas recibidas',
            icon: 'fa-truck-ramp-box',
            count: !esGrs && cuenta('PENDIENTE') ? cuenta('PENDIENTE') : undefined,
          },
          { value: 'consumos', label: 'Consumos', icon: 'fa-arrow-right-from-bracket' },
        ]}
        value={tab}
        onChange={setTab}
      />

      {tab === 'entregas' && (
        <>
          <div className="bio-card bio-filter-bar">
            {esGrs ? (
              <label style={{ flex: '1 1 14rem' }}>
                <span className="bio-label">Empresa</span>
                <select className="bio-select" value={empresaId} onChange={(e) => setEmpresaId(e.target.value)}>
                  <option value="">Todas</option>
                  {(empresas.data?.items ?? []).map((e) => (
                    <option key={e.id} value={e.id}>
                      {e.razon_social}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}
            <label>
              <span className="bio-label">Estado</span>
              <select className="bio-select" value={estado} onChange={(e) => setEstado(e.target.value)}>
                <option value="">Todos</option>
                {Object.entries(estadosEntrega).map(([k, v]) => (
                  <option key={k} value={k}>
                    {v.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <div className="bio-card" style={{ padding: 0 }}>
            {items.length === 0 ? (
              <EmptyState icon="fa-truck-ramp-box">{entregas.isLoading ? 'Cargando…' : 'Sin entregas'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Fecha</th>
                      {esGrs ? <th>Empresa</th> : null}
                      <th>Granja</th>
                      <th className="num">Ítems</th>
                      <th className="num">Cantidad</th>
                      <th>Estado</th>
                      <th>Anomalía</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((e) => (
                      <tr key={e.id} style={{ cursor: 'pointer' }} onClick={() => setDetalle(e.id)}>
                        <td>{e.id}</td>
                        <td>{formatDate(e.fecha)}</td>
                        {esGrs ? <td>{e.empresa_razon_social}</td> : null}
                        <td>{e.granja_cod || '—'}</td>
                        <td className="num">{e.num_items}</td>
                        <td className="num">{fmt(e.total_cantidad)}</td>
                        <td>
                          <span className={`bio-badge ${estadosEntrega[e.estado].badge}`}>{estadosEntrega[e.estado].label}</span>
                        </td>
                        <td style={{ maxWidth: '16rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={e.anomalia}>
                          {e.anomalia || '—'}
                        </td>
                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }} onClick={(ev) => ev.stopPropagation()}>
                          <button type="button" className="bio-icon-action" title="Ver detalle" onClick={() => setDetalle(e.id)}>
                            <i className="fa-solid fa-eye" aria-hidden />
                          </button>
                          {!esGrs && e.estado === 'PENDIENTE' ? (
                            <Button size="sm" icon="fa-check" onClick={() => setDetalle(e.id)}>
                              Confirmar
                            </Button>
                          ) : null}
                          {esGrs && e.estado === 'PENDIENTE' ? (
                            <button type="button" className="bio-icon-action danger" title="Anular" onClick={() => setAnular(e)}>
                              <i className="fa-solid fa-ban" aria-hidden />
                            </button>
                          ) : null}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}
      {tab === 'consumos' && (
        <div className="bio-card" style={{ padding: 0 }}>
          {(consumos.data?.items ?? []).length === 0 ? (
            <EmptyState icon="fa-arrow-right-from-bracket">{consumos.isLoading ? 'Cargando…' : 'Sin consumos'}</EmptyState>
          ) : (
            <div className="bio-table-wrap">
              <table className="bio-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Insumo</th>
                    <th className="num">Cantidad</th>
                    {esGrs ? <th>Empresa</th> : null}
                    <th>Granja</th>
                    <th>Observación</th>
                    {!esGrs ? <th /> : null}
                  </tr>
                </thead>
                <tbody>
                  {(consumos.data?.items ?? []).map((c) => (
                    <tr key={c.id}>
                      <td>{formatDate(c.fecha)}</td>
                      <td>{c.insumo_nombre}</td>
                      <td className="num">
                        {fmt(c.cantidad)} {c.unidad}
                      </td>
                      {esGrs ? <td>{c.empresa_razon_social ?? '—'}</td> : null}
                      <td>{c.granja_cod || '—'}</td>
                      <td>{c.observacion || '—'}</td>
                      {!esGrs ? (
                        <td style={{ textAlign: 'right' }}>
                          <button type="button" className="bio-icon-action danger" title="Eliminar" onClick={() => eliminarConsumo.mutate(c.id, { onError: err })}>
                            <i className="fa-solid fa-trash" aria-hidden />
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
      )}

      {esGrs ? (
        <EntregaFormModal
          open={nueva}
          onClose={() => setNueva(false)}
          onSaved={(id) => {
            setNueva(false)
            setNotice({ kind: 'success', msg: `Entrega #${id} registrada.` })
          }}
        />
      ) : null}
      <ConsumoModal open={consumo} onClose={() => setConsumo(false)} esGrs={esGrs} />
      <EntregaDetalleModal
        id={detalle}
        puedeConfirmar={!esGrs}
        onClose={() => setDetalle(null)}
        onDone={(msg) => {
          setDetalle(null)
          setNotice({ kind: 'success', msg })
        }}
      />
      <ConfirmDialog
        open={anular !== null}
        title="Anular entrega"
        message={anular ? `#${anular.id} · ${anular.empresa_razon_social} · ${formatDate(anular.fecha)}` : ''}
        confirmLabel="Anular"
        onCancel={() => setAnular(null)}
        onConfirm={() => {
          const a = anular
          setAnular(null)
          if (a) anularM.mutate(a.id, { onError: err })
        }}
      />
      <NoticeDialog open={notice !== null} kind={notice?.kind} message={notice?.msg ?? ''} onClose={() => setNotice(null)} />
    </div>
  )
}
