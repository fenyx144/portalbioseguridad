import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { AuthProvider } from '../features/auth/useAuth'
import { useTheme } from '../shared/hooks/useTheme'

function ThemeBootstrap() {
  useTheme()
  return null
}

const client = new QueryClient({
  defaultOptions: { queries: { refetchOnWindowFocus: false, staleTime: 30_000 } },
})

export function Providers({ children }: { children: ReactNode }) {
  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <ThemeBootstrap />
        {children}
      </AuthProvider>
    </QueryClientProvider>
  )
}
