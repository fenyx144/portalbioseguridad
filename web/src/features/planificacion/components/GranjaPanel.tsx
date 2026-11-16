import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApiClientError } from '../../../shared/api/client'
import { addDays, formatShortDate, formatWeekCompact } from '../../../shared/lib/dates'
import { openPdf } from '../../../shared/api/client'
import { Button } from '../../../shared/ui/Button'
import { ConfirmDialog } from '../../../shared/ui/ConfirmDialog'
import { Kpi } from '../../../shared/ui/Kpi'
import { NoticeDialog } from '../../../shared/ui/NoticeDialog'
import { PdfPreviewModal } from '../../../shared/ui/PdfPreviewModal'
import { Tabs } from '../../../shared/ui/Tabs'
import { useCopiarSemana, useQuitarAsignaciones } from '../api'
import type { Asignacion, Fila, Grid } from '../types'
import { filtrarFilas, pct, turnosVisibles, type FiltroTurnos, type ResumenGranja } from '../utils'
import { AsignacionRapidaModal, type RapidaPreset } from './AsignacionRapidaModal'
import { ReemplazoModal, type ReemplazoTarget } from './ReemplazoModal'
import { ResumenTrabajadores } from './ResumenTrabajadores'
import { SemanaGrid } from './SemanaGrid'

type Confirm = { title: string; message: string; label: string; run: () => void }
type Notice = { title?: string; message: string; kind: 'info' | 'error' | 'success' }

