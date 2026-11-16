import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import type { Personal } from './types'
import { api } from '../../shared/api/client'
import { todayIso } from '../../shared/lib/dates'
import { Button } from '../../shared/ui/Button'
import { Input, Select } from '../../shared/ui/Input'
import { PersonalAvatar } from '../../shared/ui/PersonalAvatar'

const schema = z.object({
  dni: z.string().min(8).max(15),
  nombres: z.string().min(1),
  apellidos: z.string().min(1),
  cargo: z.string().min(1),
  empresa: z.enum(['GRS', 'TERCERO']),
  empresa_id: z.string().optional(),
  fecha_contratacion: z.string().min(1),
  estado: z.enum(['ACTIVO', 'CESADO']),
  granja_base_cod: z.string().optional(),
})

export type PersonalFormValues = z.infer<typeof schema>

export type PersonalSubmitPayload = Omit<PersonalFormValues, 'empresa_id'> & { empresa_id?: number | null }

type DniInfo = {
  dni: string
  nombres: string
  apellidos: string
  existente: { id: number; nombres: string; apellidos: string; estado: string } | null
}

type EmpresaOpt = { id: number; codigo: string; razon_social: string; estado: string }

type Props = {
  initial?: Personal
  granjas: { codigo: string; nombre: string }[]
  empresas?: EmpresaOpt[]
  permitirContratista?: boolean
  onSubmit: (v: PersonalSubmitPayload, foto?: File | null) => void
  loading?: boolean
  conFoto?: boolean
}

