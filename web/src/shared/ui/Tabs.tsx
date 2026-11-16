import type { ReactNode } from 'react'

export type TabItem<T extends string> = { value: T; label: ReactNode; icon?: string; count?: number }

export function Tabs<T extends string>({
  items,
  value,
  onChange,
}: {
  items: TabItem<T>[]
  value: T
  onChange: (v: T) => void
}) {
  return (
    <div className="bio-tabs" role="tablist">
      {items.map((it) => (
        <button
          key={it.value}
          type="button"
          role="tab"
          className="bio-tab"
          aria-selected={value === it.value}
          onClick={() => onChange(it.value)}
        >
          {it.icon ? <i className={`fa-solid ${it.icon}`} aria-hidden /> : null}
          {it.label}
          {it.count !== undefined ? <span className="bio-tab-count">{it.count}</span> : null}
        </button>
      ))}
    </div>
  )
}
