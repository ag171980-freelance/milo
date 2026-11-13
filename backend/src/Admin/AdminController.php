<?php

declare(strict_types=1);

namespace CopiaFacil\Admin;

use CopiaFacil\Order\FileStorage;
use CopiaFacil\Order\OrderRepository;
use CopiaFacil\Order\Presenter;
use CopiaFacil\Payment\Gateway;
use CopiaFacil\Payment\MercadoPago;
use CopiaFacil\Payment\MercadoPagoOAuth;
use CopiaFacil\Shop\ShopRepository;
use CopiaFacil\Support\Crypto;
use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\Money;
use CopiaFacil\Support\RateLimit;
use CopiaFacil\Support\Request;
use CopiaFacil\Support\Response;

/**
 * El panel del local. Todo exige sesión salvo el login, y el local sale
 * del usuario, nunca de la URL.
 */
final class AdminController
{
    /** Pasos permitidos: el pedido sólo avanza. */
    private const NEXT = [
        'nuevo' => ['imprimiendo', 'listo'],
        'imprimiendo' => ['listo'],
        'listo' => ['entregado'],
    ];

    private const STATUS_MESSAGES = [
        'imprimiendo' => 'El local empezó a imprimir.',
        'listo' => 'Listo para retirar.',
        'entregado' => 'Entregado.',
    ];

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly Auth $auth,
        private readonly ShopRepository $shops,
        private readonly OrderRepository $orders,
        private readonly FileStorage $files,
        private readonly Gateway $gateway,
        private readonly Crypto $crypto,
        private readonly RateLimit $rateLimit,
        private readonly array $config,
    ) {
    }

    /* --- sesión ---------------------------------------------------------- */

    public function login(): void
    {
        $req = Request::json();
        $email = mb_strtolower($req->str('email', 160));
        $this->rateLimit->hit(
            $this->crypto->hmac('login|' . RateLimit::clientIp()),
            10,
            900,
            'Demasiados intentos. Esperá 15 minutos.',
        );

        Response::json($this->auth->login($email, $req->str('password', 200)));
    }

    public function logout(): void
    {
        $this->auth->logout();
        Response::json(['ok' => true]);
    }

    public function me(): void
    {
        $user = $this->auth->requireUser();
        Response::json(['user' => Auth::publicUser($user)]);
    }

    public function password(): void
    {
        $user = $this->auth->requireUser();
        $req = Request::json();
        $this->auth->changePassword((int) $user['id'], $req->str('actual', 200), $req->str('nueva', 200));
        Response::json(['ok' => true]);
    }

    /* --- pedidos --------------------------------------------------------- */

    /** GET /admin/orders?scope=activos|historial */
    public function orders(): void
    {
        $user = $this->auth->requireUser();
        $tenantId = (int) $user['tenant_id'];
        $shop = $this->shops->byId($tenantId);

        // Mantenimiento barato en cada consulta del panel, por si no hay cron.
        $this->maintenance();

        $scope = ($_GET['scope'] ?? '') === 'historial' ? 'historial' : 'activos';
        $list = $this->orders->listForAdmin($tenantId, $scope);

        Response::json([
            'orders' => array_map(fn (array $o) => Presenter::adminOrder($o, $shop, $this->orders->events((int) $o['id'])), $list),
            'stats' => $this->orders->todayStats($tenantId),
            'shop' => [
                'isOpen' => (bool) $shop['is_open'],
                'paymentsConnected' => ShopRepository::mpConnected($shop),
                'trialEndsAt' => $shop['trial_ends_at'] === null ? null : (new \DateTimeImmutable((string) $shop['trial_ends_at']))->format(DATE_ATOM),
                'trialEnded' => Presenter::trialEnded($shop),
            ],
        ]);
    }

    /** POST /admin/orders/{publicId}/estado {estado} */
    public function setStatus(array $p): void
    {
        [$order, $shop] = $this->ownOrder($p['publicId']);
        $next = Request::json()->str('estado', 20);

        if (!in_array($next, self::NEXT[$order['status']] ?? [], true)) {
            throw new HttpException(409, 'Ese cambio de estado no se puede hacer ahora.', 'estado_invalido');
        }

        $this->orders->setStatus((int) $order['id'], $next, self::STATUS_MESSAGES[$next], 'local');
        $this->respondOrder($order['public_id'], $shop);
    }

    /** POST /admin/orders/{publicId}/rechazar {motivo} — rechaza y devuelve la plata */
    public function reject(array $p): void
    {
        [$order, $shop] = $this->ownOrder($p['publicId']);
        if (!in_array($order['status'], ['nuevo', 'imprimiendo'], true)) {
            throw new HttpException(409, 'Este pedido ya no se puede rechazar.', 'estado_invalido');
        }

        $reason = Request::json()->str('motivo', 200);
        $this->orders->setRejectReason((int) $order['id'], $reason);
        $this->orders->setStatus((int) $order['id'], 'rechazado', 'Rechazado por el local' . ($reason !== '' ? ": $reason" : '.'), 'local');
        $this->gateway->payments($shop)->refund($order, 'local');

        $this->respondOrder($order['public_id'], $shop);
    }

    /** POST /admin/orders/{publicId}/devolucion {modo: reintentar|efectivo} */
    public function refund(array $p): void
    {
        [$order, $shop] = $this->ownOrder($p['publicId']);
        if ($order['payment_status'] !== 'devolucion_pendiente') {
            throw new HttpException(409, 'Este pedido no tiene una devolución pendiente.', 'sin_devolucion');
        }

        $mode = Request::json()->enum('modo', ['reintentar', 'efectivo'], '');
        if ($mode === 'efectivo') {
            $this->orders->addRefund((int) $order['id'], 'efectivo', null, Money::toCents($order['total']));
            $this->orders->setPaymentStatus((int) $order['id'], 'devuelto_efectivo');
            $this->orders->addEvent((int) $order['id'], (string) $order['status'], 'El local devolvió la plata en efectivo.', 'local');
        } elseif ($mode === 'reintentar') {
            $this->gateway->payments($shop)->refund($order, 'local');
        } else {
            throw HttpException::badRequest('Elegí cómo resolver la devolución.', 'modo_invalido');
        }

        $this->respondOrder($order['public_id'], $shop);
    }

    /** POST /admin/orders/{publicId}/archivo — link firmado de 5 minutos para abrir e imprimir */
    public function fileLink(array $p): void
    {
        [$order] = $this->ownOrder($p['publicId']);
        if ($order['file_path'] === null) {
            throw HttpException::notFound('El archivo ya se borró (se eliminan automáticamente).', 'archivo_borrado');
        }

        $token = $this->crypto->sign(['o' => (int) $order['id'], 't' => (int) $order['tenant_id']], 300);
        Response::json(['url' => '/files/' . $token]);
    }

    /** GET /files/{token} — sirve el archivo si la firma es válida */
    public function serveFile(array $p): void
    {
        $claims = $this->crypto->verify($p['token']);
        if ($claims === null) {
            throw HttpException::forbidden('El link venció. Volvé a abrir el archivo desde el panel.', 'link_vencido');
        }

        $row = $this->orders->findById((int) $claims['t'], (int) $claims['o']);
        $abs = $row !== null && $row['file_path'] !== null ? $this->files->absolutePath((string) $row['file_path']) : null;
        if ($abs === null) {
            throw HttpException::notFound('El archivo ya no está disponible.', 'archivo_borrado');
        }

        $inline = in_array($row['file_mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        $name = (string) $row['file_name'];

        header('Content-Type: ' . $row['file_mime']);
        header('Content-Length: ' . filesize($abs));
        header("Content-Disposition: " . ($inline ? 'inline' : 'attachment') . "; filename=\"" . addcslashes(preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'archivo', '"\\') . "\"; filename*=UTF-8''" . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        readfile($abs);
    }

    /* --- configuración --------------------------------------------------- */

    public function settings(): void
    {
        $user = $this->auth->requireUser();
        Response::json(['settings' => $this->settingsView($this->shops->byId((int) $user['tenant_id']))]);
    }

    public function saveSettings(): void
    {
        $user = $this->auth->requireUser();
        $tenantId = (int) $user['tenant_id'];
        $req = Request::json();
        $fields = [];
        $errors = [];

        if ($req->has('name')) {
            $name = $req->str('name', 120);
            mb_strlen($name) >= 2 ? $fields['name'] = $name : $errors['name'] = 'Poné el nombre del local.';
        }
        if ($req->has('address')) {
            $fields['address'] = $req->str('address', 200);
        }
        if ($req->has('isOpen')) {
            $fields['is_open'] = $req->bool('isOpen') ? 1 : 0;
        }
        foreach (['legalName' => ['legal_name', 160], 'contactEmail' => ['contact_email', 160], 'contactPhone' => ['contact_phone', 40]] as $k => [$col, $len]) {
            if ($req->has($k)) {
                $fields[$col] = $req->str($k, $len);
            }
        }
        if (isset($fields['contact_email']) && $fields['contact_email'] !== '' && !filter_var($fields['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contactEmail'] = 'Ese mail no parece válido.';
        }
        if ($req->has('cuit')) {
            $cuit = preg_replace('/\D/', '', $req->str('cuit', 20)) ?? '';
            if ($cuit !== '' && !self::validCuit($cuit)) {
                $errors['cuit'] = 'El CUIT no es válido.';
            } else {
                $fields['cuit'] = $cuit === '' ? '' : substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10);
            }
        }

        $prices = $req->obj('prices');
        foreach (ShopRepository::PRICE_COLUMNS as $key => $col) {
            if (!array_key_exists($key, $prices)) {
                continue;
            }
            $v = $prices[$key];
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 10_000_000) {
                $errors['prices.' . $key] = 'Precio inválido.';
                continue;
            }
            // El front manda centavos.
            $fields[$col] = Money::toDecimal((int) round((float) $v));
        }

        $offers = $req->obj('offers');
        foreach (['color' => 'offers_color', 'oficio' => 'offers_oficio', 'anillado' => 'offers_anillado'] as $k => $col) {
            if (array_key_exists($k, $offers)) {
                $fields[$col] = $offers[$k] ? 1 : 0;
            }
        }

        if ($errors !== []) {
            Response::error(422, 'Revisá los datos.', 'datos_invalidos', ['campos' => $errors]);
            return;
        }

        $this->shops->updateSettings($tenantId, $fields);
        Response::json(['settings' => $this->settingsView($this->shops->byId($tenantId))]);
    }

    /** POST /admin/mercadopago {accessToken} — valida el token contra la API y lo guarda cifrado */
    public function connectMercadoPago(): void
    {
        $user = $this->auth->requireUser();
        $tenantId = (int) $user['tenant_id'];
        $token = Request::json()->str('accessToken', 300);

        if (!MercadoPago::looksLikeToken($token)) {
            Response::error(422, 'Eso no parece un access token de Mercado Pago. Empieza con APP_USR- (producción) o TEST- (prueba).', 'token_invalido', [
                'campos' => ['accessToken' => 'Formato inválido.'],
            ]);
            return;
        }

        $mp = new MercadoPago($token);
        try {
            $account = $mp->account();
        } catch (HttpException $e) {
            Response::error(422, $e->errorCode === 'mp_token_invalido'
                ? 'Mercado Pago no reconoce ese token. Copialo de nuevo desde "Tus integraciones".'
                : $e->getMessage(), 'token_invalido', ['campos' => ['accessToken' => 'No se pudo validar.']]);
            return;
        }

        $this->shops->saveMercadoPago(
            $tenantId,
            $this->crypto->encrypt($token),
            (string) ($account['id'] ?? ''),
            (string) ($account['nickname'] ?? $account['email'] ?? ''),
            $mp->isTest(),
            'manual',
        );

        Response::json(['settings' => $this->settingsView($this->shops->byId($tenantId))]);
    }

    /**
     * POST /admin/mercadopago/oauth — arma el link para «Conectar con Mercado Pago».
     *
     * El `state` va cifrado con APP_KEY y lleva el local, el usuario, el
     * verificador PKCE y un vencimiento de 10 minutos. Así la vuelta no puede
     * conectar una cuenta en el local de otro ni reutilizarse más tarde.
     */
    public function startMercadoPagoOAuth(): void
    {
        $user = $this->auth->requireUser();
        $oauthConfig = $this->config['mercadopago_oauth'];
        if (!MercadoPagoOAuth::configured($oauthConfig)) {
            throw new HttpException(409, 'La conexión con un clic todavía no está configurada. Pegá el access token a mano.', 'oauth_no_configurado');
        }

        $pkce = $oauthConfig['pkce'] ? MercadoPagoOAuth::pkcePair() : null;
        $state = rtrim(strtr($this->crypto->encrypt(json_encode([
            't' => (int) $user['tenant_id'],
            'u' => (int) $user['id'],
            'v' => $pkce['verifier'] ?? null,
            'exp' => time() + 600,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        Response::json(['url' => (new MercadoPagoOAuth($oauthConfig))->authorizationUrl($state, $pkce['challenge'] ?? null)]);
    }

    /**
     * GET /oauth/mercadopago/callback?code=…&state=… — la vuelta desde Mercado Pago.
     *
     * Es una redirección del navegador (sin el token del panel): quién es el
     * local lo dice el `state` cifrado. Siempre termina redirigiendo al panel,
     * con ?mp=conectado o ?mp=error&detalle=….
     */
    public function mercadoPagoOAuthCallback(): void
    {
        $back = $this->config['front_url'] . '/admin/configuracion';
        $fail = static function (string $detail) use ($back): void {
            header('Location: ' . $back . '?mp=error&detalle=' . rawurlencode($detail), true, 302);
        };

        if (isset($_GET['error'])) {
            $fail('No autorizaste la conexión en Mercado Pago.');
            return;
        }

        $rawState = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');
        try {
            $padded = strtr($rawState, '-_', '+/');
            $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
            $state = json_decode($this->crypto->decrypt($padded), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $state = null;
        }
        if (!is_array($state) || (int) ($state['exp'] ?? 0) < time() || $code === '') {
            $fail('El link de conexión venció. Probá de nuevo desde el panel.');
            return;
        }

        $tenantId = (int) $state['t'];
        try {
            $tokens = (new MercadoPagoOAuth($this->config['mercadopago_oauth']))->exchangeCode($code, $state['v'] ?? null);
            $mp = new MercadoPago($tokens['access_token']);
            $account = $mp->account();
        } catch (HttpException $e) {
            error_log('[copiafacil] OAuth MP local ' . $tenantId . ': ' . $e->getMessage());
            $fail($e->getMessage());
            return;
        }

        $this->shops->saveMercadoPago(
            $tenantId,
            $this->crypto->encrypt($tokens['access_token']),
            (string) ($account['id'] ?? $tokens['user_id']),
            (string) ($account['nickname'] ?? $account['email'] ?? ''),
            $mp->isTest() || (bool) $this->config['mercadopago_oauth']['test_token'],
            'oauth',
            $tokens['refresh_token'] === null ? null : $this->crypto->encrypt($tokens['refresh_token']),
            $tokens['expires_in'],
        );

        header('Location: ' . $back . '?mp=conectado', true, 302);
    }

    public function disconnectMercadoPago(): void
    {
        $user = $this->auth->requireUser();
        $this->shops->clearMercadoPago((int) $user['tenant_id']);
        Response::json(['settings' => $this->settingsView($this->shops->byId((int) $user['tenant_id']))]);
    }

    /** POST /admin/abierto {abierto} — atajo para abrir/cerrar desde la pantalla de pedidos */
    public function setOpen(): void
    {
        $user = $this->auth->requireUser();
        $this->shops->updateSettings((int) $user['tenant_id'], ['is_open' => Request::json()->bool('abierto') ? 1 : 0]);
        Response::json(['ok' => true]);
    }

    /* --- internos -------------------------------------------------------- */

    /** Borra archivos vencidos y cancela pedidos que nunca se pagaron. */
    public function maintenance(): void
    {
        foreach ($this->orders->filesToPurge($this->config['file_retention_hours'], $this->config['file_max_hours']) as $row) {
            $this->files->delete((string) $row['file_path']);
            $this->orders->markFileDeleted((int) $row['id']);
        }
        $this->orders->cancelUnpaid(2);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function ownOrder(string $publicId): array
    {
        $user = $this->auth->requireUser();
        $tenantId = (int) $user['tenant_id'];

        return [$this->orders->find($tenantId, $publicId), $this->shops->byId($tenantId)];
    }

    private function respondOrder(string $publicId, array $shop): void
    {
        $order = $this->orders->find((int) $shop['id'], $publicId);
        Response::json(['order' => Presenter::adminOrder($order, $shop, $this->orders->events((int) $order['id']))]);
    }

    /** @param array<string,mixed> $shop */
    private function settingsView(array $shop): array
    {
        return [
            'slug' => $shop['slug'],
            'name' => $shop['name'],
            'address' => $shop['address'],
            'isOpen' => (bool) $shop['is_open'],
            'prices' => ShopRepository::pricesInCents($shop),
            'offers' => [
                'color' => (bool) $shop['offers_color'],
                'oficio' => (bool) $shop['offers_oficio'],
                'anillado' => (bool) $shop['offers_anillado'],
            ],
            'legalName' => $shop['legal_name'],
            'cuit' => $shop['cuit'],
            'contactEmail' => $shop['contact_email'],
            'contactPhone' => $shop['contact_phone'],
            'mercadoPago' => [
                'connected' => ShopRepository::mpConnected($shop),
                'expired' => $shop['mp_access_token_enc'] !== null && !ShopRepository::mpConnected($shop),
                'via' => $shop['mp_connected_via'],
                'expiresAt' => $shop['mp_token_expires_at'],
                'oauthAvailable' => MercadoPagoOAuth::configured($this->config['mercadopago_oauth']),
                'nickname' => $shop['mp_nickname'],
                'isTest' => (bool) $shop['mp_is_test'],
                'connectedAt' => $shop['mp_connected_at'],
                'webhookUrl' => $this->config['api_public_url'] === '' ? null : $this->config['api_public_url'] . '/webhooks/mercadopago/' . $shop['slug'],
            ],
            'shopUrl' => $this->config['front_url'] . '/' . $shop['slug'],
        ];
    }

    /** Dígito verificador del CUIT/CUIL. */
    private static function validCuit(string $cuit): bool
    {
        if (preg_match('/^\d{11}$/', $cuit) !== 1) {
            return false;
        }
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        foreach ($weights as $i => $w) {
            $sum += (int) $cuit[$i] * $w;
        }
        $check = 11 - ($sum % 11);
        $check = $check === 11 ? 0 : ($check === 10 ? 9 : $check);

        return $check === (int) $cuit[10];
    }
}
