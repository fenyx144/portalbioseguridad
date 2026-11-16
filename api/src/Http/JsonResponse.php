<?php

declare(strict_types=1);

namespace Bio\Api\Http;

final class JsonResponse
{
    /**
     * @param mixed $data
     * @param array<string, mixed>|null $error
     */
    public static function send(bool $ok, $data, ?array $error, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'data' => $data,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
