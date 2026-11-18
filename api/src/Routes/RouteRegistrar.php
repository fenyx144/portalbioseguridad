<?php

declare(strict_types=1);

namespace Bio\Api\Routes;

use Bio\Api\Controllers\AlmacenController;
use Bio\Api\Controllers\ArticuloIngresoController;
use Bio\Api\Controllers\AuthController;
use Bio\Api\Controllers\CapacitacionController;
use Bio\Api\Controllers\ConfigController;
use Bio\Api\Controllers\EmpresaController;
use Bio\Api\Controllers\GranjaController;
use Bio\Api\Controllers\OperacionController;
use Bio\Api\Controllers\PersonalController;
use Bio\Api\Controllers\PlanificacionController;
use Bio\Api\Controllers\ProveedorPlanController;
use Bio\Api\Controllers\PuntoController;
use Bio\Api\Controllers\ReporteController;
use Bio\Api\Http\Router;

final class RouteRegistrar
{
    public static function register(Router $r): void
    {
        $r->add(['POST'], '/auth/login', function () {
            AuthController::login();
        });
        $r->add(['POST'], '/auth/logout', function () {
            AuthController::logout();
        });
        $r->add(['GET'], '/auth/me', function () {
            AuthController::me();
        });

        $r->add(['GET'], '/empresas/consultar-ruc/{ruc}', function (array $p) {
            EmpresaController::consultarRuc($p);
        });
        $r->add(['GET'], '/empresas', function () {
            EmpresaController::index();
        });
        $r->add(['POST'], '/empresas', function () {
            EmpresaController::store();
        });
        $r->add(['PATCH'], '/empresas/{id}', function (array $p) {
            EmpresaController::patch($p);
        });

        $r->add(['GET'], '/granjas', function () {
            GranjaController::index();
        });
        $r->add(['GET'], '/config', function () {
            ConfigController::index();
        });

        $r->add(['GET'], '/articulos-ingreso', function () {
            ArticuloIngresoController::index();
        });
        $r->add(['GET'], '/articulos-ingreso/movil', function () {
            ArticuloIngresoController::movil();
        });
        $r->add(['POST'], '/articulos-ingreso', function () {
            ArticuloIngresoController::store();
        });
        $r->add(['PATCH'], '/articulos-ingreso/{id}', function (array $p) {
            ArticuloIngresoController::update($p);
        });
        $r->add(['PATCH'], '/config', function () {
            ConfigController::patch();
        });

        $r->add(['GET'], '/capacitaciones', function () {
            CapacitacionController::index();
        });
        $r->add(['GET'], '/capacitaciones/registros-empresa', function () {
            CapacitacionController::registrosEmpresa();
        });
        $r->add(['GET'], '/capacitaciones/sesiones', function () {
            CapacitacionController::sesionesIndex();
        });
        $r->add(['POST'], '/capacitaciones/sesiones', function () {
            CapacitacionController::sesionesStore();
        });
        $r->add(['GET'], '/capacitaciones/sesiones/{id}', function (array $p) {
            CapacitacionController::sesionesShow($p);
        });
        $r->add(['PATCH'], '/capacitaciones/sesiones/{id}', function (array $p) {
            CapacitacionController::sesionesUpdate($p);
        });
        $r->add(['POST'], '/capacitaciones/sesiones/{id}/asistencia', function (array $p) {
            CapacitacionController::sesionesAsistencia($p);
        });
        $r->add(['POST'], '/capacitaciones/sesiones/{id}/evaluacion', function (array $p) {
            CapacitacionController::sesionesEvaluacion($p);
        });
        $r->add(['POST'], '/capacitaciones/sesiones/{id}/participantes', function (array $p) {
            CapacitacionController::sesionesAgregarParticipantes($p);
        });
        $r->add(['DELETE'], '/capacitaciones/sesiones/{id}/participantes/{personalId}', function (array $p) {
            CapacitacionController::sesionesQuitarParticipante($p);
        });
        $r->add(['POST'], '/capacitaciones/sesiones/{id}/cancelar', function (array $p) {
            CapacitacionController::sesionesCancelar($p);
        });
        $r->add(['POST'], '/capacitaciones', function () {
            CapacitacionController::store();
        });
        $r->add(['PATCH'], '/capacitaciones/{id}', function (array $p) {
            CapacitacionController::update($p);
        });

        $r->add(['GET'], '/puestos', function () {
            OperacionController::puestos();
        });
        $r->add(['GET'], '/requerimientos', function () {
            OperacionController::requerimientos();
        });
        $r->add(['POST'], '/requerimientos', function () {
            OperacionController::guardarRequerimientos();
        });
        $r->add(['GET'], '/asistencia', function () {
            OperacionController::asistencia();
        });
        $r->add(['POST'], '/asistencia', function () {
            OperacionController::marcar();
        });
        $r->add(['GET'], '/alcoholemia', function () {
            OperacionController::alcoholemia();
        });
        $r->add(['POST'], '/alcoholemia', function () {
            OperacionController::registrarAlcoholemia();
        });
        $r->add(['GET'], '/anomalias', function () {
            OperacionController::anomalias();
        });
        $r->add(['POST'], '/anomalias', function () {
            OperacionController::crearAnomalia();
        });
        $r->add(['PATCH'], '/anomalias/{id}', function (array $p) {
            OperacionController::actualizarAnomalia($p);
        });
        $r->add(['GET'], '/personal/dni/{dni}/referencia', function (array $p) {
            OperacionController::fotoReferencia($p);
        });
        $r->add(['GET'], '/personal/dni/{dni}/foto', function (array $p) {
            OperacionController::fotoArchivo($p);
        });

        $r->add(['GET'], '/personal', function () {
            PersonalController::index();
        });
        $r->add(['POST'], '/personal', function () {
            PersonalController::store();
        });
        $r->add(['GET'], '/personal/cargos', function () {
            PersonalController::cargos();
        });
        $r->add(['GET'], '/personal/consultar-dni/{dni}', function (array $p) {
            PersonalController::consultarDni($p);
        });
        $r->add(['GET'], '/personal/{id}', function (array $p) {
            PersonalController::show($p);
        });
        $r->add(['PATCH'], '/personal/{id}', function (array $p) {
            PersonalController::update($p);
        });
        $r->add(['GET'], '/personal/{id}/ficha', function (array $p) {
            PersonalController::ficha($p);
        });
        $r->add(['POST'], '/personal/{id}/foto', function (array $p) {
            PersonalController::uploadFoto($p);
        });
        $r->add(['POST'], '/personal/{id}/usuario', function (array $p) {
            OperacionController::asegurarUsuario($p);
        });
        $r->add(['POST'], '/personal/{id}/capacitaciones', function (array $p) {
            PersonalController::addCapacitacion($p);
        });
        $r->add(['DELETE'], '/personal/{id}/capacitaciones/{rowId}', function (array $p) {
            PersonalController::deleteCapacitacion($p);
        });

        $r->add(['GET'], '/puntos/tipos', function () {
            PuntoController::tipos();
        });
        $r->add(['GET'], '/puntos', function () {
            PuntoController::index();
        });
        $r->add(['POST'], '/puntos', function () {
            PuntoController::store();
        });
        $r->add(['GET'], '/puntos/{id}', function (array $p) {
            PuntoController::show($p);
        });
        $r->add(['PATCH'], '/puntos/{id}', function (array $p) {
            PuntoController::update($p);
        });
        $r->add(['POST'], '/puntos/{id}/desactivar', function (array $p) {
            PuntoController::desactivar($p);
        });
        $r->add(['POST'], '/granjas/{cod}/puntos/copiar', function (array $p) {
            PuntoController::copiar($p);
        });

        $r->add(['GET'], '/planificacion/semana', function () {
            PlanificacionController::semana();
        });
        $r->add(['GET'], '/planificacion/dia', function () {
            PlanificacionController::dia();
        });
        $r->add(['POST'], '/planificacion/asignaciones', function () {
            PlanificacionController::guardarAsignacion();
        });
        $r->add(['POST'], '/planificacion/asignaciones/lote', function () {
            PlanificacionController::guardarAsignacionesLote();
        });
        $r->add(['POST'], '/planificacion/asignaciones/rapida', function () {
            PlanificacionController::asignarRapido();
        });
        $r->add(['DELETE'], '/planificacion/asignaciones/{id}', function (array $p) {
            PlanificacionController::eliminarAsignacion($p);
        });
        $r->add(['POST'], '/planificacion/semana/copiar', function () {
            PlanificacionController::copiarSemana();
        });
        $r->add(['GET'], '/planificacion/personal-disponible', function () {
            PlanificacionController::personalDisponible();
        });
        $r->add(['POST'], '/planificacion/reemplazos', function () {
            PlanificacionController::reemplazo();
        });
        $r->add(['GET'], '/planificacion/cobertura-resumen', function () {
            PlanificacionController::cobertura();
        });

        $r->add(['GET'], '/proveedor/plan/contexto', function () {
            ProveedorPlanController::contexto();
        });
        $r->add(['GET'], '/proveedor/plan', function () {
            ProveedorPlanController::listar();
        });
        $r->add(['POST'], '/proveedor/plan', function () {
            ProveedorPlanController::guardar();
        });
        $r->add(['PATCH'], '/proveedor/plan/{id}', function (array $p) {
            ProveedorPlanController::actualizar($p);
        });
        $r->add(['DELETE'], '/proveedor/plan/{id}', function (array $p) {
            ProveedorPlanController::eliminar($p);
        });
        $r->add(['POST'], '/proveedor/personal', function () {
            ProveedorPlanController::guardarPersonal();
        });
        $r->add(['POST'], '/proveedor/personal/baja', function () {
            ProveedorPlanController::bajaPersonal();
        });
        $r->add(['POST'], '/proveedor/vehiculos', function () {
            ProveedorPlanController::guardarVehiculo();
        });
        $r->add(['POST'], '/proveedor/vehiculos/baja', function () {
            ProveedorPlanController::bajaVehiculo();
        });

        $r->add(['GET'], '/almacen/insumos', function () {
            AlmacenController::insumos();
        });
        $r->add(['POST'], '/almacen/insumos', function () {
            AlmacenController::crearInsumo();
        });
        $r->add(['PATCH'], '/almacen/insumos/{id}', function (array $p) {
            AlmacenController::actualizarInsumo($p);
        });
        /*
         * Entrega GRS → proveedor/tercero. Fuera de uso. No borrar.
         *
         * $r->add(['POST'], '/almacen/saldos', function () {
         *     AlmacenController::guardarSaldos();
         * });
         * $r->add(['GET'], '/almacen/entregas', function () {
         *     AlmacenController::entregas();
         * });
         * $r->add(['POST'], '/almacen/entregas', function () {
         *     AlmacenController::crearEntrega();
         * });
         * $r->add(['GET'], '/almacen/entregas/{id}', function (array $p) {
         *     AlmacenController::entrega($p);
         * });
         * $r->add(['POST'], '/almacen/entregas/{id}/recepcion', function (array $p) {
         *     AlmacenController::recepcion($p);
         * });
         * $r->add(['POST'], '/almacen/entregas/{id}/anular', function (array $p) {
         *     AlmacenController::anular($p);
         * });
         * $r->add(['GET'], '/almacen/consumos', function () {
         *     AlmacenController::consumos();
         * });
         * $r->add(['POST'], '/almacen/consumos', function () {
         *     AlmacenController::crearConsumo();
         * });
         * $r->add(['DELETE'], '/almacen/consumos/{id}', function (array $p) {
         *     AlmacenController::eliminarConsumo($p);
         * });
         * $r->add(['GET'], '/almacen/stock', function () {
         *     AlmacenController::stock();
         * });
         * $r->add(['GET'], '/almacen/relevos', function () {
         *     AlmacenController::relevos();
         * });
         * $r->add(['GET'], '/almacen/relevos/{id}', function (array $p) {
         *     AlmacenController::relevo($p);
         * });
         * $r->add(['POST'], '/almacen/relevos', function () {
         *     AlmacenController::crearRelevo();
         * });
         */
        $r->add(['GET'], '/almacen/campo-context', function () {
            AlmacenController::campoContext();
        });
        $r->add(['GET'], '/almacen/granja/stock', function () {
            AlmacenController::stockGranja();
        });
        $r->add(['GET'], '/almacen/granja/kardex', function () {
            AlmacenController::kardexGranja();
        });
        $r->add(['POST'], '/almacen/granja/movimientos', function () {
            AlmacenController::movimientoGranja();
        });

        $r->add(['GET'], '/reportes/personal-registrado', function () {
            ReporteController::personalRegistrado();
        });
        $r->add(['GET'], '/reportes/personal-asignado', function () {
            ReporteController::personalAsignado();
        });
        $r->add(['GET'], '/reportes/capacitaciones', function () {
            ReporteController::capacitacionesPersonal();
        });
        $r->add(['GET'], '/reportes/stock-indumentaria', function () {
            ReporteController::stockIndumentariaPorProveedor();
        });
        $r->add(['GET'], '/reportes/faltante', function () {
            OperacionController::faltante();
        });
        $r->add(['GET'], '/reportes/horarios', function () {
            OperacionController::horarios();
        });
        $r->add(['GET'], '/reportes/alcoholemia', function () {
            OperacionController::alcoholemia();
        });
        $r->add(['GET'], '/reportes/anomalias', function () {
            OperacionController::anomalias();
        });
        $r->add(['GET'], '/reportes/planificacion-semana.pdf', function () {
            ReporteController::pdfPlanificacionSemana();
        });
        $r->add(['GET'], '/reportes/personal-asignado.pdf', function () {
            ReporteController::pdfPersonalAsignado();
        });
        $r->add(['GET'], '/reportes/personal-registrado.pdf', function () {
            ReporteController::pdfPersonalRegistrado();
        });
        $r->add(['GET'], '/reportes/capacitaciones.pdf', function () {
            ReporteController::pdfCapacitaciones();
        });
        $r->add(['GET'], '/reportes/stock-indumentaria.pdf', function () {
            ReporteController::pdfStockIndumentariaPorProveedor();
        });
    }
}
