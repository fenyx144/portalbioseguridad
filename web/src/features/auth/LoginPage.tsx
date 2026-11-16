import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Navigate } from 'react-router-dom'
import { z } from 'zod'
import { api } from '../../shared/api/client'
import { useAuth, type AuthUser } from './useAuth'

const schema = z.object({
  usuario: z.string().min(1, 'Ingrese usuario'),
  clave: z.string().min(1, 'Ingrese clave'),
})

type Form = z.infer<typeof schema>

export function LoginPage() {
  const { user, setUser, isLoading } = useAuth()
  const form = useForm<Form>({ resolver: zodResolver(schema) })
  const login = useMutation({
    mutationFn: (data: Form) => api.post<{ user: AuthUser }>('/auth/login', data),
    onSuccess: (data) => setUser(data.user),
  })

  if (!isLoading && user) {
    return <Navigate to="/" replace />
  }

  return (
    <div className="bio-login-root">
      <section className="bio-login-hero" aria-hidden>
        <div className="bio-login-hero-inner">
          <div className="bio-login-hero-badge">
            <img
              src={`${import.meta.env.BASE_URL}logo-grs.png`}
              alt="GRS"
              style={{ height: '8rem', maxWidth: '12rem', objectFit: 'contain' }}
            />
          </div>
          <h2>Bioseguridad</h2>
          <p>Granja Rinconada del Sur</p>
        
        </div>
      </section>

      <section className="bio-login-panel">
        <form className="bio-login-form" onSubmit={form.handleSubmit((v) => login.mutate(v))}>
          <div className="bio-login-mobile-logo">
            <img src={`${import.meta.env.BASE_URL}logo-grs.png`} alt="GRS" />
          </div>
          <h1>Iniciar sesión</h1>
          
          <div>
            <div style={{ marginBottom: '1rem' }}>
              <label className="bio-field" htmlFor="usuario">
                Usuario
              </label>
              <div className="bio-login-input-wrap">
                <i className="fa-solid fa-user" aria-hidden />
                <input
                  id="usuario"
                  className="bio-login-input"
                  autoComplete="username"
                  placeholder="Usuario"
                  {...form.register('usuario')}
                />
              </div>
              {form.formState.errors.usuario && (
                <p style={{ margin: '0.25rem 0 0', fontSize: '0.75rem', color: '#b91c1c' }}>
                  {form.formState.errors.usuario.message}
                </p>
              )}
            </div>

            <div style={{ marginBottom: '0.25rem' }}>
              <label className="bio-field" htmlFor="clave">
                Contraseña
              </label>
              <div className="bio-login-input-wrap">
                <i className="fa-solid fa-lock" aria-hidden />
                <input
                  id="clave"
                  type="password"
                  className="bio-login-input"
                  autoComplete="current-password"
                  placeholder="••••••••"
                  {...form.register('clave')}
                />
              </div>
              {form.formState.errors.clave && (
                <p style={{ margin: '0.25rem 0 0', fontSize: '0.75rem', color: '#b91c1c' }}>
                  {form.formState.errors.clave.message}
                </p>
              )}
            </div>
          </div>

          <button type="submit" className="bio-login-submit" disabled={login.isPending}>
            {login.isPending ? (
              <>
                <i className="fa-solid fa-spinner fa-spin" aria-hidden />
                Verificando…
              </>
            ) : (
              <>
                Ingresar
                <i className="fa-solid fa-arrow-right" aria-hidden />
              </>
            )}
          </button>

          {login.isError && (
            <div className="bio-login-alert" role="alert">
              <i className="fa-solid fa-circle-exclamation" aria-hidden />
              <span>{(login.error as Error).message}</span>
            </div>
          )}
        </form>
      </section>
    </div>
  )
}
