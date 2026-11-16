export type NavItem = {
  to: string
  label: string
  icon: string
  end?: boolean
  /** Etiqueta alternativa para la empresa contratista */
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
    id: 'operacion',
    label: 'Operación',
    items: [
      { to: '/personal', label: 'Personal por empresa', labelEmpresa: 'Mi personal', icon: 'fa-users' },
      { to: '/planificacion', label: 'Planificación terceros', labelEmpresa: 'Planificación', icon: 'fa-calendar-week' },
      { to: '/capacitaciones', label: 'Capacitaciones', icon: 'fa-graduation-cap', end: true },
      { to: '/almacen', label: 'Entregas de insumos', labelEmpresa: 'Almacén', icon: 'fa-warehouse', end: true },
    ],
  },
  {
    id: 'reportes',
    label: 'Reportes',
    items: [
      { to: '/reportes/personal-registrado', label: 'Personal registrado', icon: 'fa-id-card' },
      { to: '/reportes/personal-asignado', label: 'Personal asignado', icon: 'fa-user-check' },
      { to: '/reportes/capacitaciones', label: 'Capacitaciones', icon: 'fa-chart-column' },
      { to: '/reportes/stock-indumentaria', label: 'Indumentaria por proveedor', icon: 'fa-shirt', grsOnly: true },
      { to: '/almacen/stock', label: 'Stock de insumos', icon: 'fa-chart-simple' },
    ],
  },
]

const titles: [string, string, string][] = [
  ['/personal/', 'Operación', 'Ficha de personal'],
  ['/empresas', 'Configuración', 'Empresas contratistas'],
  ['/puntos', 'Configuración', 'Granjas y puntos'],
  ['/capacitaciones/catalogo', 'Configuración', 'Catálogo de cursos'],
  ['/almacen/insumos', 'Configuración', 'Catálogo de insumos'],
  ['/articulos-ingreso', 'Configuración', 'Artículos en ingreso'],
  ['/almacen/stock', 'Reportes', 'Stock de insumos'],
  ['/almacen', 'Operación', 'Almacén'],
  ['/personal', 'Operación', 'Personal'],
  ['/planificacion', 'Operación', 'Planificación'],
  ['/capacitaciones', 'Operación', 'Capacitaciones'],
  ['/reportes/personal-registrado', 'Reportes', 'Personal registrado'],
  ['/reportes/personal-asignado', 'Reportes', 'Personal asignado'],
  ['/reportes/capacitaciones', 'Reportes', 'Capacitaciones'],
  ['/reportes/stock-indumentaria', 'Reportes', 'Indumentaria por proveedor'],
]

export function pageTitle(path: string): { section: string; title: string } {
  if (path === '/') return { section: 'General', title: 'Inicio' }
  for (const [k, section, title] of titles) {
    if (path.startsWith(k)) return { section, title }
  }
  return { section: '', title: 'Bioseguridad' }
}
