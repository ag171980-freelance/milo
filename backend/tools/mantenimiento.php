<?php

declare(strict_types=1);

// Borra los archivos vencidos y cancela pedidos que nunca se pagaron.
//
// Correrlo por cron cada 15 minutos:
//   */15 * * * * php /ruta/backend/tools/mantenimiento.php
//
// El panel también lo hace cada vez que se abre, pero si un local no entra
// en todo el día, sin cron sus archivos quedarían más de lo prometido en la
// Política de privacidad.

use CopiaFacil\Order\FileStorage;
use CopiaFacil\Order\OrderRepository;
use CopiaFacil\Payment\MercadoPagoOAuth;
use CopiaFacil\Payment\TokenRefresher;
use CopiaFacil\Support\Crypto;
use CopiaFacil\Support\Database;

require __DIR__ . '/../src/bootstrap.php';
$config = require __DIR__ . '/../config/config.php';

$db = Database::connect($config['db']);
$orders = new OrderRepository($db);
$files = new FileStorage($config['storage_dir'], $config['max_upload_mb'] * 1048576);

$deleted = 0;
foreach ($orders->filesToPurge($config['file_retention_hours'], $config['file_max_hours']) as $row) {
    $files->delete((string) $row['file_path']);
    $orders->markFileDeleted((int) $row['id']);
    $deleted++;
}
$cancelled = $orders->cancelUnpaid(2);

// Solicitudes de demo que no se activaron: se borran a los 6 meses
// (lo promete la Política de privacidad).
$db->exec("DELETE FROM demo_requests WHERE status <> 'activada' AND created_at < NOW() - INTERVAL 6 MONTH");

// Tokens de Mercado Pago conectados por OAuth: se renuevan 30 días antes de vencer.
$tokens = ['renewed' => 0, 'failed' => 0];
if (MercadoPagoOAuth::configured($config['mercadopago_oauth'])) {
    $tokens = (new TokenRefresher($db, new Crypto($config['app_key']), new MercadoPagoOAuth($config['mercadopago_oauth'])))->refreshExpiring();
}

// Ventanas de rate limit viejas.
$db->exec('DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 DAY');

echo date('c') . " archivos borrados: $deleted · pedidos sin pagar cancelados: $cancelled"
    . " · tokens MP renovados: {$tokens['renewed']}" . ($tokens['failed'] ? " (fallaron {$tokens['failed']})" : '') . "\n";
