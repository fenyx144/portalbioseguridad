<?php

declare(strict_types=1);

namespace Bio\Api\Services;

use Bio\Api\Bootstrap\Env;
use Bio\Api\Repositories\PersonalRepository;

final class DniService
{
    public function consultar(string $dni): array
    {
        $dni = preg_replace('/\D+/', '', trim($dni));
        if ($dni === null || !preg_match('/^\d{8}$/', $dni)) {
            return ['ok' => false, 'error' => ['code' => 'validation', 'message' => 'Ingrese un DNI de 8 dígitos.']];
        }

        $repo = new PersonalRepository();
        $existente = null;
        $row = $repo->findByDni($dni);
        if ($row !== null) {
            $p = $repo->find((int) $row['id']);
            if ($p !== null) {
                $existente = [
                    'id' => (int) $p['id'],
                    'nombres' => (string) $p['nombres'],
                    'apellidos' => (string) $p['apellidos'],
                    'estado' => (string) $p['estado'],
                ];
            }
        }

        $r = $this->factiliza($dni);
        if (!$r['ok']) {
            if ($existente !== null) {
                return ['ok' => true, 'data' => [
                    'dni' => $dni,
                    'nombres' => $existente['nombres'],
                    'apellidos' => $existente['apellidos'],
                    'fuente' => 'registro',
                    'existente' => $existente,
                ]];
            }

            return $r;
        }

        return ['ok' => true, 'data' => [
            'dni' => $dni,
            'nombres' => $r['nombres'],
            'apellidos' => $r['apellidos'],
            'fuente' => 'factiliza',
            'existente' => $existente,
        ]];
    }

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

    private function factiliza(string $dni): array
    {
        $cfg = $this->config();
        if ($cfg['token'] === '') {
            return ['ok' => false, 'error' => ['code' => 'config', 'message' => 'Consulta DNI no configurada en el servidor.']];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => ['code' => 'config', 'message' => 'El servidor no tiene cURL habilitado.']];
        }

        $ch = curl_init($cfg['base'] . '/dni/info/' . rawurlencode($dni));
        if ($ch === false) {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'No se pudo consultar el DNI.']];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $cfg['token'],
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'No se pudo consultar el DNI.']];
        }
        if ($http === 401 || $http === 403) {
            return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'Consulta DNI no autorizada.']];
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json) || empty($json['success']) || !is_array($json['data'] ?? null)) {
            if ($http >= 500) {
                return ['ok' => false, 'error' => ['code' => 'upstream', 'message' => 'El servicio de consulta DNI no está disponible.']];
            }

            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'DNI no encontrado.']];
        }

        $d = $json['data'];
        $nombres = $this->limpiar((string) ($d['nombres'] ?? ''));
        $apellidos = $this->limpiar(trim((string) ($d['apellido_paterno'] ?? '') . ' ' . (string) ($d['apellido_materno'] ?? '')));
        if ($nombres === '' && $apellidos === '') {
            $completo = trim((string) ($d['nombre_completo'] ?? ''));
            if (strpos($completo, ',') !== false) {
                $partes = array_map('trim', explode(',', $completo, 2));
                $apellidos = $this->limpiar($partes[0]);
                $nombres = $this->limpiar($partes[1] ?? '');
            } else {
                $nombres = $this->limpiar($completo);
            }
        }
        if ($nombres === '' && $apellidos === '') {
            return ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'No se obtuvo nombre para el DNI.']];
        }

        return ['ok' => true, 'nombres' => $nombres, 'apellidos' => $apellidos];
    }

    private function limpiar(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));

        return $s === null ? '' : $s;
    }
}
