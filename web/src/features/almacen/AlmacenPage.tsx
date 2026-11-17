import { useQuery } from '@tanstack/react-query'
import type { ReactNode } from 'react'
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
  type Categoria,
  type CategoriaAlmacen,
  type OperacionAlmacen,
} from './api'

const GRANJA_KEY = 'bio.almacen.granja'

const accionesGrs: { id: OperacionAlmacen; label: string; icon: string }[] = [
  { id: 'ENVIO', label: 'Envío a granja', icon: 'fa-truck' },
  { id: 'SI', label: 'Saldo inicial', icon: 'fa-boxes-stacked' },
]
const accionesEmpresa: { id: OperacionAlmacen; label: string; icon: string }[] = [
  { id: 'CONSUMO', label: 'Consumo', icon: 'fa-flask' },
  { id: 'ENTREGA', label: 'Entrega', icon: 'fa-shirt' },
  { id: 'DEVOLUCION', label: 'Devolución', icon: 'fa-rotate-left' },
  { id: 'LAVADO', label: 'Lavado', icon: 'fa-soap' },
  { id: 'RELEVO', label: 'Relevo', icon: 'fa-people-arrows' },
]

const nombresOp: Record<string, string> = {
  ENVIO: 'Envío',
  SI: 'Saldo inicial',
  CONSUMO: 'Consumo',
  ENTREGA: 'Entrega',
  DEVOLUCION: 'Devolución',
  LAVADO: 'Lavado',
  RELEVO: 'Relevo',
}
const nombresCat: Record<string, string> = {
  UNIFORME: 'Uniformes',
  IMPLEMENTO: 'Implementos',
  INSUMO: 'Insumos',
}
const nombresFrente: Record<string, string> = {
  LIMPIO: 'Almacén limpio',
  LAVADERO: 'Lavadero',
  IMPLEMENTOS: 'Implementos',
  INSUMOS: 'Insumos',
}

function vistaDe(path: string): 'stock' | 'kardex' | 'registro' {
  if (path.endsWith('/kardex')) return 'kardex'
  if (path.endsWith('/registro')) return 'registro'
  return 'stock'
}

function esImplemento(c: Categoria) {
  return c === 'EPP' || c === 'INDUMENTARIA'
}

type Persona = { id: number; dni: string; nombres: string; apellidos: string }

