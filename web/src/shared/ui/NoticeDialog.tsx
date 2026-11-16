import { Button } from './Button'
import { Modal } from './Modal'

export function NoticeDialog({
  open,
  title = 'Aviso',
  message,
  kind = 'info',
  onClose,
}: {
  open: boolean
  title?: string
  message: string
  kind?: 'info' | 'error' | 'success'
  onClose: () => void
}) {
  const icon =
    kind === 'error' ? 'fa-circle-exclamation' : kind === 'success' ? 'fa-circle-check' : 'fa-circle-info'
  const iconColor =
    kind === 'error' ? 'var(--ix2-danger, #dc2626)' : kind === 'success' ? 'var(--ix2-ok)' : 'var(--ix2-muted)'

  return (
    <Modal open={open} title={title} onClose={onClose}>
      <div style={{ display: 'flex', gap: '0.65rem', alignItems: 'flex-start' }}>
        <i className={`fa-solid ${icon}`} style={{ color: iconColor, fontSize: '1.15rem', marginTop: '0.1rem' }} aria-hidden />
        <p style={{ margin: 0, fontSize: '0.875rem', whiteSpace: 'pre-wrap', flex: 1 }}>{message}</p>
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '1rem' }}>
        <Button type="button" onClick={onClose} autoFocus>
          Aceptar
        </Button>
      </div>
    </Modal>
  )
}
