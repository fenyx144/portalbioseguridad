import { useState } from 'react'
import { openPdf } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { NoticeDialog } from '../../shared/ui/NoticeDialog'
import { PdfPreviewModal } from '../../shared/ui/PdfPreviewModal'

type Props = {
  pdfPath: string
  pdfParams?: Record<string, string | number | undefined>
  downloadName: string
  onCsv?: () => void
  csvDisabled?: boolean
  /** En cabecera junto a enlaces; en barra debajo de filtros */
  layout?: 'bar' | 'header'
}

export function ReporteExportBar({
  pdfPath,
  pdfParams,
  downloadName,
  onCsv,
  csvDisabled,
  layout = 'bar',
}: Props) {
  const [pdfError, setPdfError] = useState<string | null>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  const descargarPdf = () => {
    try {
      openPdf(pdfPath, pdfParams, { onInlinePreview: setPreviewUrl })
    } catch (e) {
      setPdfError(e instanceof Error ? e.message : 'No se pudo generar el PDF')
    }
  }

  const buttons = (
    <>
      <Button variant="primary" size="sm" onClick={descargarPdf}>
        <i className="fa-solid fa-file-pdf" aria-hidden /> Ver PDF
      </Button>
      {onCsv ? (
        <Button variant="ghost" size="sm" disabled={csvDisabled} onClick={onCsv}>
          <i className="fa-solid fa-file-csv" aria-hidden /> CSV
        </Button>
      ) : null}
    </>
  )

  const dialogs = (
    <>
      <PdfPreviewModal
        open={previewUrl !== null}
        src={previewUrl}
        title={downloadName.replace(/\.pdf$/i, '')}
        onClose={() => setPreviewUrl(null)}
      />
      <NoticeDialog open={pdfError !== null} kind="error" message={pdfError ?? ''} onClose={() => setPdfError(null)} />
    </>
  )

  if (layout === 'header') {
    return (
      <>
        {buttons}
        {dialogs}
      </>
    )
  }

  return (
    <>
      <div className="bio-toolbar" style={{ marginBottom: '0.75rem', justifyContent: 'flex-end' }}>
        {buttons}
      </div>
      {dialogs}
    </>
  )
}
