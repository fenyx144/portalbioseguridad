import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api } from '../../shared/api/client'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { Modal } from '../../shared/ui/Modal'
import { PageHeader } from '../../shared/ui/PageHeader'
import { SuccessDialog } from '../../shared/ui/SuccessDialog'
import { PersonalForm, type PersonalSubmitPayload } from './PersonalForm'
import type { Personal } from './types'
import { etiquetaEmpresaPersonal } from './types'
import { useAuth } from '../auth/useAuth'
import { personalTabs, ProcessTabs } from '../layout/processNav'

export function PersonalListPage() {
  const { user } = useAuth()
  const isGrs = (user?.actor ?? 'grs') === 'grs'
  const [q, setQ] = useState('')
  const [tipoEmpresa, setTipoEmpresa] = useState('')
  const [searchParams] = useSearchParams()
  const [empresaId, setEmpresaId] = useState(() => searchParams.get('empresa_id') ?? '')
  const [estado, setEstado] = useState('ACTIVO')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [exito, setExito] = useState<string | null>(null)
  const qc = useQueryClient()

  const granjas = useQuery({
    queryKey: ['granjas'],
    queryFn: () => api.get<{ items: { codigo: string; nombre: string }[] }>('/granjas'),
  })

  const empresas = useQuery({
    queryKey: ['empresas'],
    queryFn: () => api.get<{ items: { id: number; codigo: string; razon_social: string; estado: string }[] }>('/empresas'),
    enabled: isGrs,
  })

  const list = useQuery({
    queryKey: ['personal', q, tipoEmpresa, empresaId, estado, page],
    queryFn: () =>
      api.get<{ items: Personal[]; total: number; page: number; per_page: number }>('/personal', {
        q,
        empresa: tipoEmpresa,
        empresa_id: empresaId,
        estado,
        page,
      }),
  })

  const crear = useMutation({
    mutationFn: async ({ body, foto }: { body: PersonalSubmitPayload; foto?: File | null }) => {
      const r = await api.post<{ id: number; usuario_cod?: string; usuario_message?: string }>('/personal', body)
      if (foto && r?.id) {
        const fd = new FormData()
        fd.append('foto', foto)
        try {
          await api.post(`/personal/${r.id}/foto`, fd)
        } catch (e) {
          return `Personal registrado. No se pudo subir la foto: ${(e as Error).message}`
        }
      }
      if (r.usuario_cod) return `Personal registrado. Usuario ${r.usuario_cod}`
      if (r.usuario_message) return `Personal registrado. ${r.usuario_message}`
      return 'Personal registrado correctamente'
    },
    onSuccess: (msg) => {
      qc.invalidateQueries({ queryKey: ['personal'] })
      setOpen(false)
      setExito(msg)
    },
  })

  const total = list.data?.total ?? 0
  const perPage = list.data?.per_page ?? 20
  const pages = Math.max(1, Math.ceil(total / perPage))

  return (
    <div>
      <PageHeader
        actions={
          <Button type="button" onClick={() => setOpen(true)}>
            <i className="fa-solid fa-user-plus" aria-hidden /> Nuevo trabajador
          </Button>
        }
      />
      <ProcessTabs items={personalTabs} label="Personal" />

      <div className="bio-toolbar">
        <Input
          placeholder="Buscar por nombre o DNI…"
          value={q}
          onChange={(e) => {
            setQ(e.target.value)
            setPage(1)
          }}
          style={{ maxWidth: '16rem' }}
        />
        {isGrs ? (
          <>
            <Select
              value={tipoEmpresa}
              onChange={(e) => {
                setTipoEmpresa(e.target.value)
                setPage(1)
              }}
              style={{ maxWidth: '8.5rem' }}
            >
              <option value="">Todos</option>
              <option value="TERCERO">Tercero</option>
            </Select>
            <Select
              value={empresaId}
              onChange={(e) => {
                setEmpresaId(e.target.value)
                setPage(1)
              }}
              style={{ maxWidth: '14rem' }}
            >
              <option value="">Contratista</option>
              {(empresas.data?.items ?? [])
                .filter((e) => e.estado === 'ACTIVO')
                .map((e) => (
                  <option key={e.id} value={String(e.id)}>
                    {e.codigo} — {e.razon_social}
                  </option>
                ))}
            </Select>
          </>
        ) : null}
        <Select
          value={estado}
          onChange={(e) => {
            setEstado(e.target.value)
            setPage(1)
          }}
          style={{ maxWidth: '9rem' }}
        >
          <option value="">Estado</option>
          <option value="ACTIVO">Activo</option>
          <option value="CESADO">Cesado</option>
        </Select>
        <span style={{ marginLeft: 'auto', fontSize: '0.8125rem', color: 'var(--ix2-muted)' }}>
          {total} {total === 1 ? 'registro' : 'registros'}
        </span>
      </div>

      <div className="bio-card bio-table-wrap">
        <table className="bio-table">
          <thead>
            <tr>
              <th style={{ width: '3rem' }}>#</th>
              <th>DNI</th>
              <th>Nombre</th>
              <th>Cargo</th>
              <th>Usuario</th>
              <th>{isGrs ? 'Contratista' : 'Empresa'}</th>
              <th>Estado</th>
              <th>Granja base</th>
            </tr>
          </thead>
          <tbody>
            {list.isLoading ? (
              <tr>
                <td colSpan={8} style={{ textAlign: 'center', color: 'var(--ix2-muted)', padding: '2rem' }}>
                  Cargando…
                </td>
              </tr>
            ) : (
              list.data?.items.map((p, i) => (
                <tr key={p.id}>
                  <td style={{ color: 'var(--ix2-muted)', fontSize: '0.75rem' }}>{(page - 1) * perPage + i + 1}</td>
                  <td style={{ fontFamily: 'ui-monospace, monospace', fontSize: '0.75rem' }}>{p.dni}</td>
                  <td>
                    <Link className="bio-link" to={`/personal/${p.id}`}>
                      {p.apellidos}, {p.nombres}
                    </Link>
                  </td>
                  <td>{p.cargo}</td>
                  <td style={{ fontFamily: 'ui-monospace, monospace', fontSize: '0.75rem' }}>{p.usuario_cod || '—'}</td>
                  <td>{etiquetaEmpresaPersonal(p)}</td>
                  <td>
                    <span className={`bio-badge ${p.estado === 'ACTIVO' ? 'bio-badge-ok' : 'bio-badge-warn'}`}>
                      {p.estado}
                    </span>
                  </td>
                  <td>{p.granja_base_cod ? ((g) => (g ? `${g.codigo} - ${g.nombre}` : p.granja_base_cod))(granjas.data?.items.find((x) => x.codigo === p.granja_base_cod)) : '—'}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <div className="bio-toolbar" style={{ marginTop: '0.75rem', justifyContent: 'center' }}>
        <Button variant="ghost" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
          <i className="fa-solid fa-chevron-left" aria-hidden /> Anterior
        </Button>
        <span style={{ fontSize: '0.8125rem', color: 'var(--ix2-muted)' }}>
          Página {page} de {pages}
        </span>
        <Button variant="ghost" size="sm" disabled={page >= pages} onClick={() => setPage((p) => p + 1)}>
          Siguiente <i className="fa-solid fa-chevron-right" aria-hidden />
        </Button>
      </div>

      <Modal open={open} title="Nuevo trabajador" onClose={() => setOpen(false)} wide>
        <PersonalForm
          granjas={granjas.data?.items ?? []}
          empresas={empresas.data?.items}
          permitirContratista={isGrs}
          onSubmit={(body, foto) => crear.mutate({ body, foto })}
          loading={crear.isPending}
          conFoto
        />
        {crear.isError && <p style={{ marginTop: '0.5rem', fontSize: '0.8125rem', color: '#b91c1c' }}>{(crear.error as Error).message}</p>}
      </Modal>
      <SuccessDialog message={exito} onClose={() => setExito(null)} />
    </div>
  )
}
