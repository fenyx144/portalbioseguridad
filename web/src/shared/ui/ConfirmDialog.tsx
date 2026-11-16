import { Button } from './Button'
import { Modal } from './Modal'

export function ConfirmDialog({
  open,
  title = 'Confirmar',
  message,
  confirmLabel = 'Continuar',
  cancelLabel = 'Cancelar',
  onConfirm,
  onCancel,
}: {
  open: boolean
  title?: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
  onCancel: () => void
}) {
  return (
    <Modal open={open} title={title} onClose={onCancel}>
      <div style={{ display: 'flex', gap: '0.65rem', alignItems: 'flex-start' }}>
        <i
          className="fa-solid fa-triangle-exclamation"
          style={{ color: 'var(--ix2-warn, #d97706)', fontSize: '1.15rem', marginTop: '0.1rem' }}
          aria-hidden
        />
        <p style={{ margin: 0, fontSize: '0.875rem', whiteSpace: 'pre-wrap', flex: 1 }}>{message}</p>
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '1rem' }}>
        <Button type="button" variant="ghost" onClick={onCancel}>
          {cancelLabel}
        </Button>
        <Button type="button" onClick={onConfirm} autoFocus>
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  )
}
