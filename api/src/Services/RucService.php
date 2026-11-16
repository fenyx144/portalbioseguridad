<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Bootstrap\Env;

final class RucService
{
    public function consultar(string $ruc): array
    {
        $ruc = preg_replace('/\D+/', '', trim($ruc)) ?? '';
        if ($ruc === '' || !preg_match('/^\d{11}$/', $ruc)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Ingrese un RUC de 11 dígitos.']];
        }

        $cfg = $this->config();
        if ($cfg['token'] === '') {
            return ['ok' => false, 'error' => ['code' => 'config', 'message' => 'Consulta RUC no configurada.']];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => ['code' => 'config', 'message' => 'cURL no disponible.']];
        }

        $ch = curl_init($cfg['base'] . '/ruc/info/' . rawurlencode($ruc));
        if ($ch === false) {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'No se pudo consultar el RUC.']];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $cfg['token'],
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'No se pudo consultar el RUC.']];
        }
        if ($http === 401 || $http === 403) {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'Consulta RUC no autorizada.']];
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json) || empty($json['success']) || !is_array($json['data'] ?? null)) {
            if ($http >= 500) {
                return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'Servicio RUC no disponible.']];
            }

            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'RUC no encontrado.']];
        }

        $d = $json['data'];
        $razon = trim((string) ($d['razon_social'] ?? $d['nombre_o_razon_social'] ?? $d['nombre_completo'] ?? ''));
        $razon = preg_replace('/\s+/u', ' ', $razon) ?? '';
        if ($razon === '') {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No se obtuvo razón social.']];
        }
        if (function_exists('mb_substr')) {
            $razon = mb_substr($razon, 0, 120, 'UTF-8');
        } else {
            $razon = substr($razon, 0, 120);
        }

        return ['ok' => true, 'data' => ['ruc' => $ruc, 'razon_social' => $razon]];
    }

    /** @return array{token: string, base: string} */
    private function config(): array
    {
        $token = trim(Env::get('FACTILIZA_API_TOKEN', ''));
        $base = trim(Env::get('FACTILIZA_API_BASE_URL', ''));
        if ($token === '' || $base === '') {
            $file = dirname(__DIR__, 4) . '/sanidad/config/factiliza.php';
            if (is_file($file)) {
                require_once $file;
            }
            if ($token === '' && defined('FACTILIZA_API_TOKEN')) {
                $token = trim((string) constant('FACTILIZA_API_TOKEN'));
            }
            if ($base === '' && defined('FACTILIZA_API_BASE_URL')) {
                $base = trim((string) constant('FACTILIZA_API_BASE_URL'));
            }
        }
        if ($base === '') {
            $base = 'https://api.factiliza.com/v1';
        }

        return ['token' => $token, 'base' => rtrim($base, '/')];
    }
}
