import { useEffect, useState } from 'react'
import { Modal } from './Modal'

type Props = {
  open: boolean
  src: string | null
  title?: string
  onClose: () => void
}

/** Visor embebido: el iframe carga el PDF directo desde la API (una sola petición). */
export function PdfPreviewModal({ open, src, title = 'Vista previa PDF', onClose }: Props) {
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (open && src) setLoading(true)
  }, [open, src])

  return (
    <Modal open={open} title={title} onClose={onClose} size="xl" icon="fa-file-pdf">
      {src ? (
        <div className="bio-pdf-preview-wrap">
          {loading ? (
            <p className="bio-pdf-preview-status" role="status">
              <i className="fa-solid fa-spinner fa-spin" aria-hidden /> Generando PDF…
            </p>
          ) : null}
          <iframe
            key={src}
            className="bio-pdf-preview"
            src={src}
            title={title}
            onLoad={() => setLoading(false)}
          />
        </div>
      ) : null}
    </Modal>
  )
}
