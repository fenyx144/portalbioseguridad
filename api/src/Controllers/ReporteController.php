<?php

declare(strict_types=1);

namespace Bio\Api\Controllers;

use Bio\Api\Http\JsonResponse;
use Bio\Api\Services\AlmacenService;
use Bio\Api\Services\ReportPdfService;
use Bio\Api\Services\ReporteService;
use Bio\Api\Support\PdfResponse;
use Bio\Api\Support\UserContext;

final class ReporteController extends BaseController
{
    public static function personalRegistrado(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        self::respond((new ReporteService())->personalRegistrado($query));
    }

    public static function personalAsignado(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        self::respond((new ReporteService())->personalAsignado($query));
    }

    public static function capacitacionesPersonal(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        self::respond((new ReporteService())->capacitacionesPersonal($query));
    }

    public static function stockIndumentariaPorProveedor(): void
    {
        self::auth();
        UserContext::requireGrs();
        $query = self::query();
        $query['categoria'] = 'INDUMENTARIA';
        self::respond((new AlmacenService())->stock($query));
    }

    public static function pdfPlanificacionSemana(): void
    {
        self::auth();
        $query = self::query();
        try {
            $pdf = (new ReportPdfService())->planificacionSemana($query);
            PdfResponse::send($pdf, 'planificacion-semana.pdf');
        } catch (\InvalidArgumentException $e) {
            JsonResponse::send(false, null, ['code' => 'validation', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'mPDF') !== false || stripos($msg, 'vendor') !== false) {
                $msg = 'No se pudo cargar mPDF (revise sanidad/vendor en el servidor). ' . $msg;
            }
            JsonResponse::send(false, null, ['code' => 'pdf', 'message' => $msg], 500);
        }
    }

    public static function pdfPersonalAsignado(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        try {
            $pdf = (new ReportPdfService())->personalAsignado($query);
            PdfResponse::send($pdf, 'personal-asignado.pdf');
        } catch (\Throwable $e) {
            JsonResponse::send(false, null, ['code' => 'pdf', 'message' => $e->getMessage()], 500);
        }
    }

    public static function pdfPersonalRegistrado(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        try {
            $pdf = (new ReportPdfService())->personalRegistrado($query);
            PdfResponse::send($pdf, 'personal-registrado.pdf');
        } catch (\Throwable $e) {
            JsonResponse::send(false, null, ['code' => 'pdf', 'message' => $e->getMessage()], 500);
        }
    }

    public static function pdfCapacitaciones(): void
    {
        self::auth();
        $query = self::query();
        UserContext::applyPersonalScope($query);
        try {
            $pdf = (new ReportPdfService())->capacitaciones($query);
            PdfResponse::send($pdf, 'capacitaciones.pdf');
        } catch (\Throwable $e) {
            JsonResponse::send(false, null, ['code' => 'pdf', 'message' => $e->getMessage()], 500);
        }
    }

    public static function pdfStockIndumentariaPorProveedor(): void
    {
        self::auth();
        UserContext::requireGrs();
        $query = self::query();
        try {
            $pdf = (new ReportPdfService())->stockIndumentariaPorProveedor($query);
            PdfResponse::send($pdf, 'stock-indumentaria-proveedor.pdf');
        } catch (\Throwable $e) {
            JsonResponse::send(false, null, ['code' => 'pdf', 'message' => $e->getMessage()], 500);
        }
    }
}