export function GranjaPanel({
  granja,
  filas,
  dias,
  semana,
  gridCompleto,
  readOnly,
  mostrarEmpresa,
  onBack,
}: {
  granja: ResumenGranja
  filas: Fila[]
  dias: string[]
  semana: string
  gridCompleto?: Grid
  readOnly: boolean
  mostrarEmpresa?: boolean
  onBack: () => void
}) {
  const [params, setParams] = useSearchParams()
  const tab: 'resumen' | 'editar' = !readOnly && params.get('tab') === 'editar' ? 'editar' : 'resumen'
  const setTab = (t: 'resumen' | 'editar') =>
    setParams(
      (p) => {
        const n = new URLSearchParams(p)
        n.set('tab', t)
        return n
      },
      { replace: true },
    )
  const [filtro, setFiltro] = useState<FiltroTurnos>('todos')
  const [rapida, setRapida] = useState<RapidaPreset | null>(null)
  const [reemplazo, setReemplazo] = useState<ReemplazoTarget | null>(null)
  const [confirm, setConfirm] = useState<Confirm | null>(null)
  const [notice, setNotice] = useState<Notice | null>(null)
  const [pdfPreviewUrl, setPdfPreviewUrl] = useState<string | null>(null)
  const quitar = useQuitarAsignaciones()
  const copiar = useCopiarSemana()

  const faltantes = Math.max(0, granja.requeridos - granja.cubiertos)
  const visibles = filtrarFilas(filas, dias, filtro)
  const filtros: [FiltroTurnos, string, string][] = [
    ['todos', 'Todos', ''],
    ['DIA', 'Día', 'fa-sun'],
    ['NOCHE', 'Noche', 'fa-moon'],
    ['incompletos', 'Solo incompletos', 'fa-circle-exclamation'],
  ]
  const contar = (f: FiltroTurnos) => filas.reduce((n, fila) => n + turnosVisibles(fila, dias, f).length, 0)
  const err = (e: unknown, fallback: string) => setNotice({ kind: 'error', message: e instanceof ApiClientError ? e.message : fallback })

  async function quitarIds(ids: number[]) {
    try {
      await quitar.mutateAsync(ids)
    } catch (e) {
      err(e, 'No se pudo quitar')
    }
  }

  const abrirReemplazo = (fila: Fila, turno: string, asig: Asignacion) => {
    const fechas = dias.filter((d) => fila.celdas[d]?.[turno]?.asignaciones.some((a) => a.personal_id === asig.personal_id))
    setReemplazo({ asig, puntoNombre: fila.punto.nombre, turno, fechasTitular: fechas })
  }

  const copiarSemana = () =>
    setConfirm({
      title: 'Copiar semana anterior',
      message: `${formatWeekCompact(addDays(semana, -7))} → ${formatWeekCompact(semana)}\n${granja.nombre}`,
      label: 'Copiar',
      run: async () => {
        try {
          const r = await copiar.mutateAsync({ granja_cod: granja.codigo, semana_origen: addDays(semana, -7), semana_destino: semana })
          const omit = r.omitidas
            ? `\nOmitidas: ${r.omitidas}\n` +
              r.detalle_omitidas.slice(0, 15).map((o) => `• ${o.personal} · ${formatShortDate(o.fecha)} · ${o.message}`).join('\n')
            : ''
          setNotice({ kind: r.copiadas ? 'success' : 'info', title: 'Copia de semana', message: `Copiadas: ${r.copiadas}${omit}` })
        } catch (e) {
          err(e, 'No se pudo copiar la semana')
        }
      },
    })

  return (
    <div style={{ display: 'grid', gap: '0.9rem' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem', flexWrap: 'wrap' }}>
        <Button variant="ghost" size="sm" icon="fa-arrow-left" onClick={onBack}>
          Granjas
        </Button>
        <h2 style={{ margin: 0, fontSize: '1.05rem', flex: 1, minWidth: 0 }}>
          <i className="fa-solid fa-wheat-awn" style={{ color: 'var(--ix2-accent)' }} aria-hidden /> {granja.nombre}{' '}
          <span className="bio-badge bio-badge-muted">{granja.codigo}</span>
        </h2>
        <Button
          variant="soft"
          size="sm"
          icon="fa-file-pdf"
          onClick={() => {
            try {
              openPdf('/reportes/planificacion-semana.pdf', { fecha_inicio: semana, granja_cod: granja.codigo }, { onInlinePreview: setPdfPreviewUrl })
            } catch (e) {
              setNotice({ kind: 'error', message: e instanceof Error ? e.message : 'No se pudo generar el PDF' })
            }
          }}
        >
          PDF semana
        </Button>
        {!readOnly && tab === 'editar' ? (
          <>
            <Button variant="soft" size="sm" icon="fa-clone" disabled={copiar.isPending} onClick={copiarSemana}>
              Copiar semana anterior
            </Button>
            <Button size="sm" icon="fa-bolt" onClick={() => setRapida({})}>
              Asignación rápida
            </Button>
          </>
        ) : null}
      </div>

      <div className="bio-kpis">
        <Kpi icon="fa-gauge-high" label="Cobertura" value={`${pct(granja.requeridos, granja.cubiertos)}%`} tone={granja.estado === 'completo' ? 'ok' : granja.estado === 'parcial' ? 'warn' : 'danger'} />
        <Kpi icon="fa-location-dot" label="Puntos" value={granja.puntos} tone="info" />
        <Kpi icon="fa-users" label="Personas" value={granja.personas} />
        <Kpi icon="fa-user-clock" label="Turnos por cubrir" value={faltantes} tone={faltantes ? 'danger' : 'ok'} />
      </div>

      {!readOnly ? (
        <Tabs
          items={[
            { value: 'resumen', label: 'Resumen', icon: 'fa-list-check' },
            { value: 'editar', label: 'Editar semana', icon: 'fa-table-cells' },
          ]}
          value={tab}
          onChange={setTab}
        />
      ) : null}

      <div className="bio-filter-chips" role="group">
        {filtros.map(([v, label, icon]) => (
          <button key={v} type="button" className="bio-filter-chip" aria-pressed={filtro === v} onClick={() => setFiltro(v)}>
            {icon ? <i className={`fa-solid ${icon}`} aria-hidden /> : null}
            {label}
            <span>{contar(v)}</span>
          </button>
        ))}
      </div>

      {tab === 'resumen' ? (
        <ResumenTrabajadores
          filas={filas}
          dias={dias}
          filtro={filtro}
          readOnly
          mostrarEmpresa={mostrarEmpresa}
          onFalta={readOnly ? undefined : () => setTab('editar')}
        />
      ) : (
        <SemanaGrid
          filas={visibles}
          dias={dias}
          readOnly={readOnly}
          mostrarEmpresa={mostrarEmpresa}
          actions={
            readOnly
              ? undefined
              : {
                  onAgregar: (fila, turno, fecha) => setRapida({ puntoId: fila.punto.id, turno, fecha }),
                  onQuitar: (a) => void quitarIds([a.id]),
                  onReemplazar: abrirReemplazo,
                }
          }
        />
      )}

      {!readOnly ? (
        <>
          <AsignacionRapidaModal
            open={rapida !== null}
            onClose={() => setRapida(null)}
            filas={filas}
            dias={dias}
            gridCompleto={gridCompleto}
            preset={rapida ?? undefined}
          />
          <ReemplazoModal
            target={reemplazo}
            onClose={() => setReemplazo(null)}
            onDone={(message) => {
              setReemplazo(null)
              setNotice({ kind: 'success', title: 'Reemplazo', message })
            }}
          />
        </>
      ) : null}

      <ConfirmDialog
        open={confirm !== null}
        title={confirm?.title}
        message={confirm?.message ?? ''}
        confirmLabel={confirm?.label}
        onCancel={() => setConfirm(null)}
        onConfirm={() => {
          const c = confirm
          setConfirm(null)
          c?.run()
        }}
      />
      <NoticeDialog
        open={notice !== null}
        title={notice?.title}
        kind={notice?.kind}
        message={notice?.message ?? ''}
        onClose={() => setNotice(null)}
      />
      <PdfPreviewModal open={pdfPreviewUrl !== null} src={pdfPreviewUrl} title="Planificación semana" onClose={() => setPdfPreviewUrl(null)} />
    </div>
  )
}
