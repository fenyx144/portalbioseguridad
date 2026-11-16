import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, useContext, useMemo, type ReactNode } from 'react'
import { api } from '../../shared/api/client'

export type AuthUser = {
  codigo: string
  nombre: string
  actor: 'grs' | 'empresa'
  empresa_id?: number | null
  empresa_codigo?: string | null
  empresa_nombre?: string | null
}

type Ctx = {
  user: AuthUser | null
  setUser: (u: AuthUser | null) => void
  isLoading: boolean
  logout: () => Promise<void>
}

const AuthContext = createContext<Ctx | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const qc = useQueryClient()
  const q = useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      try {
        return await api.get<{ user: AuthUser }>('/auth/me')
      } catch {
        return null
      }
    },
    retry: false,
  })

  const raw = q.data?.user
  const user: AuthUser | null = raw ? { ...raw, actor: raw.actor ?? 'grs' } : null

  const value = useMemo<Ctx>(
    () => ({
      user,
      isLoading: q.isLoading,
      setUser: (u) => {
        if (u) qc.setQueryData(['auth', 'me'], { user: u })
        else qc.setQueryData(['auth', 'me'], null)
      },
      logout: async () => {
        await api.post('/auth/logout')
        qc.setQueryData(['auth', 'me'], null)
      },
    }),
    [user, q.isLoading, qc],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('AuthProvider requerido')
  return ctx
}
