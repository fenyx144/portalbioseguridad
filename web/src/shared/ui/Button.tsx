import type { ButtonHTMLAttributes } from 'react'

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'ghost' | 'danger' | 'soft'
  size?: 'sm' | 'md'
  icon?: string
}

export function Button({ variant = 'primary', size = 'md', className = '', icon, children, ...props }: Props) {
  const variantClass =
    variant === 'primary'
      ? 'bio-btn-primary'
      : variant === 'danger'
        ? 'bio-btn-danger'
        : variant === 'soft'
          ? 'bio-btn-soft'
          : 'bio-btn-ghost'
  const sizeClass = size === 'sm' ? 'bio-btn-sm' : ''
  return (
    <button type="button" className={`bio-btn ${variantClass} ${sizeClass} ${className}`.trim()} {...props}>
      {icon ? <i className={`fa-solid ${icon}`} aria-hidden /> : null}
      {children}
    </button>
  )
}
