import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Input } from '../../shared/ui/Input'
import { PageHeader } from '../../shared/ui/PageHeader'
import { personalTabs, ProcessTabs } from '../layout/processNav'

type Puesto = { codigo: string; nombre: string; turno: string }
type Celda = { id: number; cantidad: number }
type Fila = { zona: string; granja_nombre: string; orden: number; celdas: Record<string, Celda> }

const DIA = ['SUP_DIA', 'TRANQ_DIA', 'GAR_DIA', 'PER_DIA']
const NOCHE = ['SUP_NOCHE', 'TRANQ_NOCHE', 'GAR_NOCHE', 'PER_NOCHE']

export function RequerimientoPage() {
  const qc = useQueryClient()
  const [zonaF, setZonaF] = useState('')
  const [q, setQ] = useState('')
  const [draft, setDraft] = useState<Record<number, string>>({})
  const [nuevaZona, setNuevaZona] = useState('')
  const [nuevaGranja, setNuevaGranja] = useState('')
  const data = useQuery({
    queryKey: ['requerimientos'],
    queryFn: () => api.get<{ puestos: Puesto[]; filas: Fila[] }>('/requerimientos'),
  })
  const guardar = useMutation({
    mutationFn: (body: { items?: { id: number; cantidad: number }[]; zona?: string; granja_nombre?: string }) =>
      api.post('/requerimientos', body),
    onSuccess: () => {
      setDraft({})
      setNuevaZona('')
      setNuevaGranja('')
      void qc.invalidateQueries({ queryKey: ['requerimientos'] })
    },
  })
  const puestos = data.data?.puestos ?? []
  const zonas = useMemo(() => [...new Set((data.data?.filas ?? []).map((f) => f.zona))], [data.data])
  const filas = (data.data?.filas ?? []).filter((f) => {
    if (zonaF && f.zona !== zonaF) return false
    if (q && !f.granja_nombre.toLowerCase().includes(q.toLowerCase())) return false
    return true
  })
  const valor = (c?: Celda) => {
    if (!c) return ''
    if (draft[c.id] !== undefined) return draft[c.id]
    return c.cantidad ? String(c.cantidad) : ''
  }
  const num = (c?: Celda) => {
    const v = valor(c)
    const n = Number(v)
    return Number.isFinite(n) ? n : 0
  }
  const suma = (f: Fila, cods: string[]) => cods.reduce((a, c) => a + num(f.celdas[c]), 0)
  const totales = puestos.map((p) => filas.reduce((a, f) => a + num(f.celdas[p.codigo]), 0))

  return (
    <div>
      <PageHeader
        title="Requerimiento de personal"
        actions={
          <Button
            icon="fa-floppy-disk"
            disabled={guardar.isPending || Object.keys(draft).length === 0}
            onClick={() =>
              guardar.mutate({
                items: Object.entries(draft).map(([id, cantidad]) => ({ id: Number(id), cantidad: Number(cantidad) || 0 })),
              })
            }
          >
            Guardar
          </Button>
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />
      <div className="bio-toolbar">
        <Input placeholder="Granja…" value={q} onChange={(e) => setQ(e.target.value)} style={{ maxWidth: '14rem' }} />
        <select className="bio-select" value={zonaF} onChange={(e) => setZonaF(e.target.value)} style={{ maxWidth: '12rem' }}>
          <option value="">Zona</option>
          {zonas.map((z) => (
            <option key={z} value={z}>
              {z}
            </option>
          ))}
        </select>
        <Input placeholder="Zona" value={nuevaZona} onChange={(e) => setNuevaZona(e.target.value)} style={{ maxWidth: '8rem' }} />
        <Input placeholder="Nueva granja" value={nuevaGranja} onChange={(e) => setNuevaGranja(e.target.value)} style={{ maxWidth: '12rem' }} />
        <Button
          variant="ghost"
          size="sm"
          disabled={!nuevaGranja.trim() || guardar.isPending}
          onClick={() => guardar.mutate({ zona: nuevaZona.trim(), granja_nombre: nuevaGranja.trim() })}
        >
          Agregar
        </Button>
      </div>
      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Zona</th>
              <th>Granja</th>
              {puestos.map((p) => (
                <th key={p.codigo} className="num">
                  {p.nombre}
                </th>
              ))}
              <th className="num">Total día</th>
              <th className="num">Total noche</th>
              <th className="num">Total</th>
            </tr>
          </thead>
          <tbody>
            {filas.map((f) => {
              const td = suma(f, DIA)
              const tn = suma(f, NOCHE)
              const tt = puestos.reduce((a, p) => a + num(f.celdas[p.codigo]), 0)
              return (
                <tr key={`${f.zona}-${f.granja_nombre}`}>
                  <td>{f.zona}</td>
                  <td>{f.granja_nombre}</td>
                  {puestos.map((p) => {
                    const c = f.celdas[p.codigo]
                    return (
                      <td key={p.codigo} className="num">
                        <input
                          className="bio-input"
                          inputMode="decimal"
                          style={{ width: '4.2rem', textAlign: 'right' }}
                          value={valor(c)}
                          disabled={!c}
                          onChange={(e) => c && setDraft((d) => ({ ...d, [c.id]: e.target.value }))}
                        />
                      </td>
                    )
                  })}
                  <td className="num">{td || ''}</td>
                  <td className="num">{tn || ''}</td>
                  <td className="num">{tt || ''}</td>
                </tr>
              )
            })}
          </tbody>
          <tfoot>
            <tr>
              <th colSpan={2}>Total</th>
              {totales.map((n, i) => (
                <th key={puestos[i]?.codigo ?? i} className="num">
                  {Math.round(n * 100) / 100}
                </th>
              ))}
              <th className="num">{Math.round(filas.reduce((a, f) => a + suma(f, DIA), 0) * 100) / 100}</th>
              <th className="num">{Math.round(filas.reduce((a, f) => a + suma(f, NOCHE), 0) * 100) / 100}</th>
              <th className="num">{Math.round(totales.reduce((a, n) => a + n, 0) * 100) / 100}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  )
}
