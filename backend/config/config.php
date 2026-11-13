<?php

declare(strict_types=1);

/**
 * Configuración. Lee el .env si existe y cae a valores de desarrollo.
 * Nada sensible vive acá: el .env está fuera del repositorio.
 */

/** @return array<string, string> */
function cf_load_env(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $vars[trim($parts[0])] = trim($parts[1], " \t\"'");
        }
    }

    return $vars;
}

$env = cf_load_env(dirname(__DIR__) . '/.env');

$read = static function (string $key, string $default = '') use ($env): string {
    return $env[$key] ?? (getenv($key) !== false ? (string) getenv($key) : $default);
};
$bool = static fn (string $v): bool => in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);

$storage = $read('STORAGE_DIR', dirname(__DIR__) . '/storage/uploads');

return [
    'debug' => $bool($read('APP_DEBUG', 'true')),
    'env' => $read('APP_ENV', 'local'),

    // 32 bytes en base64. Cifra los tokens de Mercado Pago y firma los
    // links de descarga. Generar con: php tools/generar-clave.php
    'app_key' => $read('APP_KEY'),

    'db' => [
        'host' => $read('DB_HOST', '127.0.0.1'),
        'port' => (int) $read('DB_PORT', '3306'),
        'name' => $read('DB_NAME', 'copiafacil'),
        'user' => $read('DB_USER', 'root'),
        'pass' => $read('DB_PASS', ''),
    ],

    'cors_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', $read('CORS_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
    ))),

    // A dónde vuelve el cliente desde Mercado Pago, y la URL pública (https)
    // de esta API para los webhooks. Sin API_PUBLIC_URL no hay webhook y el
    // pago se confirma cuando la pantalla del pedido pregunta.
    'front_url' => rtrim($read('FRONT_URL', 'http://localhost:5173'), '/'),
    'api_public_url' => rtrim($read('API_PUBLIC_URL', ''), '/'),

    // Pago simulado para locales sin Mercado Pago conectado. En producción, false.
    'allow_fake_payments' => $bool($read('ALLOW_FAKE_PAYMENTS', 'true')),

    'storage_dir' => rtrim($storage, '/'),
    'max_upload_mb' => (int) $read('MAX_UPLOAD_MB', '25'),

    // Retención de archivos (ver Política de privacidad):
    // - entregados, rechazados o cancelados: se borran a las N horas;
    // - cualquier archivo, sin excepción, a las FILE_MAX_HOURS.
    'file_retention_hours' => (int) $read('FILE_RETENTION_HOURS', '24'),
    'file_max_hours' => (int) $read('FILE_MAX_HOURS', '72'),

    // «Conectar con Mercado Pago» (OAuth). La aplicación es UNA, de la
    // plataforma: se crea en Mercado Pago Developers → Tus integraciones.
    // Sin client_id/secret el panel sólo ofrece pegar el token a mano.
    'mercadopago_oauth' => [
        'client_id' => $read('MP_CLIENT_ID'),
        'client_secret' => $read('MP_CLIENT_SECRET'),
        // Tiene que ser https y coincidir EXACTO con la cargada en la app.
        'redirect_uri' => $read('MP_REDIRECT_URI', rtrim($read('API_PUBLIC_URL', ''), '/') === '' ? '' : rtrim($read('API_PUBLIC_URL'), '/') . '/oauth/mercadopago/callback'),
        'auth_url' => $read('MP_AUTH_URL', 'https://auth.mercadopago.com/authorization'),
        // PKCE: activarlo también en la configuración de la app de Mercado Pago.
        'pkce' => $bool($read('MP_PKCE', 'true')),
        // true para que el token emitido sea de prueba (usuarios de prueba).
        'test_token' => $bool($read('MP_OAUTH_TEST', 'false')),
    ],

    // A dónde llega el aviso de cada solicitud de demo. Vacío: sólo se guarda.
    'demo_notify_email' => $read('DEMO_NOTIFY_EMAIL', ''),

    // Versión de los términos que acepta el cliente. Cambiarla cuando se
    // modifiquen los textos legales: queda guardada en cada pedido.
    'terms_version' => $read('TERMS_VERSION', '2026-11'),
];
