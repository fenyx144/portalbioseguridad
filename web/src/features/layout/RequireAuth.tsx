import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'

export function RequireAuth() {
  const { user, isLoading } = useAuth()
  const loc = useLocation()
  if (isLoading) {
    return <div className="p-8 text-sm text-[var(--color-bio-muted)]">Verificando sesión…</div>
  }
  if (!user) {
    return <Navigate to="/login" state={{ from: loc }} replace />
  }
  return <Outlet />
}
