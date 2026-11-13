<?php

declare(strict_types=1);

namespace CopiaFacil\Support;

/**
 * Todo lo que sale de la API pasa por acá.
 */
final class Response
{
    /**
     * CORS por lista blanca, nunca `*`. En producción el front pega por el
     * proxy de Netlify (mismo origen) y esto ni se usa; existe para
     * desarrollo con Vite en otro puerto.
     *
     * @param list<string> $allowedOrigins
     */
    public static function cors(array $allowedOrigins): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }
    }

    public static function json(mixed $payload, int $status = 200): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string,mixed> $extra */
    public static function error(int $status, string $message, string $code = 'error', array $extra = []): void
    {
        self::json(['error' => ['codigo' => $code, 'mensaje' => $message] + $extra], $status);
    }
}
