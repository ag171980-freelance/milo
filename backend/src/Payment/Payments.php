<?php

declare(strict_types=1);

namespace CopiaFacil\Payment;

use CopiaFacil\Order\OrderRepository;
use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\Money;

/**
 * Cobros y devoluciones de un local.
 *
 * Los pagos llegan por dos caminos que terminan acá: el webhook (en
 * producción) y la pantalla del pedido preguntando (siempre, y es lo único
 * que anda en local). Todo es idempotente: un pago ya registrado no se
 * vuelve a procesar.
 */
final class Payments
{
    /** Estados de Mercado Pago que todavía pueden terminar bien. */
    private const IN_PROGRESS = ['pending', 'in_process', 'authorized', 'in_mediation'];

    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ?MercadoPago $mp,
    ) {
    }

    /** Pregunta por los pagos del pedido y aplica el aprobado si hay; si no, el último. */
    public function sync(int $tenantId, string $publicId): void
    {
        if ($this->mp === null) {
            return;
        }
        $list = $this->mp->paymentsFor($publicId);
        if ($list === []) {
            return;
        }
        $approved = null;
        foreach ($list as $p) {
            if (($p['status'] ?? '') === 'approved') {
                $approved = $p;
                break;
            }
        }
        $this->apply($tenantId, $approved ?? $list[0]);
    }

    /** El webhook trae sólo el id: el pago se pide a la API, nunca se cree lo que llegó. */
    public function applyById(int $tenantId, string $paymentId): void
    {
        if ($this->mp === null) {
            return;
        }
        $this->apply($tenantId, $this->mp->payment($paymentId));
    }

    /** @param array<string,mixed> $payment tal como lo devuelve la API */
    private function apply(int $tenantId, array $payment): void
    {
        $publicId = (string) ($payment['external_reference'] ?? '');
        // El pago tiene que ser de un pedido de ESTE local: con el token de
        // un local no se puede confirmar el pedido de otro.
        if ($publicId === '' || $this->orders->tenantOf($publicId) !== $tenantId) {
            return;
        }

        $ref = (string) ($payment['id'] ?? '');
        if ($ref === '' || $this->orders->paymentRegistered('mercadopago', $ref)) {
            return;
        }

        $status = (string) ($payment['status'] ?? '');
        if (in_array($status, self::IN_PROGRESS, true)) {
            return;
        }

        $order = $this->orders->find($tenantId, $publicId);
        $payload = json_encode($payment, JSON_UNESCAPED_UNICODE) ?: null;
        $amount = Money::toCents($payment['transaction_amount'] ?? 0);

        if ($status !== 'approved') {
            $this->orders->settlePayment($order, false, 'mercadopago', $ref, $amount, $payload);
            return;
        }

        $matches = $amount === Money::toCents($order['total'])
            && ($payment['currency_id'] ?? $order['currency']) === $order['currency'];

        // Pago aprobado de un pedido cancelado o por otro monto: se registra
        // pero no confirma nada, y queda el aviso para el local.
        if ($order['status'] === 'cancelado' || !$matches) {
            $this->orders->registerPayment((int) $order['id'], 'mercadopago', $ref, 'aprobado', $amount, $payload);
            $this->orders->addEvent(
                (int) $order['id'],
                (string) $order['status'],
                $order['status'] === 'cancelado'
                    ? "Llegó un pago aprobado (Mercado Pago $ref) de un pedido cancelado: hay que devolverlo."
                    : "El pago de Mercado Pago $ref no coincide con el total: revisarlo antes de imprimir.",
                'pasarela',
            );
            return;
        }

        $this->orders->settlePayment($order, true, 'mercadopago', $ref, $amount, $payload);
    }

    /**
     * Devuelve la plata de un pedido pagado.
     *
     * Si Mercado Pago no puede (por ejemplo, el local no tiene saldo), el
     * pedido queda en «devolución pendiente» y el local lo resuelve desde el
     * panel: reintentar o marcar que devolvió en efectivo.
     *
     * @return bool true si la devolución se hizo
     */
    public function refund(array $order, string $actor): bool
    {
        $orderId = (int) $order['id'];
        $amount = Money::toCents($order['total']);
        $payment = $this->orders->approvedPayment($orderId);

        if ($payment === null) {
            // Nunca se cobró: no hay nada que devolver.
            return true;
        }

        if ($payment['provider'] === 'simulado') {
            $this->orders->addRefund($orderId, 'simulado', null, $amount);
            $this->orders->setPaymentStatus($orderId, 'devuelto');
            $this->orders->addEvent($orderId, (string) $order['status'], 'Pago simulado devuelto.', $actor);
            return true;
        }

        if ($this->mp === null) {
            $this->orders->setPaymentStatus($orderId, 'devolucion_pendiente');
            $this->orders->addEvent($orderId, (string) $order['status'], 'No se pudo devolver: Mercado Pago no está conectado.', 'sistema');
            return false;
        }

        try {
            // La clave cambia por minuto: un doble clic no devuelve dos veces
            // (y Mercado Pago tampoco deja devolver más que lo cobrado), pero
            // un reintento más tarde no recibe la respuesta fallida guardada.
            $key = 'refund-' . $order['public_id'] . '-' . intdiv(time(), 60);
            $res = $this->mp->refund((string) $payment['provider_ref'], $key);
        } catch (HttpException $e) {
            $this->orders->setPaymentStatus($orderId, 'devolucion_pendiente');
            $this->orders->addEvent($orderId, (string) $order['status'], 'No se pudo devolver por Mercado Pago: ' . $e->getMessage(), 'sistema');
            return false;
        }

        $this->orders->addRefund($orderId, 'mercadopago', isset($res['id']) ? (string) $res['id'] : null, $amount);
        $this->orders->setPaymentStatus($orderId, 'devuelto');
        $this->orders->addEvent($orderId, (string) $order['status'], 'Plata devuelta por Mercado Pago.', $actor);

        return true;
    }
}
