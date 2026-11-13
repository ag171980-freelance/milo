<?php

declare(strict_types=1);

namespace CopiaFacil\Order;

use CopiaFacil\Payment\Gateway;
use CopiaFacil\Shop\ShopRepository;
use CopiaFacil\Support\Crypto;
use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\Money;
use CopiaFacil\Support\RateLimit;
use CopiaFacil\Support\Request;
use CopiaFacil\Support\Response;

/**
 * Lo que usa el cliente: ver el local, pedir, pagar y seguir el pedido.
 */
final class OrderController
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly ShopRepository $shops,
        private readonly OrderRepository $orders,
        private readonly FileStorage $files,
        private readonly Gateway $gateway,
        private readonly Crypto $crypto,
        private readonly RateLimit $rateLimit,
        private readonly array $config,
    ) {
    }

    /** GET /t/{slug} */
    public function shop(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        Response::json(['shop' => $this->shopView($shop)]);
    }

    /** POST /t/{slug}/orders (multipart: file + campos) */
    public function create(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        $tenantId = (int) $shop['id'];

        if (Presenter::trialEnded($shop)) {
            throw new HttpException(409, 'Este local no está tomando pedidos online por ahora.', 'prueba_vencida');
        }
        if (!$shop['is_open']) {
            throw new HttpException(409, 'El local está cerrado ahora. Probá más tarde.', 'local_cerrado');
        }
        $mp = $this->gateway->mercadoPago($shop);
        if ($mp === null && !$this->config['allow_fake_payments']) {
            throw new HttpException(409, 'Este local todavía no habilitó el pago online.', 'sin_pagos');
        }

        $ip = RateLimit::clientIp();
        $this->rateLimit->hit(
            $this->crypto->hmac('order|' . $tenantId . '|' . $ip),
            15,
            600,
            'Hiciste muchos pedidos seguidos. Esperá unos minutos.',
        );

        $req = Request::form();
        $errors = [];

        $name = $req->str('name', 60);
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Poné tu nombre.';
        }
        if (!$req->bool('acceptTerms')) {
            $errors['acceptTerms'] = 'Tenés que aceptar los términos y la política de privacidad.';
        }

        $copies = $req->int('copies', 1, 50, 1);
        $color = $req->enum('color', ['bn', 'color'], 'bn');
        $sides = $req->enum('sides', ['simple', 'doble'], 'doble');
        $paper = $req->enum('paper', ['A4', 'Oficio'], 'A4');
        $finish = $req->enum('finish', ['ninguna', 'abrochado', 'anillado'], 'ninguna');
        $note = $req->str('note', 200);
        $declaredPages = $req->int('pages', 1, 2000, 1);

        if ($color === 'color' && !$shop['offers_color']) {
            $errors['color'] = 'Este local no imprime en color.';
        }
        if ($paper === 'Oficio' && !$shop['offers_oficio']) {
            $errors['paper'] = 'Este local no imprime en oficio.';
        }
        if ($finish === 'anillado' && !$shop['offers_anillado']) {
            $errors['finish'] = 'Este local no hace anillados.';
        }

        if ($errors !== []) {
            Response::error(422, 'Revisá los datos del pedido.', 'datos_invalidos', ['campos' => $errors]);
            return;
        }

        $file = $this->files->store($_FILES['file'] ?? null, $tenantId);
        $pages = $file['pages'] ?? $declaredPages;
        if ($pages > 2000) {
            $this->files->delete($file['path']);
            throw new HttpException(422, 'El archivo tiene demasiadas páginas para un pedido online.', 'muchas_paginas');
        }

        $q = Pricing::quote(ShopRepository::pricesInCents($shop), $pages, $copies, $color, $sides, $finish);

        try {
            $order = $this->orders->create($tenantId, [
                'customer_name' => $name,
                'file_name' => $file['name'],
                'file_path' => $file['path'],
                'file_mime' => $file['mime'],
                'file_size' => $file['size'],
                'pages' => $pages,
                'pages_counted' => $file['pages'] === null ? 0 : 1,
                'copies' => $copies,
                'color' => $color,
                'sides' => $q['doble'] ? 'doble' : 'simple',
                'paper' => $paper,
                'finish' => $finish,
                'note' => $note,
                'unit_price' => Money::toDecimal($q['unit']),
                'print_total' => Money::toDecimal($q['print']),
                'finish_total' => Money::toDecimal($q['finish']),
                'total' => Money::toDecimal($q['total']),
                'currency' => 'ARS',
                'terms_version' => $this->config['terms_version'],
                'terms_accepted_at' => date('Y-m-d H:i:s'),
                'ip_hash' => $this->crypto->hmac('ip|' . $ip),
            ]);
        } catch (\Throwable $e) {
            $this->files->delete($file['path']);
            throw $e;
        }

        Response::json($this->checkout($order, $shop), 201);
    }

    /** GET /t/{slug}/orders/{publicId} */
    public function show(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        $order = $this->orders->find((int) $shop['id'], $p['publicId']);
        Response::json(['order' => Presenter::order($order, $shop)]);
    }

    /** POST /t/{slug}/orders/{publicId}/sincronizar — le pregunta a Mercado Pago por el pago */
    public function sync(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        $order = $this->orders->find((int) $shop['id'], $p['publicId']);

        if ($order['status'] === 'pendiente_pago') {
            $this->gateway->payments($shop)->sync((int) $shop['id'], $order['public_id']);
            $order = $this->orders->find((int) $shop['id'], $p['publicId']);
        }

        Response::json(['order' => Presenter::order($order, $shop)]);
    }

    /** POST /t/{slug}/orders/{publicId}/pagar — un link de pago nuevo para un pedido sin pagar */
    public function pay(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        $order = $this->orders->find((int) $shop['id'], $p['publicId']);

        if ($order['status'] !== 'pendiente_pago') {
            throw new HttpException(409, 'Este pedido ya no se puede pagar.', 'no_pagable');
        }

        Response::json($this->checkout($order, $shop));
    }

    /** POST /t/{slug}/orders/{publicId}/pago-simulado — sólo sin Mercado Pago y con ALLOW_FAKE_PAYMENTS */
    public function fakePay(array $p): void
    {
        $shop = $this->shops->bySlug($p['slug']);
        if (!$this->config['allow_fake_payments'] || $this->gateway->mercadoPago($shop) !== null) {
            throw HttpException::forbidden('El pago simulado está desactivado.', 'simulador_apagado');
        }

        $order = $this->orders->find((int) $shop['id'], $p['publicId']);
        if ($order['status'] !== 'pendiente_pago') {
            throw new HttpException(409, 'Este pedido ya no se puede pagar.', 'no_pagable');
        }

        $this->orders->settlePayment($order, true, 'simulado', 'sim-' . $order['public_id'], Money::toCents($order['total']), null);
        $order = $this->orders->find((int) $shop['id'], $p['publicId']);
        Response::json(['order' => Presenter::order($order, $shop)]);
    }

    /**
     * POST /webhooks/mercadopago/{slug}
     *
     * Cada local tiene su propia cuenta (y su propia clave de webhook, que no
     * conocemos), así que no se valida firma: el aviso sólo dispara una
     * consulta a la API con el token del local, y lo que vale es lo que
     * responde la API. Un aviso falso no puede aprobar nada.
     */
    public function webhook(array $p): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        $body = is_array($body) ? $body : [];

        $type = (string) ($body['type'] ?? $body['topic'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
        $id = (string) ($body['data']['id'] ?? $_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '');

        if ($type !== 'payment' || preg_match('/^\d{1,20}$/', $id) !== 1) {
            Response::json(['ok' => true, 'ignorado' => true]);
            return;
        }

        try {
            $shop = $this->shops->bySlug($p['slug']);
        } catch (HttpException) {
            Response::json(['ok' => true, 'ignorado' => true]);
            return;
        }

        // Si Mercado Pago falla acá, sale 5xx y el aviso se reintenta.
        $this->gateway->payments($shop)->applyById((int) $shop['id'], $id);
        Response::json(['ok' => true]);
    }

    /**
     * POST /arrepentimiento — Botón de arrepentimiento (Res. SCI 424/2020).
     *
     * Si el pedido todavía no se empezó a imprimir, se cancela y se devuelve
     * la plata en el acto. Si ya se imprimió, la solicitud queda registrada
     * igual (con su código) y el local la ve en el historial del pedido.
     */
    public function withdraw(): void
    {
        $this->rateLimit->hit(
            $this->crypto->hmac('withdraw|' . RateLimit::clientIp()),
            10,
            3600,
            'Hiciste muchas solicitudes seguidas. Probá en un rato.',
        );

        $req = Request::json();
        $raw = $req->str('pedido', 300);
        $contact = $req->str('contacto', 160);
        $detail = $req->str('detalle', 500);

        // Acepta el link completo del pedido o sólo el código.
        $publicId = preg_match('/([a-z0-9]{20})\/?$/', strtolower($raw), $m) === 1 ? $m[1] : '';
        $order = $publicId !== '' ? $this->orders->findAnyTenant($publicId) : null;

        if ($order === null && $contact === '') {
            Response::error(422, 'No encontramos el pedido. Dejanos un mail o teléfono para contactarte.', 'datos_invalidos', [
                'campos' => ['contacto' => 'Necesitamos un contacto si no tenés el link del pedido.'],
            ]);
            return;
        }

        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));

        if ($order === null) {
            $this->orders->addWithdrawal($code, null, null, $contact, $detail . ($raw !== '' ? ' · Dato del pedido: ' . $raw : ''), 'registrado');
            Response::json([
                'codigo' => $code,
                'resultado' => 'registrado',
                'mensaje' => 'Registramos tu solicitud. Te vamos a contactar para resolverla.',
            ]);
            return;
        }

        $tenantId = (int) $order['tenant_id'];
        $shop = $this->shops->byId($tenantId);
        $orderId = (int) $order['id'];

        if (in_array($order['status'], ['pendiente_pago', 'nuevo'], true)) {
            $wasPaid = $order['status'] === 'nuevo';
            $this->orders->setStatus($orderId, 'cancelado', "Cancelado por el cliente con el botón de arrepentimiento ($code).", 'cliente');
            $refunded = $wasPaid ? $this->gateway->payments($shop)->refund($order, 'cliente') : true;
            $this->orders->addWithdrawal($code, $orderId, $tenantId, $contact, $detail, 'cancelado_y_devuelto');

            Response::json([
                'codigo' => $code,
                'resultado' => 'cancelado',
                'mensaje' => !$wasPaid
                    ? 'Cancelamos tu pedido. No se te cobró nada.'
                    : ($refunded
                        ? 'Cancelamos tu pedido y te devolvimos la plata por Mercado Pago.'
                        : 'Cancelamos tu pedido. La devolución quedó pendiente: el local te la va a hacer a la brevedad.'),
            ]);
            return;
        }

        $this->orders->addEvent($orderId, (string) $order['status'], "El cliente pidió arrepentirse ($code). El pedido ya estaba en estado: {$order['status']}.", 'cliente');
        $this->orders->addWithdrawal($code, $orderId, $tenantId, $contact, $detail, 'registrado');

        Response::json([
            'codigo' => $code,
            'resultado' => 'registrado',
            'mensaje' => in_array($order['status'], ['rechazado', 'cancelado'], true)
                ? 'Este pedido ya estaba cancelado. Si se cobró, la devolución ya se inició.'
                : 'Tu pedido ya se empezó a imprimir. Por ser un trabajo hecho con tu archivo y tus indicaciones, la cancelación no corre automáticamente, pero tu solicitud quedó registrada y el local la va a revisar.',
        ]);
    }

    /**
     * Link de pago, o pago simulado si el local no conectó Mercado Pago.
     *
     * @param array<string,mixed> $order
     * @param array<string,mixed> $shop
     * @return array<string,mixed>
     */
    private function checkout(array $order, array $shop): array
    {
        $mp = $this->gateway->mercadoPago($shop);
        $view = Presenter::order($order, $shop);

        if ($mp === null) {
            return ['order' => $view, 'checkoutUrl' => null, 'simulated' => true];
        }

        $return = $this->config['front_url'] . '/' . $shop['slug'] . '/pedido/' . $order['public_id'];
        $notify = $this->config['api_public_url'] === ''
            ? null
            : $this->config['api_public_url'] . '/webhooks/mercadopago/' . $shop['slug'];

        $pref = $mp->createPreference($order, (string) $shop['name'], $return, $notify);

        return ['order' => $view, 'checkoutUrl' => $pref['url'], 'simulated' => false];
    }

    /** @param array<string,mixed> $shop */
    private function shopView(array $shop): array
    {
        $mpConnected = ShopRepository::mpConnected($shop);

        return Presenter::shop($shop, $mpConnected, (bool) $this->config['allow_fake_payments'], (int) $this->config['max_upload_mb']);
    }
}
