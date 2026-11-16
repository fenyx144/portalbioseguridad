import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Input } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { SuccessDialog } from '../../shared/ui/SuccessDialog'
import { useAuth } from '../auth/useAuth'
import { Link, Navigate } from 'react-router-dom'

type Empresa = {
  id: number
  codigo: string
  ruc: string
  razon_social: string
  usuario_cod: string
  estado: string
  personal_activo?: number
  personal_total?: number
}

export function EmpresasPage() {
  const { user, isLoading } = useAuth()
  const [open, setOpen] = useState(false)
  const [codigo, setCodigo] = useState('')
  const [ruc, setRuc] = useState('')
  const [razon, setRazon] = useState('')
  const [rucLookup, setRucLookup] = useState<'idle' | 'loading' | 'err'>('idle')
  const [rucMsg, setRucMsg] = useState('')
  const [exito, setExito] = useState<string | null>(null)
  const ultimoRucConsulta = useRef('')
  const qc = useQueryClient()

  const list = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: Empresa[] }>('/empresas'),
  })

  const crear = useMutation({
    mutationFn: () => api.post<{ codigo: string; razon_social: string }>('/empresas', { codigo, ruc, razon_social: razon }),
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['empresas'] })
      setOpen(false)
      setCodigo('')
      setRuc('')
      setRazon('')
      setRucLookup('idle')
      setRucMsg('')
      ultimoRucConsulta.current = ''
      setExito(`Empresa ${data.codigo} — ${data.razon_social} creada correctamente.`)
    },
  })

  const toggle = useMutation({
    mutationFn: ({ id, estado }: { id: number; estado: string }) => api.patch(`/empresas/${id}`, { estado }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['empresas'] }),
  })

  const cargarRazonPorRuc = useCallback(async (value: string) => {
    const digits = value.replace(/\D/g, '')
    if (digits.length !== 11) {
      return
    }
    if (digits === ultimoRucConsulta.current && rucLookup !== 'err') {
      return
    }
    ultimoRucConsulta.current = digits
    setRucLookup('loading')
    setRucMsg('')
    try {
      const res = await api.get<{ razon_social: string }>(`/empresas/consultar-ruc/${digits}`)
      setRazon(res.razon_social)
      setRucLookup('idle')
    } catch (e) {
      setRucLookup('err')
      setRucMsg(e instanceof Error ? e.message : 'No se pudo consultar el RUC')
    }
  }, [rucLookup])

  useEffect(() => {
    if (ruc.length === 11) {
      void cargarRazonPorRuc(ruc)
    }
    if (ruc.length < 11) {
      ultimoRucConsulta.current = ''
    }
  }, [ruc, cargarRazonPorRuc])

  const codigoOk = /^[A-Z0-9]{6}$/.test(codigo.trim())
  const puedeCrear = codigoOk && ruc.length === 11 && razon.trim().length > 0

  if (!isLoading && user?.actor === 'empresa') {
    return <Navigate to="/" replace />
  }

  return (
    <div>
      <PageHeader
        actions={
          <Button type="button" onClick={() => setOpen(true)}>
            <i className="fa-solid fa-building" aria-hidden /> Nueva empresa
          </Button>
        }
      />

      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>RUC</th>
              <th>Razón social</th>
              <th>Usuario</th>
              <th style={{ textAlign: 'right' }}>Personal</th>
              <th>Estado</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {list.isLoading ? (
              <tr>
                <td colSpan={7} style={{ textAlign: 'center', padding: '2rem', color: 'var(--ix2-muted)' }}>
                  Cargando…
                </td>
              </tr>
            ) : (
              list.data?.items.map((e) => (
                <tr key={e.id}>
                  <td style={{ fontFamily: 'ui-monospace, monospace' }}>{e.codigo}</td>
                  <td>{e.ruc}</td>
                  <td>{e.razon_social}</td>
                  <td>{e.usuario_cod}</td>
                  <td style={{ textAlign: 'right' }}>
                    <Link to={`/personal?empresa_id=${e.id}`} className="bio-badge bio-badge-accent" title="Ver personal">
                      <i className="fa-solid fa-users" aria-hidden /> {Number(e.personal_activo ?? 0)}
                      <span style={{ opacity: 0.7 }}> / {Number(e.personal_total ?? 0)}</span>
                    </Link>
                  </td>
                  <td>
                    <span className={`bio-badge ${e.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-warn'}`}>{e.estado}</span>
                  </td>
                  <td style={{ textAlign: 'right' }}>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        toggle.mutate({ id: e.id, estado: e.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' })
                      }
                    >
                      {e.estado === 'ACTIVO' ? 'Desactivar' : 'Activar'}
                    </Button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <Modal open={open} title="Nueva empresa" onClose={() => setOpen(false)}>
        <div className="bio-field" style={{ marginBottom: '0.75rem' }}>
          <label htmlFor="emp-cod">Código usuario (6 caracteres, login web y app)</label>
          <Input
            id="emp-cod"
            value={codigo}
            onChange={(e) => setCodigo(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6))}
            autoComplete="off"
            spellCheck={false}
            maxLength={6}
          />
          <p className="bio-help" style={{ marginTop: '0.35rem' }}>
            La clave inicial en la app móvil es el RUC (11 dígitos). El dispositivo debe estar registrado.
          </p>
        </div>
        <div className="bio-field" style={{ marginBottom: '0.75rem' }}>
          <label htmlFor="emp-ruc">RUC</label>
          <Input
            id="emp-ruc"
            value={ruc}
            inputMode="numeric"
            onChange={(e) => {
              const v = e.target.value.replace(/\D/g, '').slice(0, 11)
              setRuc(v)
              if (v.length < 11) {
                setRucLookup('idle')
                setRucMsg('')
              }
            }}
            onPaste={(e) => {
              const text = e.clipboardData.getData('text')
              const v = text.replace(/\D/g, '').slice(0, 11)
              if (v.length === 11) {
                e.preventDefault()
                setRuc(v)
              }
            }}
          />
          {rucLookup === 'loading' ? (
            <span style={{ fontSize: '0.75rem', color: 'var(--ix2-muted)' }}>Consultando…</span>
          ) : null}
          {rucLookup === 'err' && rucMsg ? (
            <span style={{ fontSize: '0.75rem', color: '#b91c1c' }}>{rucMsg}</span>
          ) : null}
        </div>
        <div className="bio-field" style={{ marginBottom: '0.75rem' }}>
          <label htmlFor="emp-razon">Razón social</label>
          <Input id="emp-razon" value={razon} onChange={(e) => setRazon(e.target.value)} />
        </div>
        <Button disabled={crear.isPending || !puedeCrear} onClick={() => crear.mutate()}>
          Crear
        </Button>
        {crear.isError && (
          <p style={{ color: '#b91c1c', fontSize: '0.8125rem', marginTop: '0.5rem' }}>{(crear.error as Error).message}</p>
        )}
      </Modal>

      <SuccessDialog message={exito} onClose={() => setExito(null)} />
    </div>
  )
}
