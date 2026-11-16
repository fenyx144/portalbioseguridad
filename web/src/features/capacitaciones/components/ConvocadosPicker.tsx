import { useMemo, useState } from 'react'
import { Tabs } from '../../../shared/ui/Tabs'
import { useCargos, usePersonalActivoCap } from '../api'

export type Convocados = { cargos: string[]; personal_ids: number[] }

export function ConvocadosPicker({
  value,
  onChange,
  excluir = [],
}: {
  value: Convocados
  onChange: (v: Convocados) => void
  excluir?: number[]
}) {
  const [modo, setModo] = useState<'cargo' | 'nombre'>('cargo')
  const [q, setQ] = useState('')
  const cargos = useCargos()
  const personal = usePersonalActivoCap()
  const items = useMemo(() => (personal.data?.items ?? []).filter((p) => !excluir.includes(p.id)), [personal.data, excluir])
  const filtrados = useMemo(() => {
    const t = q.trim().toLowerCase()
    return t ? items.filter((p) => `${p.apellidos} ${p.nombres} ${p.dni} ${p.cargo}`.toLowerCase().includes(t)) : items
  }, [items, q])

  const porCargo = new Set(items.filter((p) => value.cargos.includes(p.cargo)).map((p) => p.id))
  const total = new Set([...porCargo, ...value.personal_ids]).size

  const toggleCargo = (c: string) =>
    onChange({ ...value, cargos: value.cargos.includes(c) ? value.cargos.filter((x) => x !== c) : [...value.cargos, c] })
  const togglePersona = (id: number) =>
    onChange({
      ...value,
      personal_ids: value.personal_ids.includes(id) ? value.personal_ids.filter((x) => x !== id) : [...value.personal_ids, id],
    })

  return (
    <div style={{ display: 'grid', gap: '0.6rem' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem', flexWrap: 'wrap' }}>
        <Tabs
          items={[
            { value: 'cargo', label: 'Por cargo', icon: 'fa-id-badge', count: value.cargos.length },
            { value: 'nombre', label: 'Por nombre', icon: 'fa-user', count: value.personal_ids.length },
          ]}
          value={modo}
          onChange={setModo}
        />
        <span className="bio-badge bio-badge-accent" style={{ marginLeft: 'auto' }}>
          <i className="fa-solid fa-users" aria-hidden /> {total} convocado(s)
        </span>
      </div>
      {modo === 'cargo' ? (
        <div className="bio-option-grid">
          {(cargos.data?.items ?? []).map((c) => (
            <button key={c.cargo} type="button" className="bio-option" aria-pressed={value.cargos.includes(c.cargo)} onClick={() => toggleCargo(c.cargo)}>
              <i className="fa-solid fa-id-badge" style={{ color: 'var(--ix2-accent)' }} aria-hidden />
              <span style={{ flex: 1 }}>{c.cargo}</span>
              <span className="bio-badge bio-badge-muted">{c.total}</span>
            </button>
          ))}
          {cargos.data && cargos.data.items.length === 0 ? <span className="bio-help">Sin cargos</span> : null}
        </div>
      ) : (
        <>
          <input className="bio-input" placeholder="Buscar…" value={q} onChange={(e) => setQ(e.target.value)} />
          <div className="bio-people-list">
            {filtrados.map((p) => {
              const viaCargo = porCargo.has(p.id)
              const on = viaCargo || value.personal_ids.includes(p.id)
              return (
                <button
                  key={p.id}
                  type="button"
                  className="bio-people-item"
                  aria-pressed={on}
                  disabled={viaCargo}
                  onClick={() => togglePersona(p.id)}
                >
                  <input type="checkbox" readOnly checked={on} tabIndex={-1} />
                  <span style={{ flex: 1, minWidth: 0 }}>
                    <strong>
                      {p.apellidos}, {p.nombres}
                    </strong>
                    <span style={{ display: 'block', fontSize: '0.68rem', color: 'var(--ix2-muted)' }}>
                      {p.cargo || 'Sin cargo'} · DNI {p.dni}
                    </span>
                  </span>
                  {viaCargo ? <span className="bio-badge bio-badge-info">por cargo</span> : null}
                </button>
              )
            })}
            {filtrados.length === 0 ? <div className="bio-people-item">Sin resultados</div> : null}
          </div>
        </>
      )}
    </div>
  )
}
