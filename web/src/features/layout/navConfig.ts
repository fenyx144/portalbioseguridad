export type NavItem = {
  to: string
  label: string
  icon: string
  end?: boolean
  match?: (path: string) => boolean
  labelEmpresa?: string
  grsOnly?: boolean
  empresaOnly?: boolean
}

export type NavGroup = {
  id: string
  label: string
  grsOnly?: boolean
  items: NavItem[]
}

export const navGroups: NavGroup[] = [
  {
    id: 'inicio',
    label: 'General',
    items: [{ to: '/', label: 'Inicio', icon: 'fa-house', end: true }],
  },
  {
    id: 'config',
    label: 'Configuración',
    grsOnly: true,
    items: [
      { to: '/empresas', label: 'Empresas', icon: 'fa-building' },
      { to: '/puntos', label: 'Granjas y puntos', icon: 'fa-location-dot' },
      { to: '/capacitaciones/catalogo', label: 'Catálogo de cursos', icon: 'fa-book-open' },
      { to: '/almacen/insumos', label: 'Catálogo de insumos', icon: 'fa-boxes-stacked' },
      { to: '/articulos-ingreso', label: 'Artículos en ingreso', icon: 'fa-suitcase' },
    ],
  },
  {
    id: 'procesos',
    label: 'Procesos',
    items: [
      { to: '/personal', label: 'Personal', labelEmpresa: 'Mi personal', icon: 'fa-users', end: true },
      { to: '/planificacion', label: 'Planificación', icon: 'fa-calendar-week' },
      { to: '/visitas-proveedor', label: 'Visitas proveedor', labelEmpresa: 'Mis visitas', icon: 'fa-truck-field' },
      { to: '/capacitaciones', label: 'Capacitaciones', icon: 'fa-graduation-cap', end: true },
      {
        to: '/almacen',
        label: 'Almacén',
        labelEmpresa: 'Almacén',
        icon: 'fa-warehouse',
        match: (p) => p === '/almacen' || p === '/almacen/stock' || p === '/almacen/kardex' || p === '/almacen/registro',
      },
    ],
  },
]

const titles: [string, string, string][] = [
  ['/personal/requerimiento', 'Personal', 'Requerimiento'],
  ['/personal/asistencia', 'Personal', 'Asistencia'],
  ['/personal/alcoholemia', 'Personal', 'Alcoholemia'],
  ['/personal/anomalias', 'Personal', 'Anomalías'],
  ['/personal/', 'Personal', 'Ficha'],
  ['/empresas', 'Configuración', 'Empresas'],
  ['/puntos', 'Configuración', 'Granjas y puntos'],
  ['/capacitaciones/catalogo', 'Configuración', 'Catálogo de cursos'],
  ['/almacen/insumos', 'Configuración', 'Catálogo de insumos'],
  ['/articulos-ingreso', 'Configuración', 'Artículos en ingreso'],
  ['/almacen/kardex', 'Almacén', 'Kardex'],
  ['/almacen/registro', 'Almacén', 'Registro'],
  ['/almacen/stock', 'Almacén', 'Stock'],
  ['/almacen', 'Almacén', 'Almacén'],
  ['/personal', 'Personal', 'Personal'],
  ['/visitas-proveedor', 'Visitas proveedor', 'Visitas proveedor'],
  ['/planificacion/anomalias', 'Planificación', 'Anomalías'],
  ['/planificacion', 'Planificación', 'Planificación'],
  ['/capacitaciones', 'Capacitaciones', 'Capacitaciones'],
  ['/reportes/personal-registrado', 'Personal', 'Personal registrado'],
  ['/reportes/personal-asignado', 'Planificación', 'Personal asignado'],
  ['/reportes/capacitaciones', 'Capacitaciones', 'Capacitaciones'],
  ['/reportes/stock-indumentaria', 'Almacén', 'Indumentaria'],
  ['/reportes/personal-asistencia', 'Personal', 'Asistencias'],
  ['/reportes/faltante', 'Personal', 'Personal faltante'],
  ['/reportes/horarios', 'Personal', 'Horarios'],
  ['/reportes/alcoholemia', 'Personal', 'Alcoholemia'],
  ['/reportes/anomalias', 'Personal', 'Anomalías'],
]

export function pageTitle(path: string): { section: string; title: string } {
  if (path === '/') return { section: 'General', title: 'Inicio' }
  const sorted = [...titles].sort((a, b) => b[0].length - a[0].length)
  for (const [k, section, title] of sorted) {
    if (path.startsWith(k)) return { section, title }
  }
  return { section: '', title: 'Bioseguridad' }
}
