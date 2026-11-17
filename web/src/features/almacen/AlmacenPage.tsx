import { useQuery } from '@tanstack/react-query'
import { useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { ApiClientError, api } from '../../shared/api/client'
import { formatDate, todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { EmptyState, Kpi, PageHero } from '../../shared/ui/Kpi'
import { useAuth } from '../auth/useAuth'
import { almacenTabs, ProcessTabs } from '../layout/processNav'
import {
  fmt,
  useInsumos,
  useKardexGranja,
  useMovimientoGranja,
  useStockGranja,
  type OperacionAlmacen,
} from './api'

const GRANJA_KEY = 'bio.almacen.granja'

const operaciones: { value: OperacionAlmacen; label: string }[] = [
  { value: 'SI_UNIFORME', label: 'Saldo inicial de uniforme' },
  { value: 'CARGA_UNIFORME', label: 'Carga de uniforme' },
  { value: 'ENTREGA', label: 'Entrega de uniforme' },
  { value: 'RECEPCION', label: 'Recepción de uniforme' },
  { value: 'LAVADO', label: 'Lavado' },
  { value: 'SI_INSUMO', label: 'Saldo inicial de insumo' },
  { value: 'CARGA_INSUMO', label: 'Carga de insumo' },
  { value: 'CONSUMO', label: 'Consumo de insumo' },
]

const conceptos: Record<string, string> = {
  CARGA: 'Carga',
  ENTREGA: 'Entrega',
  RECEPCION: 'Recepción',
  LAVADO: 'Lavado',
  CONSUMO: 'Consumo',
}

function vistaDe(path: string): 'stock' | 'kardex' | 'registro' {
  if (path.endsWith('/kardex')) return 'kardex'
  if (path.endsWith('/registro')) return 'registro'
  return 'stock'
}

export function AlmacenPage() {
  const { pathname } = useLocation()
  const vista = vistaDe(pathname)
  const { user } = useAuth()
  const [granja, setGranja] = useState(() => sessionStorage.getItem(GRANJA_KEY) ?? '')
  const [almacen, setAlmacen] = useState('')
  const [clase, setClase] = useState('todos')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [op, setOp] = useState<OperacionAlmacen>('ENTREGA')
  const [fecha, setFecha] = useState(todayIso())
  const [cantidad, setCantidad] = useState('1')
  const [ropa, setRopa] = useState('2')
  const [deposito, setDeposito] = useState('LIMPIO')
  const [insumoId, setInsumoId] = useState('')
  const [dni, setDni] = useState('')
  const [trabajador, setTrabajador] = useState('')
  const [obs, setObs] = useState('')
  const [aviso, setAviso] = useState<{ ok: boolean; msg: string } | null>(null)

  useEffect(() => {
    sessionStorage.setItem(GRANJA_KEY, granja)
  }, [granja])

  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })
  const stock = useStockGranja(granja)
  const kardex = useKardexGranja({ granja_cod: granja, almacen, desde, hasta, clase })
  const insumos = useInsumos()
  const guardar = useMovimientoGranja()

  const resumen = useMemo(() => stock.data?.resumen ?? [], [stock.data])
  const limpio = resumen.reduce((s, r) => s + Number(r.limpio), 0)
  const lavadero = resumen.reduce((s, r) => s + Number(r.lavadero), 0)
  const esInsumo = op === 'SI_INSUMO' || op === 'CARGA_INSUMO' || op === 'CONSUMO'
  const esPersona = op === 'ENTREGA' || op === 'RECEPCION'
  const esCargaUnif = op === 'SI_UNIFORME' || op === 'CARGA_UNIFORME'
  const nombreGranja = (cod: string, nombre: string) => (nombre && nombre !== cod ? `${cod} - ${nombre}` : cod)

  const registrar = () => {
    setAviso(null)
    if (!granja) {
      setAviso({ ok: false, msg: 'Granja requerida' })
      return
    }
    guardar.mutate(
      {
        operacion: op,
        granja_cod: granja,
        fecha,
        cantidad: Number(cantidad),
        ropa_tipo: Number(ropa),
        almacen: deposito,
        insumo_id: Number(insumoId || 0),
        dni,
        trabajador,
        observacion: obs,
      },
      {
        onSuccess: () => {
          setAviso({ ok: true, msg: 'Registrado' })
          setObs('')
        },
        onError: (e) => setAviso({ ok: false, msg: e instanceof ApiClientError ? e.message : 'Error' }),
      },
    )
  }

  return (
    <div className="bio-almacen" style={{ display: 'grid', gap: '1rem' }}>
      <PageHero icon="fa-warehouse" title="Almacén" subtitle={user?.actor === 'empresa' ? user.empresa_nombre : undefined} />
      <ProcessTabs items={almacenTabs} label="Almacén" />

      <div className="bio-card bio-filter-bar">
        <label style={{ flex: '1 1 16rem' }}>
          <span className="bio-label">Granja</span>
          <select className="bio-select" value={granja} onChange={(e) => setGranja(e.target.value)}>
            <option value="">{vista === 'stock' ? 'Todas' : 'Seleccionar'}</option>
            {(granjas.data?.items ?? []).map((g) => (
              <option key={g.codigo} value={g.codigo}>
                {g.codigo} - {g.nombre}
              </option>
            ))}
          </select>
        </label>
      </div>

      {vista === 'stock' ? (
        <>
          <div className="bio-kpis">
            <Kpi icon="fa-shirt" label="Almacén limpio" value={fmt(limpio)} tone="ok" />
            <Kpi icon="fa-soap" label="Lavadero" value={fmt(lavadero)} />
          </div>
          <div className="bio-card" style={{ padding: 0 }}>
            {resumen.length === 0 ? (
              <EmptyState icon="fa-warehouse">{stock.isLoading ? 'Cargando…' : 'Sin stock'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>Granja</th>
                      <th className="num">Almacén limpio</th>
                      <th className="num">Lavadero</th>
                    </tr>
                  </thead>
                  <tbody>
                    {resumen.map((r) => (
                      <tr key={r.granja_cod}>
                        <td>{nombreGranja(r.granja_cod, r.granja_nombre)}</td>
                        <td className="num">{fmt(r.limpio)}</td>
                        <td className="num">{fmt(r.lavadero)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
          <div className="bio-card" style={{ padding: 0 }}>
            {(stock.data?.insumos ?? []).length === 0 ? (
              <EmptyState icon="fa-box">{stock.isLoading ? 'Cargando…' : 'Sin insumos'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      {!granja ? <th>Granja</th> : null}
                      <th>Insumo</th>
                      <th>Unidad</th>
                      <th className="num">Stock</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(stock.data?.insumos ?? []).map((r) => (
                      <tr key={`${r.granja_cod}-${r.insumo_id}`}>
                        {!granja ? <td>{nombreGranja(r.granja_cod, r.granja_nombre)}</td> : null}
                        <td>{r.nombre || r.codigo}</td>
                        <td>{r.unidad}</td>
                        <td className="num">{fmt(r.stock)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      ) : null}

      {vista === 'kardex' ? (
        <>
          <div className="bio-card bio-filter-bar">
            <label>
              <span className="bio-label">Clase</span>
              <select className="bio-select" value={clase} onChange={(e) => setClase(e.target.value)}>
                <option value="todos">Todos</option>
                <option value="uniforme">Uniformes</option>
                <option value="insumo">Insumos</option>
              </select>
            </label>
            <label>
              <span className="bio-label">Almacén</span>
              <select className="bio-select" value={almacen} onChange={(e) => setAlmacen(e.target.value)}>
                <option value="">Todos</option>
                <option value="LIMPIO">Almacén limpio</option>
                <option value="LAVADERO">Lavadero</option>
              </select>
            </label>
            <label>
              <span className="bio-label">Desde</span>
              <input className="bio-input" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </label>
            <label>
              <span className="bio-label">Hasta</span>
              <input className="bio-input" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </label>
          </div>
          <div className="bio-card" style={{ padding: 0 }}>
            {!granja ? (
              <EmptyState icon="fa-warehouse">Seleccionar granja</EmptyState>
            ) : (kardex.data?.items ?? []).length === 0 ? (
              <EmptyState icon="fa-list">{kardex.isLoading ? 'Cargando…' : 'Sin movimientos'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Concepto</th>
                      <th>Detalle</th>
                      <th>Almacén</th>
                      <th>SI/E/S</th>
                      <th className="num">Cantidad</th>
                      <th className="num">Stock</th>
                      <th>Trabajador</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(kardex.data?.items ?? []).map((m) => (
                      <tr key={`${m.clase}-${m.id}`}>
                        <td>{formatDate(m.fecha)}</td>
                        <td>{conceptos[m.concepto] ?? m.concepto}</td>
                        <td>{m.clase === 'uniforme' ? m.ropa_nombre : m.insumo_nombre}</td>
                        <td>{m.almacen_nombre || '—'}</td>
                        <td>{m.tipo_mov}</td>
                        <td className="num">{fmt(m.cantidad)}</td>
                        <td className="num">{fmt(m.stock)}</td>
                        <td>{m.trabajador || m.dni || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      ) : null}

      {vista === 'registro' ? (
        <div className="bio-card bio-card-pad" style={{ display: 'grid', gap: '0.85rem', maxWidth: '46rem' }}>
          <div className="bio-filter-bar" style={{ padding: 0, border: 0, background: 'transparent' }}>
            <label style={{ flex: '1 1 16rem' }}>
              <span className="bio-label">Operación</span>
              <select className="bio-select" value={op} onChange={(e) => setOp(e.target.value as OperacionAlmacen)}>
                {operaciones.map((o) => (
                  <option key={o.value} value={o.value}>
                    {o.label}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="bio-label">Fecha</span>
              <input className="bio-input" type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
            </label>
            <label>
              <span className="bio-label">Cantidad</span>
              <input className="bio-input" type="number" min="0.01" step="0.01" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
            </label>
            {!esInsumo ? (
              <label>
                <span className="bio-label">Tipo de ropa</span>
                <select className="bio-select" value={ropa} onChange={(e) => setRopa(e.target.value)}>
                  <option value="2">Ropa para el proceso</option>
                  <option value="1">Ropa de tránsito</option>
                </select>
              </label>
            ) : (
              <label style={{ flex: '1 1 16rem' }}>
                <span className="bio-label">Insumo</span>
                <select className="bio-select" value={insumoId} onChange={(e) => setInsumoId(e.target.value)}>
                  <option value="">Seleccionar</option>
                  {(insumos.data?.items ?? []).map((i) => (
                    <option key={i.id} value={i.id}>
                      {i.nombre}
                    </option>
                  ))}
                </select>
              </label>
            )}
            {esCargaUnif ? (
              <label>
                <span className="bio-label">Almacén</span>
                <select className="bio-select" value={deposito} onChange={(e) => setDeposito(e.target.value)}>
                  <option value="LIMPIO">Almacén limpio</option>
                  <option value="LAVADERO">Lavadero</option>
                </select>
              </label>
            ) : null}
            {esPersona ? (
              <>
                <label>
                  <span className="bio-label">DNI</span>
                  <input className="bio-input" value={dni} onChange={(e) => setDni(e.target.value)} />
                </label>
                <label style={{ flex: '1 1 14rem' }}>
                  <span className="bio-label">Trabajador</span>
                  <input className="bio-input" value={trabajador} onChange={(e) => setTrabajador(e.target.value)} />
                </label>
              </>
            ) : null}
            <label style={{ flex: '1 1 16rem' }}>
              <span className="bio-label">Observación</span>
              <input className="bio-input" value={obs} onChange={(e) => setObs(e.target.value)} />
            </label>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <Button icon="fa-check" onClick={registrar} disabled={guardar.isPending}>
              Guardar
            </Button>
            {aviso ? <span className={`bio-badge ${aviso.ok ? 'bio-badge-ok' : 'bio-badge-danger'}`}>{aviso.msg}</span> : null}
          </div>
        </div>
      ) : null}
    </div>
  )
}
