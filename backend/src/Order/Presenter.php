<?php

declare(strict_types=1);

namespace CopiaFacil\Order;

use CopiaFacil\Shop\ShopRepository;
use CopiaFacil\Support\Money;

/**
 * El contrato con el front. Los montos viajan en centavos (enteros).
 */
final class Presenter
{
    /** @param array<string,mixed> $shop */
    public static function shop(array $shop, bool $mpConnected, bool $fakeAllowed, int $maxUploadMb): array
    {
        $trialEnded = self::trialEnded($shop);

        return [
            'slug' => $shop['slug'],
            'name' => $shop['name'],
            'address' => $shop['address'],
            'isOpen' => (bool) $shop['is_open'],
            // Sin Mercado Pago conectado (y sin simulador) no se puede pedir.
            'acceptsOrders' => (bool) $shop['is_open'] && ($mpConnected || $fakeAllowed) && !$trialEnded,
            'trialEnded' => $trialEnded,
            'paymentsEnabled' => $mpConnected || $fakeAllowed,
            'simulatedPayments' => !$mpConnected && $fakeAllowed,
            'prices' => ShopRepository::pricesInCents($shop),
            'offers' => [
                'color' => (bool) $shop['offers_color'],
                'oficio' => (bool) $shop['offers_oficio'],
                'anillado' => (bool) $shop['offers_anillado'],
            ],
            // Identificación del proveedor (Ley 24.240, art. 4; Res. SCI 270/2020).
            'legal' => [
                'legalName' => $shop['legal_name'],
                'cuit' => $shop['cuit'],
                'email' => $shop['contact_email'],
                'phone' => $shop['contact_phone'],
            ],
            'maxUploadMb' => $maxUploadMb,
        ];
    }

    /** @param array<string,mixed> $o */
    public static function order(array $o, array $shop): array
    {
        return [
            'publicId' => $o['public_id'],
            'number' => (int) $o['number'],
            'shop' => ['slug' => $shop['slug'], 'name' => $shop['name'], 'address' => $shop['address']],
            'customerName' => $o['customer_name'],
            'fileName' => $o['file_name'],
            'pages' => (int) $o['pages'],
            'copies' => (int) $o['copies'],
            'color' => $o['color'],
            'sides' => ($o['sides'] === 'doble' && (int) $o['pages'] > 1) ? 'doble' : 'simple',
            'paper' => $o['paper'],
            'finish' => $o['finish'],
            'note' => $o['note'],
            'unitPrice' => Money::toCents($o['unit_price']),
            'total' => Money::toCents($o['total']),
            'status' => $o['status'],
            'paymentStatus' => $o['payment_status'],
            'rejectReason' => $o['reject_reason'],
            'canWithdraw' => in_array($o['status'], ['pendiente_pago', 'nuevo'], true),
            'createdAt' => self::iso($o['created_at']),
            'paidAt' => self::iso($o['paid_at']),
        ];
    }

    /**
     * @param array<string,mixed> $o
     * @param list<array<string,mixed>> $events
     */
    public static function adminOrder(array $o, array $shop, array $events = []): array
    {
        return self::order($o, $shop) + [
            'fileAvailable' => $o['file_path'] !== null,
            'fileMime' => $o['file_mime'],
            'fileSize' => (int) $o['file_size'],
            'pagesCounted' => (bool) $o['pages_counted'],
            'events' => array_map(static fn (array $e) => [
                'status' => $e['status'],
                'message' => $e['message'],
                'actor' => $e['actor'],
                'at' => self::iso($e['created_at']),
            ], $events),
        ];
    }

    /** @param array<string,mixed> $shop la semana de prueba venció */
    public static function trialEnded(array $shop): bool
    {
        return ($shop['trial_ends_at'] ?? null) !== null && strtotime((string) $shop['trial_ends_at']) < time();
    }

    private static function iso(mixed $dt): ?string
    {
        if ($dt === null || $dt === '') {
            return null;
        }

        return (new \DateTimeImmutable((string) $dt))->format(DATE_ATOM);
    }
}
