<?php

declare(strict_types=1);

namespace CopiaFacil\Payment;

use CopiaFacil\Support\HttpException;

/**
 * Lo que hace falta de la API de Mercado Pago, sin SDK.
 *
 * Cada local conecta SU cuenta: el access token se carga desde el panel,
 * se guarda cifrado y se usa sólo para los pedidos de ese local. La plata
 * va directo a la cuenta del local; Copiá Fácil no la toca.
 *
 * Checkout Pro: se crea una preferencia con el total, el cliente paga en
 * Mercado Pago y vuelve. Lo que vuelve en la URL no se cree nunca: el
 * estado del pago se le pregunta a la API.
 */
final class MercadoPago
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(private readonly string $accessToken)
    {
    }

    public function isTest(): bool
    {
        return str_starts_with($this->accessToken, 'TEST-');
    }

    /** Formato básico de un access token, antes de llamar a la API. */
    public static function looksLikeToken(string $token): bool
    {
        return preg_match('/^(APP_USR|TEST)-[A-Za-z0-9-]{20,200}$/', $token) === 1;
    }

    /**
     * @param array<string,mixed> $order
     * @return array{id:string, url:string}
     */
    public function createPreference(array $order, string $shopName, string $returnUrl, ?string $notificationUrl): array
    {
        $publicId = (string) $order['public_id'];

        $body = [
            'items' => [[
                'id' => $publicId,
                'title' => sprintf('Impresión #%s · %s', $order['number'], $shopName),
                'description' => mb_substr(sprintf('%s · %d pág. × %d', $order['file_name'], $order['pages'], $order['copies']), 0, 250),
                'quantity' => 1,
                'currency_id' => (string) $order['currency'],
                'unit_price' => round((float) $order['total'], 2),
            ]],
            'payer' => ['name' => (string) $order['customer_name']],
            'external_reference' => $publicId,
            'back_urls' => ['success' => $returnUrl, 'failure' => $returnUrl, 'pending' => $returnUrl],
            'statement_descriptor' => mb_substr(strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', $shopName) ?? ''), 0, 22),
            'metadata' => ['pedido' => $publicId],
            // Sin pagos en efectivo (Rapipago/Pago Fácil): tardan días en
            // acreditarse y el cliente está esperando en el mostrador.
            'payment_methods' => ['excluded_payment_types' => [['id' => 'ticket'], ['id' => 'atm']]],
            // El link vence a la hora: un pedido viejo no se cobra con precios viejos.
            'expires' => true,
            'expiration_date_to' => (new \DateTimeImmutable('+1 hour'))->format('Y-m-d\TH:i:s.vP'),
        ];

        // Mercado Pago sólo vuelve solo a una dirección https.
        if (str_starts_with($returnUrl, 'https://')) {
            $body['auto_return'] = 'approved';
        }
        // Y sólo avisa a una dirección pública.
        if ($notificationUrl !== null && str_starts_with($notificationUrl, 'https://')) {
            $body['notification_url'] = $notificationUrl;
        }

        $res = $this->request('POST', '/checkout/preferences', $body, 'pref-' . $publicId . '-' . bin2hex(random_bytes(4)));

        $url = $this->isTest() ? ($res['sandbox_init_point'] ?? $res['init_point'] ?? null) : ($res['init_point'] ?? null);
        if (!is_string($url) || $url === '') {
            throw new HttpException(502, 'Mercado Pago no devolvió el link de pago.', 'mp_sin_link');
        }

        return ['id' => (string) $res['id'], 'url' => $url];
    }

    /** @return array<string,mixed> */
    public function payment(string $id): array
    {
        if (preg_match('/^\d{1,20}$/', $id) !== 1) {
            throw HttpException::badRequest('Identificador de pago inválido.', 'mp_pago_invalido');
        }

        return $this->request('GET', '/v1/payments/' . $id);
    }

    /** @return list<array<string,mixed>> los pagos de un pedido, del más nuevo al más viejo */
    public function paymentsFor(string $externalReference): array
    {
        $res = $this->request('GET', '/v1/payments/search?' . http_build_query([
            'external_reference' => $externalReference,
            'sort' => 'date_created',
            'criteria' => 'desc',
            'limit' => 20,
        ]));

        return array_values($res['results'] ?? []);
    }

    /**
     * Devolución total de un pago. La clave de idempotencia evita que un
     * doble clic o un reintento devuelvan dos veces.
     *
     * @return array<string,mixed>
     */
    public function refund(string $paymentId, string $idempotencyKey): array
    {
        if (preg_match('/^\d{1,20}$/', $paymentId) !== 1) {
            throw HttpException::badRequest('Identificador de pago inválido.', 'mp_pago_invalido');
        }

        return $this->request('POST', '/v1/payments/' . $paymentId . '/refunds', [], $idempotencyKey);
    }

    /** @return array<string,mixed> la cuenta dueña del token: sirve para validarlo */
    public function account(): array
    {
        return $this->request('GET', '/users/me');
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $ch = curl_init(self::API . $path);
        $headers = ['Authorization: Bearer ' . $this->accessToken, 'Accept: application/json'];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode((object) $body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $netError = curl_error($ch);

        if ($raw === false) {
            throw new HttpException(502, 'No pudimos conectarnos con Mercado Pago. Probá de nuevo en un rato.', 'mp_sin_conexion');
        }

        $data = json_decode((string) $raw, true);

        if ($status === 401 || $status === 403) {
            throw new HttpException(502, 'Mercado Pago rechazó el access token del local.', 'mp_token_invalido');
        }
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $msg = is_array($data) ? (string) ($data['message'] ?? $data['error'] ?? 'error') : $netError;
            throw new HttpException(502, 'Mercado Pago respondió con un error: ' . $msg, 'mp_error');
        }

        return $data;
    }
}
