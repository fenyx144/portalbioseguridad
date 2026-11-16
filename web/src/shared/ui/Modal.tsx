import { useEffect, type ReactNode } from 'react'

type Props = {
  open: boolean
  title: ReactNode
  onClose: () => void
  children: ReactNode
  /** Compatibilidad: wide = tamaño "lg" */
  wide?: boolean
  size?: 'md' | 'lg' | 'xl'
  icon?: string
  footer?: ReactNode
}

const widths = { md: '32rem', lg: '48rem', xl: '64rem' }

export function Modal({ open, title, onClose, children, wide, size, icon, footer }: Props) {
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open, onClose])

  if (!open) return null
  const w = widths[size ?? (wide ? 'lg' : 'md')]
  return (
    <div
      className="bio-modal-backdrop"
      role="dialog"
      aria-modal="true"
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose()
      }}
    >
      <div className="bio-modal" style={{ maxWidth: w }}>
        <div className="bio-modal-head">
          {icon ? <i className={`fa-solid ${icon}`} style={{ color: 'var(--ix2-accent)' }} aria-hidden /> : null}
          <h2>{title}</h2>
          <button type="button" className="bio-icon-action" onClick={onClose} aria-label="Cerrar">
            <i className="fa-solid fa-xmark" aria-hidden />
          </button>
        </div>
        <div className="bio-modal-body">{children}</div>
        {footer ? <div className="bio-modal-foot">{footer}</div> : null}
      </div>
    </div>
  )
}
