<?php

declare(strict_types=1);

namespace CopiaFacil\Payment;

use CopiaFacil\Order\OrderRepository;
use CopiaFacil\Shop\ShopRepository;
use CopiaFacil\Support\Crypto;

/**
 * Arma el cliente de Mercado Pago de un local a partir de su token cifrado.
 */
final class Gateway
{
    public function __construct(private readonly Crypto $crypto, private readonly OrderRepository $orders)
    {
    }

    /** @param array<string,mixed> $shop */
    public function mercadoPago(array $shop): ?MercadoPago
    {
        // Un token vencido no sirve: el local tiene que volver a conectar.
        if (!ShopRepository::mpConnected($shop)) {
            return null;
        }

        return new MercadoPago($this->crypto->decrypt((string) $shop['mp_access_token_enc']));
    }

    /** @param array<string,mixed> $shop */
    public function payments(array $shop): Payments
    {
        return new Payments($this->orders, $this->mercadoPago($shop));
    }
}
