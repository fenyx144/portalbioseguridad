import type { ReactNode } from 'react'

type Props = {
  title?: string
  description?: ReactNode
  actions?: ReactNode
}

export function PageHeader({ title, description, actions }: Props) {
  const soloToolbar = !title && !description && !!actions
  return (
    <div className={soloToolbar ? 'bio-page-head bio-page-head--toolbar' : 'bio-page-head'}>
      {title || description ? (
        <div>
          {title ? <h1>{title}</h1> : null}
          {description ? <p>{description}</p> : null}
        </div>
      ) : null}
      {actions ? <div className="bio-page-head-actions">{actions}</div> : null}
    </div>
  )
}
