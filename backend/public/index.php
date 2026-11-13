<?php

declare(strict_types=1);

/**
 * Front controller. Todo entra por acá.
 */

use CopiaFacil\Admin\AdminController;
use CopiaFacil\Admin\Auth;
use CopiaFacil\Demo\DemoController;
use CopiaFacil\Order\FileStorage;
use CopiaFacil\Order\OrderController;
use CopiaFacil\Order\OrderRepository;
use CopiaFacil\Payment\Gateway;
use CopiaFacil\Shop\ShopRepository;
use CopiaFacil\Support\Crypto;
use CopiaFacil\Support\Database;
use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\RateLimit;
use CopiaFacil\Support\Response;
use CopiaFacil\Support\Router;

$root = dirname(__DIR__);
require $root . '/src/bootstrap.php';

/** @var array<string, mixed> $config */
$config = require $root . '/config/config.php';

ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

Response::cors($config['cors_origins']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $router = new Router();
    $orders = static fn () => orderController($config);
    $admin = static fn () => adminController($config);

    $router->get('/health', static fn () => Response::json(['ok' => true, 'servicio' => 'copiafacil-api']));

    /* --- cliente --------------------------------------------------------- */
    $router->get('/t/{slug}', static fn (array $p) => $orders()->shop($p));
    $router->post('/t/{slug}/orders', static fn (array $p) => $orders()->create($p));
    $router->get('/t/{slug}/orders/{publicId}', static fn (array $p) => $orders()->show($p));
    $router->post('/t/{slug}/orders/{publicId}/sincronizar', static fn (array $p) => $orders()->sync($p));
    $router->post('/t/{slug}/orders/{publicId}/pagar', static fn (array $p) => $orders()->pay($p));
    $router->post('/t/{slug}/orders/{publicId}/pago-simulado', static fn (array $p) => $orders()->fakePay($p));
    $router->post('/arrepentimiento', static fn () => $orders()->withdraw());
    $router->post('/demo', static function () use ($config): void {
        $db = Database::connect($config['db']);
        (new DemoController($db, new Crypto($config['app_key']), new RateLimit($db), $config))->request();
    });

    /* --- Mercado Pago ---------------------------------------------------- */
    $router->post('/webhooks/mercadopago/{slug}', static fn (array $p) => $orders()->webhook($p));

    /* --- panel ----------------------------------------------------------- */
    $router->post('/admin/login', static fn () => $admin()->login());
    $router->post('/admin/logout', static fn () => $admin()->logout());
    $router->get('/admin/me', static fn () => $admin()->me());
    $router->post('/admin/password', static fn () => $admin()->password());

    $router->get('/admin/orders', static fn () => $admin()->orders());
    $router->post('/admin/orders/{publicId}/estado', static fn (array $p) => $admin()->setStatus($p));
    $router->post('/admin/orders/{publicId}/rechazar', static fn (array $p) => $admin()->reject($p));
    $router->post('/admin/orders/{publicId}/devolucion', static fn (array $p) => $admin()->refund($p));
    $router->post('/admin/orders/{publicId}/archivo', static fn (array $p) => $admin()->fileLink($p));
    $router->get('/files/{token}', static fn (array $p) => $admin()->serveFile($p));

    $router->get('/admin/settings', static fn () => $admin()->settings());
    $router->put('/admin/settings', static fn () => $admin()->saveSettings());
    $router->post('/admin/mercadopago', static fn () => $admin()->connectMercadoPago());
    $router->delete('/admin/mercadopago', static fn () => $admin()->disconnectMercadoPago());
    $router->post('/admin/mercadopago/oauth', static fn () => $admin()->startMercadoPagoOAuth());
    $router->get('/oauth/mercadopago/callback', static fn () => $admin()->mercadoPagoOAuthCallback());
    $router->post('/admin/abierto', static fn () => $admin()->setOpen());

    [$handler, $params] = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', Router::currentPath());
    $handler($params);
} catch (HttpException $e) {
    Response::error($e->status, $e->getMessage(), $e->errorCode);
} catch (Throwable $e) {
    error_log('[copiafacil] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error(500, $config['debug'] ? $e->getMessage() : 'Algo se rompió de nuestro lado.', 'error_interno');
}

/* Armado de dependencias a mano: son pocas. */

/** @param array<string,mixed> $config */
function orderController(array $config): OrderController
{
    $db = Database::connect($config['db']);
    $crypto = new Crypto($config['app_key']);
    $orders = new OrderRepository($db);

    return new OrderController(
        new ShopRepository($db),
        $orders,
        new FileStorage($config['storage_dir'], $config['max_upload_mb'] * 1048576),
        new Gateway($crypto, $orders),
        $crypto,
        new RateLimit($db),
        $config,
    );
}

/** @param array<string,mixed> $config */
function adminController(array $config): AdminController
{
    $db = Database::connect($config['db']);
    $crypto = new Crypto($config['app_key']);
    $orders = new OrderRepository($db);

    return new AdminController(
        new Auth($db),
        new ShopRepository($db),
        $orders,
        new FileStorage($config['storage_dir'], $config['max_upload_mb'] * 1048576),
        new Gateway($crypto, $orders),
        $crypto,
        new RateLimit($db),
        $config,
    );
}
