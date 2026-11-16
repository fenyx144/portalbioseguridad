import { Button } from './Button'
import { Modal } from './Modal'

type Props = {
  message: string | null
  onClose: () => void
}

export function SuccessDialog({ message, onClose }: Props) {
  return (
    <Modal open={message !== null} title="Éxito" onClose={onClose}>
      <p style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.875rem' }}>
        <i className="fa-solid fa-circle-check" style={{ color: 'var(--ix2-ok)', fontSize: '1.25rem' }} aria-hidden />
        {message}
      </p>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '1rem' }}>
        <Button type="button" onClick={onClose} autoFocus>
          Aceptar
        </Button>
      </div>
    </Modal>
  )
}
