import type { ReactNode } from 'react'

export function Kpi({
  icon,
  label,
  value,
  tone,
}: {
  icon: string
  label: ReactNode
  value: ReactNode
  tone?: 'ok' | 'warn' | 'danger' | 'info'
}) {
  return (
    <div className="bio-kpi" data-tone={tone}>
      <span className="bio-kpi-ic">
        <i className={`fa-solid ${icon}`} aria-hidden />
      </span>
      <div style={{ minWidth: 0 }}>
        <div className="bio-kpi-val">{value}</div>
        <div className="bio-kpi-lbl">{label}</div>
      </div>
    </div>
  )
}

export function EmptyState({ icon = 'fa-seedling', children }: { icon?: string; children: ReactNode }) {
  return (
    <div className="bio-empty">
      <i className={`fa-solid ${icon}`} aria-hidden />
      {children}
    </div>
  )
}

export function PageHero({
  icon,
  title,
  subtitle,
  actions,
}: {
  icon: string
  title: ReactNode
  subtitle?: ReactNode
  actions?: ReactNode
}) {
  return (
    <div className="bio-hero">
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', minWidth: 0 }}>
        <span className="bio-hero-icon">
          <i className={`fa-solid ${icon}`} aria-hidden />
        </span>
        <div style={{ minWidth: 0 }}>
          <h1>{title}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
      </div>
      {actions ? <div className="bio-page-head-actions">{actions}</div> : null}
    </div>
  )
}
