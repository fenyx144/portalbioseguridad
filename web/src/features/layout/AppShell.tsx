import { useEffect, useMemo, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useTheme } from '../../shared/hooks/useTheme'
import { useAuth } from '../auth/useAuth'
import { navGroups } from './navConfig'

const PIN_KEY = 'ix2-sidebar-pin'
const GROUPS_KEY = 'bio-nav-groups'

function readGroups(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(GROUPS_KEY) ?? '{}') as Record<string, boolean>
  } catch {
    return {}
  }
}

function iniciales(nombre: string) {
  const parts = nombre.trim().split(/\s+/).filter(Boolean)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || 'U'
}

export function AppShell() {
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const [pinned, setPinned] = useState(() => localStorage.getItem(PIN_KEY) !== '0')
  const [groupsOpen, setGroupsOpen] = useState<Record<string, boolean>>(readGroups)
  const isGrs = (user?.actor ?? 'grs') === 'grs'

  const groups = useMemo(
    () =>
      navGroups
        .filter((g) => isGrs || !g.grsOnly)
        .map((g) => ({
          ...g,
          items: g.items
            .filter((i) => (isGrs ? !i.empresaOnly : !i.grsOnly))
            .map((i) => ({ ...i, label: !isGrs && i.labelEmpresa ? i.labelEmpresa : i.label })),
        }))
        .filter((g) => g.items.length > 0),
    [isGrs],
  )

  useEffect(() => {
    localStorage.setItem(PIN_KEY, pinned ? '1' : '0')
  }, [pinned])

  useEffect(() => {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(groupsOpen))
  }, [groupsOpen])

  const nombre = user?.nombre || user?.codigo || ''

  return (
    <div className={`bio-shell ${pinned ? 'bio-sidebar-pinned' : ''}`}>
      <aside className={`bio-tree-col ${pinned ? 'bio-sidebar-pin-open' : ''}`} aria-label="Menú principal">
        <div className="bio-tree-card">
          <div className="bio-sidebar-top">
            <div className="bio-sidebar-top-row">
              <div className="bio-sidebar-brand">
                <i className="fa-solid fa-shield-virus bio-brand-icon" aria-hidden />
                <span className="bio-brand-text"> BIOSEGURIDAD</span>
              </div>
              <div className="bio-sidebar-actions">
                <button type="button" className="bio-theme-switch" aria-label="Cambiar tema" onClick={toggle}>
                  <i className={`fa-solid fa-sun bio-theme-sun ${theme === 'dark' ? 'hidden' : ''}`} aria-hidden />
                  <i className={`fa-solid fa-moon bio-theme-moon ${theme === 'light' ? 'hidden' : ''}`} aria-hidden />
                  <span className="bio-theme-thumb" />
                </button>
                <button
                  type="button"
                  className="bio-icon-btn"
                  aria-pressed={pinned}
                  title={pinned ? 'Desfijar menú' : 'Fijar menú'}
                  onClick={() => setPinned((p) => !p)}
                >
                  <i className="fa-solid fa-thumbtack" aria-hidden />
                </button>
              </div>
            </div>
          </div>

          <div className="bio-sidebar-scroll">
            <nav>
              {groups.map((g, gi) => {
                const open = groupsOpen[g.id] !== false
                return (
                  <div key={g.id} className="bio-nav-group" data-open={open}>
                    {gi > 0 ? <div className="bio-nav-sep" /> : null}
                    {g.id !== 'inicio' ? (
                      <button
                        type="button"
                        className="bio-nav-group-head"
                        aria-expanded={open}
                        onClick={() => setGroupsOpen((s) => ({ ...s, [g.id]: !open }))}
                      >
                        {g.label}
                        <i className="fa-solid fa-chevron-down bio-nav-chev" aria-hidden />
                      </button>
                    ) : null}
                    <div className="bio-nav-group-items">
                      {g.items.map((item) => (
                        <NavLink
                          key={item.to}
                          to={item.to}
                          end={item.end}
                          title={item.label}
                          className={({ isActive }) =>
                            `bio-nav-link ${(item.match ? item.match(pathname) : isActive) ? 'active' : ''}`
                          }
                        >
                          <i className={`fa-solid ${item.icon} bio-nav-ic`} aria-hidden />
                          <span className="bio-nav-label">{item.label}</span>
                        </NavLink>
                      ))}
                    </div>
                  </div>
                )
              })}
            </nav>
          </div>

          <div className="bio-user-wrap bio-user-block">
            <div className="bio-user-card">
              <span className="bio-user-avatar">{iniciales(nombre)}</span>
              <div style={{ minWidth: 0 }}>
                <div className="bio-user-name" title={nombre}>
                  {nombre}
                </div>
                <div className="bio-user-role">
                  {isGrs ? `GRS · ${user?.codigo ?? ''}` : (user?.empresa_nombre ?? user?.empresa_codigo ?? 'Empresa')}
                </div>
              </div>
            </div>
            <button
              type="button"
              className="bio-user-out"
              onClick={async () => {
                await logout()
                navigate('/login')
              }}
            >
              <i className="fa-solid fa-right-from-bracket" style={{ marginRight: '0.4rem' }} aria-hidden />
              Cerrar sesión
            </button>
          </div>
        </div>
      </aside>

      <div className="bio-shell-main flex min-h-screen min-w-0 flex-col">
        <main className="bio-content bio-content-rich">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
