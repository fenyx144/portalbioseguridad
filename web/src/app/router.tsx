import { createBrowserRouter, Navigate } from 'react-router-dom'
import { LoginPage } from '../features/auth/LoginPage'
import { AppShell } from '../features/layout/AppShell'
import { HomePage } from '../features/layout/HomePage'
import { RequireAuth } from '../features/layout/RequireAuth'
import { PersonalDetailPage } from '../features/personal/PersonalDetailPage'
import { PersonalListPage } from '../features/personal/PersonalListPage'
import { PlanificacionPage } from '../features/planificacion/PlanificacionPage'
import { PuntosPage } from '../features/puntos/PuntosPage'
import { ReporteAsignadoPage } from '../features/reportes/ReporteAsignadoPage'
import { ReporteCapacitacionesPage } from '../features/reportes/ReporteCapacitacionesPage'
import { ReporteRegistradoPage } from '../features/reportes/ReporteRegistradoPage'
import { ReporteStockIndumentariaPage } from '../features/reportes/ReporteStockIndumentariaPage'
import { EmpresasPage } from '../features/empresas/EmpresasPage'
import { CapacitacionesPage } from '../features/capacitaciones/CapacitacionesPage'
import { CapacitacionCatalogoPage } from '../features/capacitaciones/CapacitacionCatalogoPage'
import { AlmacenPage } from '../features/almacen/AlmacenPage'
import { InsumosPage } from '../features/almacen/InsumosPage'
import { StockPage } from '../features/almacen/StockPage'
import { ArticulosIngresoPage } from '../features/articulos/ArticulosIngresoPage'

export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPage /> },
    {
      element: <RequireAuth />,
      children: [
        {
          element: <AppShell />,
          children: [
            { path: '/', element: <HomePage /> },
            { path: '/personal', element: <PersonalListPage /> },
            { path: '/personal/:id', element: <PersonalDetailPage /> },
            { path: '/empresas', element: <EmpresasPage /> },
            { path: '/puntos', element: <PuntosPage /> },
            { path: '/planificacion', element: <PlanificacionPage /> },
            { path: '/capacitaciones', element: <CapacitacionesPage /> },
            { path: '/capacitaciones/catalogo', element: <CapacitacionCatalogoPage /> },
            { path: '/almacen', element: <AlmacenPage /> },
            { path: '/almacen/insumos', element: <InsumosPage /> },
            { path: '/articulos-ingreso', element: <ArticulosIngresoPage /> },
            { path: '/almacen/stock', element: <StockPage /> },
            { path: '/reportes/personal-registrado', element: <ReporteRegistradoPage /> },
            { path: '/reportes/personal-asignado', element: <ReporteAsignadoPage /> },
            { path: '/reportes/capacitaciones', element: <ReporteCapacitacionesPage /> },
            { path: '/reportes/stock-indumentaria', element: <ReporteStockIndumentariaPage /> },
            { path: '/reportes', element: <Navigate to="/reportes/personal-registrado" replace /> },
          ],
        },
      ],
    },
    { path: '*', element: <Navigate to="/" replace /> },
  ],
  { basename: '/bioseguridad/web' },
)
