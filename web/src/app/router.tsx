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
import { ArticulosIngresoPage } from '../features/articulos/ArticulosIngresoPage'
import { AlcoholemiaPage } from '../features/operacion/AlcoholemiaPage'
import { AnomaliasPage } from '../features/operacion/AnomaliasPage'
import { AsistenciaPage } from '../features/operacion/AsistenciaPage'
import { RequerimientoPage } from '../features/operacion/RequerimientoPage'
import { ReporteFaltantePage } from '../features/reportes/ReporteFaltantePage'
import { ReporteHorariosPage } from '../features/reportes/ReporteHorariosPage'

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
            { path: '/personal/requerimiento', element: <RequerimientoPage /> },
            { path: '/personal/asistencia', element: <AsistenciaPage /> },
            { path: '/personal/alcoholemia', element: <AlcoholemiaPage /> },
            { path: '/personal/anomalias', element: <AnomaliasPage /> },
            { path: '/personal/:id', element: <PersonalDetailPage /> },
            { path: '/empresas', element: <EmpresasPage /> },
            { path: '/puntos', element: <PuntosPage /> },
            { path: '/planificacion', element: <PlanificacionPage /> },
            { path: '/planificacion/anomalias', element: <AnomaliasPage /> },
            { path: '/capacitaciones', element: <CapacitacionesPage /> },
            { path: '/capacitaciones/catalogo', element: <CapacitacionCatalogoPage /> },
            { path: '/almacen', element: <AlmacenPage /> },
            { path: '/almacen/kardex', element: <AlmacenPage /> },
            { path: '/almacen/registro', element: <AlmacenPage /> },
            { path: '/almacen/insumos', element: <InsumosPage /> },
            { path: '/articulos-ingreso', element: <ArticulosIngresoPage /> },
            { path: '/almacen/stock', element: <Navigate to="/almacen" replace /> },
            { path: '/reportes/personal-registrado', element: <ReporteRegistradoPage /> },
            { path: '/reportes/personal-asignado', element: <ReporteAsignadoPage /> },
            { path: '/reportes/capacitaciones', element: <ReporteCapacitacionesPage /> },
            { path: '/reportes/stock-indumentaria', element: <ReporteStockIndumentariaPage /> },
            { path: '/reportes/faltante', element: <ReporteFaltantePage /> },
            { path: '/reportes/horarios', element: <ReporteHorariosPage /> },
            { path: '/reportes/alcoholemia', element: <AlcoholemiaPage reporte /> },
            { path: '/reportes/anomalias', element: <AnomaliasPage reporte /> },
            { path: '/reportes', element: <Navigate to="/reportes/personal-registrado" replace /> },
          ],
        },
      ],
    },
    { path: '*', element: <Navigate to="/" replace /> },
  ],
  { basename: '/bioseguridad/web' },
)