export function AlmacenPage() {
  const { pathname } = useLocation()
  const vista = vistaDe(pathname)
  const { user } = useAuth()
  const esGrs = (user?.actor ?? 'grs') !== 'empresa'
  const acciones = esGrs ? accionesGrs : accionesEmpresa
  const [granja, setGranja] = useState(() => sessionStorage.getItem(GRANJA_KEY) ?? '')
  const [categoriaFiltro, setCategoriaFiltro] = useState('')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [op, setOp] = useState<OperacionAlmacen>(esGrs ? 'ENVIO' : 'CONSUMO')
  const opActual = acciones.some((a) => a.id === op) ? op : acciones[0].id
  const [cat, setCat] = useState<CategoriaAlmacen>('UNIFORME')
  const [item, setItem] = useState('2')
  const [fecha, setFecha] = useState(todayIso())
  const [cantidad, setCantidad] = useState('1')
  const [personalId, setPersonalId] = useState('')
  const [saleId, setSaleId] = useState('')
  const [entraId, setEntraId] = useState('')
  const [aviso, setAviso] = useState<{ ok: boolean; msg: string } | null>(null)

  useEffect(() => {
    sessionStorage.setItem(GRANJA_KEY, granja)
  }, [granja])

  const stock = useStockGranja(granja)
  const kardex = useKardexGranja({ granja_cod: granja, categoria: categoriaFiltro, desde, hasta }, vista === 'kardex')
  const catalogo = useInsumos()
  const guardar = useMovimientoGranja()
  const granjas = stock.data?.granjas ?? []
  const pidePersona = opActual === 'ENTREGA' || opActual === 'DEVOLUCION' || opActual === 'RELEVO'
  const personal = useQuery({
    queryKey: ['personal', 'almacen', granja],
    queryFn: () => api.get<{ items: Persona[] }>('/personal', { granja_base: granja, estado: 'ACTIVO', per_page: '500' }),
    enabled: granja !== '' && pidePersona,
  })

  useEffect(() => {
    if (granjas.length === 1 && granja !== granjas[0].codigo) setGranja(granjas[0].codigo)
    if (granja && granjas.length > 0 && !granjas.some((g) => g.codigo === granja)) setGranja('')
  }, [granjas, granja])

  const resumen = useMemo(() => stock.data?.resumen ?? [], [stock.data])
  const limpio = resumen.reduce((s, r) => s + Number(r.limpio), 0)
  const lavadero = resumen.reduce((s, r) => s + Number(r.lavadero), 0)
  const implementosCat = (catalogo.data?.items ?? []).filter((i) => esImplemento(i.categoria))
  const insumosCat = (catalogo.data?.items ?? []).filter((i) => !esImplemento(i.categoria))
  const personas = personal.data?.items ?? []
  const nombreGranja = (cod: string, nombre: string) => (nombre && nombre !== cod ? `${cod} - ${nombre}` : cod)
  const nombrePersona = (p: Persona) => `${p.dni} - ${p.apellidos} ${p.nombres}`

  const muestraCat = opActual === 'ENVIO' || opActual === 'SI' || opActual === 'ENTREGA' || opActual === 'DEVOLUCION'
  const cats: CategoriaAlmacen[] = opActual === 'ENTREGA' || opActual === 'DEVOLUCION' ? ['UNIFORME', 'IMPLEMENTO'] : ['UNIFORME', 'IMPLEMENTO', 'INSUMO']
  const catActiva: CategoriaAlmacen = opActual === 'CONSUMO' ? 'INSUMO' : opActual === 'LAVADO' || opActual === 'RELEVO' ? 'UNIFORME' : cats.includes(cat) ? cat : cats[0]
  const listaItems = catActiva === 'IMPLEMENTO' ? implementosCat : insumosCat

  const registrar = () => {
    setAviso(null)
    if (!granja) {
      setAviso({ ok: false, msg: 'Granja requerida' })
      return
    }
    const persona = personas.find((p) => String(p.id) === personalId)
    const saliente = personas.find((p) => String(p.id) === saleId)
    const entrante = personas.find((p) => String(p.id) === entraId)
    guardar.mutate(
      {
        operacion: opActual,
        granja_cod: granja,
        fecha,
        cantidad: opActual === 'RELEVO' ? 1 : Number(cantidad),
        categoria: catActiva,
        item: catActiva === 'UNIFORME' ? item || '2' : item,
        dni: persona?.dni ?? '',
        personal_id: persona?.id ?? 0,
        dni_sale: saliente?.dni ?? '',
        dni_entra: entrante?.dni ?? '',
        personal_id_sale: saliente?.id ?? 0,
        personal_id_entra: entrante?.id ?? 0,
        origen: 'web',
      },
      {
        onSuccess: () => setAviso({ ok: true, msg: 'Registrado' }),
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
            <option value="">{vista === 'stock' && esGrs ? 'Todas' : 'Seleccionar'}</option>
            {granjas.map((g) => (
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
          <Bloque titulo="Uniformes" vacio={resumen.length === 0} cargando={stock.isLoading}>
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
          </Bloque>
          <BloqueItems titulo="Implementos" filas={stock.data?.implementos ?? []} cargando={stock.isLoading} granja={granja} nombreGranja={nombreGranja} />
          <BloqueItems titulo="Insumos" filas={stock.data?.insumos ?? []} cargando={stock.isLoading} granja={granja} nombreGranja={nombreGranja} />
        </>
      ) : null}

      {vista === 'kardex' ? (
        <>
          <div className="bio-card bio-filter-bar">
            <label>
              <span className="bio-label">Categoría</span>
              <select className="bio-select" value={categoriaFiltro} onChange={(e) => setCategoriaFiltro(e.target.value)}>
                <option value="">Todas</option>
                <option value="UNIFORME">Uniformes</option>
                <option value="IMPLEMENTO">Implementos</option>
                <option value="INSUMO">Insumos</option>
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
            {(kardex.data?.items ?? []).length === 0 ? (
              <EmptyState icon="fa-list">{kardex.isLoading ? 'Cargando…' : 'Sin movimientos'}</EmptyState>
            ) : (
              <div className="bio-table-wrap">
                <table className="bio-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      {!granja ? <th>Granja</th> : null}
                      <th>Categoría</th>
                      <th>Operación</th>
                      <th>Frente</th>
                      <th>Ítem</th>
                      <th>SI/E/S</th>
                      <th className="num">Cantidad</th>
                      <th className="num">Stock</th>
                      <th>DNI</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(kardex.data?.items ?? []).map((m) => (
                      <tr key={m.id}>
                        <td>{formatDate(m.fecha)}</td>
                        {!granja ? <td>{nombreGranja(m.granja_cod, m.granja_nombre)}</td> : null}
                        <td>{nombresCat[m.categoria] ?? m.categoria}</td>
                        <td>{nombresOp[m.operacion] ?? m.operacion}</td>
                        <td>{nombresFrente[m.frente] ?? m.frente}</td>
                        <td>{m.item_nombre}</td>
                        <td>{m.tipo}</td>
                        <td className="num">{fmt(m.cantidad)}</td>
                        <td className="num">{fmt(m.stock)}</td>
                        <td>{m.dni || '—'}</td>
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
        <div className="bio-card bio-card-pad" style={{ display: 'grid', gap: '0.9rem' }}>
          <div className="bio-acc-grid">
            {acciones.map((a) => (
              <button key={a.id} type="button" className="bio-acc" aria-pressed={opActual === a.id} onClick={() => { setOp(a.id); setAviso(null) }}>
                <i className={`fa-solid ${a.icon}`} aria-hidden />
                {a.label}
              </button>
            ))}
          </div>
          <div className="bio-filter-bar" style={{ padding: 0, border: 0, background: 'transparent' }}>
            <label>
              <span className="bio-label">Fecha</span>
              <input className="bio-input" type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
            </label>
            {muestraCat ? (
              <label>
                <span className="bio-label">Categoría</span>
                <select className="bio-select" value={catActiva} onChange={(e) => { setCat(e.target.value as CategoriaAlmacen); setItem('') }}>
                  {cats.map((c) => (
                    <option key={c} value={c}>{nombresCat[c]}</option>
                  ))}
                </select>
              </label>
            ) : null}
            {opActual !== 'RELEVO' && catActiva === 'UNIFORME' ? (
              <label>
                <span className="bio-label">Ropa</span>
                <select className="bio-select" value={item || '2'} onChange={(e) => setItem(e.target.value)}>
                  <option value="2">Ropa para el proceso</option>
                  <option value="1">Ropa de tránsito</option>
                </select>
              </label>
            ) : null}
            {opActual !== 'RELEVO' && catActiva !== 'UNIFORME' ? (
              <label style={{ flex: '1 1 16rem' }}>
                <span className="bio-label">{catActiva === 'IMPLEMENTO' ? 'Implemento' : 'Insumo'}</span>
                <select className="bio-select" value={item} onChange={(e) => setItem(e.target.value)}>
                  <option value="">Seleccionar</option>
                  {listaItems.map((i) => (
                    <option key={i.id} value={i.id}>{i.nombre}</option>
                  ))}
                </select>
              </label>
            ) : null}
            {opActual !== 'RELEVO' ? (
              <label>
                <span className="bio-label">Cantidad</span>
                <input className="bio-input" type="number" min="0.01" step="0.01" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
              </label>
            ) : null}
            {opActual === 'ENTREGA' || opActual === 'DEVOLUCION' ? (
              <label style={{ flex: '1 1 16rem' }}>
                <span className="bio-label">Personal</span>
                <select className="bio-select" value={personalId} onChange={(e) => setPersonalId(e.target.value)}>
                  <option value="">Seleccionar</option>
                  {personas.map((p) => (
                    <option key={p.id} value={p.id}>{nombrePersona(p)}</option>
                  ))}
                </select>
              </label>
            ) : null}
            {opActual === 'RELEVO' ? (
              <>
                <label style={{ flex: '1 1 16rem' }}>
                  <span className="bio-label">Saliente</span>
                  <select className="bio-select" value={saleId} onChange={(e) => setSaleId(e.target.value)}>
                    <option value="">Seleccionar</option>
                    {personas.map((p) => (
                      <option key={p.id} value={p.id}>{nombrePersona(p)}</option>
                    ))}
                  </select>
                </label>
                <label style={{ flex: '1 1 16rem' }}>
                  <span className="bio-label">Entrante</span>
                  <select className="bio-select" value={entraId} onChange={(e) => setEntraId(e.target.value)}>
                    <option value="">Seleccionar</option>
                    {personas.map((p) => (
                      <option key={p.id} value={p.id}>{nombrePersona(p)}</option>
                    ))}
                  </select>
                </label>
              </>
            ) : null}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <Button icon="fa-check" onClick={registrar} disabled={guardar.isPending}>Guardar</Button>
            {aviso ? <span className={`bio-badge ${aviso.ok ? 'bio-badge-ok' : 'bio-badge-danger'}`}>{aviso.msg}</span> : null}
          </div>
        </div>
      ) : null}
    </div>
  )
}

function Bloque({ titulo, vacio, cargando, children }: { titulo: string; vacio: boolean; cargando: boolean; children: ReactNode }) {
  return (
    <div className="bio-card" style={{ padding: 0 }}>
      <div className="bio-card-pad" style={{ paddingBottom: 0 }}><strong>{titulo}</strong></div>
      {vacio ? <EmptyState icon="fa-warehouse">{cargando ? 'Cargando…' : 'Sin stock'}</EmptyState> : <div className="bio-table-wrap">{children}</div>}
    </div>
  )
}

function BloqueItems({
  titulo,
  filas,
  cargando,
  granja,
  nombreGranja,
}: {
  titulo: string
  filas: { granja_cod: string; granja_nombre: string; insumo_id: number; nombre: string; unidad: string; stock: number }[]
  cargando: boolean
  granja: string
  nombreGranja: (cod: string, nombre: string) => string
}) {
  return (
    <Bloque titulo={titulo} vacio={filas.length === 0} cargando={cargando}>
      <table className="bio-table">
        <thead>
          <tr>
            {!granja ? <th>Granja</th> : null}
            <th>Ítem</th>
            <th>Unidad</th>
            <th className="num">Stock</th>
          </tr>
        </thead>
        <tbody>
          {filas.map((r) => (
            <tr key={`${r.granja_cod}-${r.insumo_id}`}>
              {!granja ? <td>{nombreGranja(r.granja_cod, r.granja_nombre)}</td> : null}
              <td>{r.nombre}</td>
              <td>{r.unidad}</td>
              <td className="num">{fmt(r.stock)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Bloque>
  )
}