export function PersonalForm({ initial, granjas, empresas = [], permitirContratista = false, onSubmit, loading, conFoto = false }: Props) {
  const [foto, setFoto] = useState<File | null>(null)
  const [fotoUrl, setFotoUrl] = useState<string | null>(null)
  const [fotoErr, setFotoErr] = useState('')
  useEffect(() => {
    if (!foto) {
      setFotoUrl(null)
      return
    }
    const url = URL.createObjectURL(foto)
    setFotoUrl(url)
    return () => URL.revokeObjectURL(url)
  }, [foto])
  const elegirFoto = (file?: File) => {
    setFotoErr('')
    if (!file) return
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setFotoErr('Formato no permitido (JPG, PNG o WEBP)')
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      setFotoErr('Máximo 2 MB')
      return
    }
    setFoto(file)
  }
  const form = useForm<PersonalFormValues>({
    resolver: zodResolver(schema),
    defaultValues: initial
      ? {
          dni: initial.dni,
          nombres: initial.nombres,
          apellidos: initial.apellidos,
          cargo: initial.cargo,
          empresa: initial.empresa,
          empresa_id: initial.empresa_id ? String(initial.empresa_id) : '',
          fecha_contratacion: initial.fecha_contratacion,
          estado: initial.estado,
          granja_base_cod: initial.granja_base_cod ?? '',
        }
      : {
          dni: '',
          nombres: '',
          apellidos: '',
          cargo: '',
          empresa: 'TERCERO',
          empresa_id: '',
          fecha_contratacion: todayIso(),
          estado: 'ACTIVO',
          granja_base_cod: '',
        },
  })
  const [buscando, setBuscando] = useState(false)
  const [dniMsg, setDniMsg] = useState('')
  const [dniErr, setDniErr] = useState(false)
  const ultimo = useRef<{ dni: string; nombres: string; apellidos: string }>({ dni: initial?.dni ?? '', nombres: '', apellidos: '' })

  const mostrarContratista = permitirContratista

  const buscarDni = async (valor: string) => {
    const dni = valor.replace(/\D/g, '')
    if (dni.length !== 8 || buscando) return
    ultimo.current.dni = dni
    setBuscando(true)
    setDniMsg('')
    setDniErr(false)
    try {
      const r = await api.get<DniInfo>(`/personal/consultar-dni/${dni}`)
      if (form.getValues('dni').replace(/\D/g, '') !== dni) return
      const n = form.getValues('nombres')
      const a = form.getValues('apellidos')
      if (n.trim() === '' || n === ultimo.current.nombres) form.setValue('nombres', r.nombres, { shouldValidate: true })
      if (a.trim() === '' || a === ultimo.current.apellidos) form.setValue('apellidos', r.apellidos, { shouldValidate: true })
      ultimo.current.nombres = r.nombres
      ultimo.current.apellidos = r.apellidos
      if (r.existente && r.existente.id !== initial?.id) {
        setDniErr(true)
        setDniMsg(`Ya registrado: ${r.existente.apellidos}, ${r.existente.nombres} (${r.existente.estado})`)
      }
    } catch (e) {
      setDniErr(true)
      setDniMsg((e as Error).message || 'DNI no encontrado.')
    } finally {
      setBuscando(false)
    }
  }

  const dniReg = form.register('dni')

  const handleSubmit = (v: PersonalFormValues) => {
    const empresa = permitirContratista ? 'TERCERO' : v.empresa
    if (permitirContratista && !v.empresa_id?.trim()) {
      form.setError('empresa_id', { message: 'Seleccione contratista' })
      return
    }
    const empresa_id =
      permitirContratista && v.empresa_id?.trim()
        ? Number(v.empresa_id)
        : empresa === 'TERCERO' && v.empresa_id?.trim()
          ? Number(v.empresa_id)
          : empresa === 'GRS'
            ? null
            : undefined
    const { empresa_id: _omit, ...rest } = v
    onSubmit({ ...rest, empresa, empresa_id }, conFoto ? foto : undefined)
  }

  return (
    <form className="grid gap-3 sm:grid-cols-2" onSubmit={form.handleSubmit(handleSubmit)}>
      {conFoto ? (
        <div className="bio-foto-picker sm:col-span-2">
          {fotoUrl ? (
            <img src={fotoUrl} alt="" width={96} height={96} className="bio-foto-img" />
          ) : (
            <PersonalAvatar size={96} className="bio-foto-img" />
          )}
          <div className="bio-foto-actions">
            <label className="bio-btn bio-btn-soft bio-btn-sm">
              <i className="fa-solid fa-camera" aria-hidden /> {foto ? 'Cambiar foto' : 'Agregar foto'}
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                hidden
                onChange={(e) => {
                  elegirFoto(e.target.files?.[0])
                  e.target.value = ''
                }}
              />
            </label>
            {foto ? (
              <button type="button" className="bio-btn bio-btn-ghost bio-btn-sm" onClick={() => setFoto(null)}>
                <i className="fa-solid fa-xmark" aria-hidden /> Quitar
              </button>
            ) : null}
            {fotoErr ? <span className="text-xs text-red-600">{fotoErr}</span> : null}
          </div>
        </div>
      ) : null}
      <label className="text-sm sm:col-span-2">
        DNI
        <div className="mt-1 flex items-center gap-2">
          <Input
            {...dniReg}
            inputMode="numeric"
            onChange={(e) => {
              dniReg.onChange(e)
              const d = e.target.value.replace(/\D/g, '')
              setDniMsg('')
              if (d.length === 8 && d !== ultimo.current.dni) buscarDni(d)
            }}
          />
          <Button type="button" variant="ghost" size="sm" disabled={buscando} onClick={() => buscarDni(form.getValues('dni'))}>
            Buscar
          </Button>
        </div>
        {buscando ? (
          <span className="mt-1 block text-xs text-[var(--color-bio-muted)]">Buscando…</span>
        ) : dniMsg ? (
          <span className={`mt-1 block text-xs ${dniErr ? 'text-red-600' : 'text-[var(--color-bio-muted)]'}`}>{dniMsg}</span>
        ) : null}
      </label>
      <label className="text-sm">
        Nombres
        <Input className="mt-1" {...form.register('nombres')} />
      </label>
      <label className="text-sm">
        Apellidos
        <Input className="mt-1" {...form.register('apellidos')} />
      </label>
      <label className="text-sm">
        Cargo
        <Input className="mt-1" {...form.register('cargo')} />
      </label>
      {mostrarContratista ? (
        <label className="text-sm sm:col-span-2">
          Contratista
          <Select className="mt-1" {...form.register('empresa_id')}>
            <option value="">Seleccione</option>
            {empresas
              .filter((e) => e.estado === 'ACTIVO')
              .map((e) => (
                <option key={e.id} value={String(e.id)}>
                  {e.codigo} — {e.razon_social}
                </option>
              ))}
          </Select>
        </label>
      ) : null}
      <label className="text-sm">
        Fecha contratación
        <Input className="mt-1" type="date" {...form.register('fecha_contratacion')} />
      </label>
      <label className="text-sm">
        Estado
        <Select className="mt-1" {...form.register('estado')}>
          <option value="ACTIVO">Activo</option>
          <option value="CESADO">Cesado</option>
        </Select>
      </label>
      <label className="text-sm sm:col-span-2">
        Granja base
        <Select className="mt-1" {...form.register('granja_base_cod')}>
          <option value="">— Sin asignar —</option>
          {granjas.map((g) => (
            <option key={g.codigo} value={g.codigo}>
              {g.codigo} — {g.nombre}
            </option>
          ))}
        </Select>
      </label>
      <div className="sm:col-span-2">
        <Button type="submit" disabled={loading}>
          Guardar
        </Button>
      </div>
    </form>
  )
}
