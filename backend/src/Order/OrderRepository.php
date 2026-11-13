<?php

declare(strict_types=1);

namespace CopiaFacil\Order;

use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\Money;
use PDO;

/**
 * Persistencia de pedidos, pagos y devoluciones.
 * Toda consulta de un pedido filtra por tenant_id, aunque el id ya lo implique.
 */
final class OrderRepository
{
    public const ACTIVE = ['nuevo', 'imprimiendo', 'listo'];
    public const CLOSED = ['entregado', 'rechazado', 'cancelado'];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Crea el pedido. El número corto se reserva con SELECT … FOR UPDATE:
     * sin el bloqueo, dos pedidos simultáneos leen el mismo número.
     *
     * @param array<string,mixed> $row columnas de orders salvo id/number/public_id
     * @return array<string,mixed> el pedido guardado
     */
    public function create(int $tenantId, array $row): array
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT IGNORE INTO order_counters (tenant_id, last_number) VALUES (:t, 100)')
                ->execute(['t' => $tenantId]);
            $stmt = $this->db->prepare('SELECT last_number FROM order_counters WHERE tenant_id = :t FOR UPDATE');
            $stmt->execute(['t' => $tenantId]);
            $number = (int) $stmt->fetchColumn() + 1;
            $this->db->prepare('UPDATE order_counters SET last_number = :n WHERE tenant_id = :t')
                ->execute(['n' => $number, 't' => $tenantId]);

            $publicId = self::newPublicId();
            $row = ['tenant_id' => $tenantId, 'public_id' => $publicId, 'number' => $number] + $row;
            $cols = implode(', ', array_keys($row));
            $vals = implode(', ', array_map(static fn ($c) => ':' . $c, array_keys($row)));
            $this->db->prepare("INSERT INTO orders ($cols) VALUES ($vals)")->execute($row);
            $orderId = (int) $this->db->lastInsertId();

            $this->addEvent($orderId, 'pendiente_pago', 'Pedido creado. Esperando el pago.', 'cliente');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($tenantId, $publicId);
    }

    /** @return array<string,mixed> */
    public function find(int $tenantId, string $publicId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE tenant_id = :t AND public_id = :p LIMIT 1');
        $stmt->execute(['t' => $tenantId, 'p' => $publicId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw HttpException::notFound('No encontramos ese pedido.', 'pedido_no_encontrado');
        }

        return $row;
    }

    /** @return array<string,mixed>|null */
    public function findById(int $tenantId, int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE tenant_id = :t AND id = :id LIMIT 1');
        $stmt->execute(['t' => $tenantId, 'id' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null busca por id público sin saber el local (botón de arrepentimiento) */
    public function findAnyTenant(string $publicId): ?array
    {
        if (preg_match('/^[a-z0-9]{20}$/', $publicId) !== 1) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE public_id = :p LIMIT 1');
        $stmt->execute(['p' => $publicId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param 'activos'|'historial' $scope
     * @return list<array<string,mixed>>
     */
    public function listForAdmin(int $tenantId, string $scope): array
    {
        if ($scope === 'historial') {
            $stmt = $this->db->prepare(
                "SELECT * FROM orders
                  WHERE tenant_id = :t
                    AND status IN ('entregado','rechazado','cancelado')
                    AND payment_status <> 'devolucion_pendiente'
                    AND created_at > NOW() - INTERVAL 30 DAY
                  ORDER BY created_at DESC LIMIT 200",
            );
        } else {
            // Lo que el local tiene que atender: pagados sin terminar y
            // devoluciones que quedaron trabadas.
            $stmt = $this->db->prepare(
                "SELECT * FROM orders
                  WHERE tenant_id = :t
                    AND (status IN ('nuevo','imprimiendo','listo') OR payment_status = 'devolucion_pendiente')
                  ORDER BY FIELD(status,'nuevo','imprimiendo','listo','rechazado','cancelado'), created_at ASC
                  LIMIT 200",
            );
        }
        $stmt->execute(['t' => $tenantId]);

        return $stmt->fetchAll();
    }

    /** @return array{queue:int, todayCount:int, todayTotal:int} */
    public function todayStats(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
               SUM(status = 'nuevo') AS queue,
               SUM(paid_at >= CURDATE() AND payment_status = 'aprobado') AS today_count,
               COALESCE(SUM(CASE WHEN paid_at >= CURDATE() AND payment_status = 'aprobado' THEN total END), 0) AS today_total
             FROM orders WHERE tenant_id = :t",
        );
        $stmt->execute(['t' => $tenantId]);
        $r = $stmt->fetch() ?: [];

        return [
            'queue' => (int) ($r['queue'] ?? 0),
            'todayCount' => (int) ($r['today_count'] ?? 0),
            'todayTotal' => Money::toCents($r['today_total'] ?? 0),
        ];
    }

    public function setStatus(int $orderId, string $status, string $message, string $actor): void
    {
        $this->db->prepare('UPDATE orders SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $orderId]);
        $this->addEvent($orderId, $status, $message, $actor);
    }

    public function setPaymentStatus(int $orderId, string $paymentStatus): void
    {
        $this->db->prepare('UPDATE orders SET payment_status = :s WHERE id = :id')
            ->execute(['s' => $paymentStatus, 'id' => $orderId]);
    }

    public function setRejectReason(int $orderId, string $reason): void
    {
        $this->db->prepare('UPDATE orders SET reject_reason = :r WHERE id = :id')->execute(['r' => $reason, 'id' => $orderId]);
    }

    public function addEvent(int $orderId, string $status, string $message, string $actor): void
    {
        $this->db->prepare(
            'INSERT INTO order_events (order_id, status, message, actor) VALUES (:o, :s, :m, :a)',
        )->execute(['o' => $orderId, 's' => $status, 'm' => mb_substr($message, 0, 300), 'a' => $actor]);
    }

    /** @return list<array<string,mixed>> */
    public function events(int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT status, message, actor, created_at FROM order_events WHERE order_id = :o ORDER BY id');
        $stmt->execute(['o' => $orderId]);

        return $stmt->fetchAll();
    }

    /* --- pagos ----------------------------------------------------------- */

    public function tenantOf(string $publicId): ?int
    {
        $stmt = $this->db->prepare('SELECT tenant_id FROM orders WHERE public_id = :p LIMIT 1');
        $stmt->execute(['p' => $publicId]);
        $v = $stmt->fetchColumn();

        return $v === false ? null : (int) $v;
    }

    public function paymentRegistered(string $provider, string $ref): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM payments WHERE provider = :p AND provider_ref = :r LIMIT 1');
        $stmt->execute(['p' => $provider, 'r' => $ref]);

        return $stmt->fetchColumn() !== false;
    }

    public function registerPayment(int $orderId, string $provider, string $ref, string $status, int $amountCents, ?string $payload): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO payments (order_id, provider, provider_ref, status, amount, payload)
             VALUES (:o, :p, :r, :s, :a, :pl)',
        )->execute([
            'o' => $orderId, 'p' => $provider, 'r' => $ref, 's' => $status,
            'a' => Money::toDecimal($amountCents), 'pl' => $payload,
        ]);
    }

    /**
     * Aplica el resultado de un cobro, una sola vez.
     * Aprobado: el pedido pasa a «nuevo» y aparece en el panel del local.
     * Rechazado: queda esperando el pago, para que el cliente reintente.
     */
    public function settlePayment(array $order, bool $approved, string $provider, string $ref, int $amountCents, ?string $payload): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status, payment_status FROM orders WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $order['id']]);
            $current = $stmt->fetch();

            $this->registerPayment((int) $order['id'], $provider, $ref, $approved ? 'aprobado' : 'rechazado', $amountCents, $payload);

            if ($current !== false && $current['payment_status'] !== 'aprobado') {
                if ($approved) {
                    $this->db->prepare(
                        "UPDATE orders SET status = 'nuevo', payment_status = 'aprobado', paid_at = NOW() WHERE id = :id",
                    )->execute(['id' => $order['id']]);
                    $this->addEvent((int) $order['id'], 'nuevo', 'Pago aprobado. El pedido entró a la cola.', 'pasarela');
                } else {
                    $this->db->prepare("UPDATE orders SET payment_status = 'rechazado' WHERE id = :id")
                        ->execute(['id' => $order['id']]);
                    $this->addEvent((int) $order['id'], 'pendiente_pago', 'El pago fue rechazado.', 'pasarela');
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed>|null el pago aprobado del pedido */
    public function approvedPayment(int $orderId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM payments WHERE order_id = :o AND status = 'aprobado' ORDER BY id DESC LIMIT 1",
        );
        $stmt->execute(['o' => $orderId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function addRefund(int $orderId, string $method, ?string $ref, int $amountCents): void
    {
        $this->db->prepare(
            'INSERT INTO refunds (order_id, method, provider_ref, amount) VALUES (:o, :m, :r, :a)',
        )->execute(['o' => $orderId, 'm' => $method, 'r' => $ref, 'a' => Money::toDecimal($amountCents)]);
    }

    /* --- retención de archivos ------------------------------------------- */

    /** @return list<array{id:int, file_path:string}> */
    public function filesToPurge(int $retentionHours, int $maxHours): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, file_path FROM orders
              WHERE file_path IS NOT NULL
                AND (
                  (status IN ('entregado','rechazado','cancelado') AND updated_at < NOW() - INTERVAL :r HOUR)
                  OR created_at < NOW() - INTERVAL :m HOUR
                )
              LIMIT 500",
        );
        $stmt->bindValue('r', $retentionHours, PDO::PARAM_INT);
        $stmt->bindValue('m', $maxHours, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markFileDeleted(int $orderId): void
    {
        $this->db->prepare('UPDATE orders SET file_path = NULL, file_deleted_at = NOW() WHERE id = :id')
            ->execute(['id' => $orderId]);
    }

    /** Pedidos que nunca se pagaron: se cancelan para que no queden colgados. */
    public function cancelUnpaid(int $hours): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM orders WHERE status = 'pendiente_pago' AND created_at < NOW() - INTERVAL :h HOUR LIMIT 500",
        );
        $stmt->bindValue('h', $hours, PDO::PARAM_INT);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->setStatus((int) $id, 'cancelado', 'Cancelado automáticamente: no se pagó.', 'sistema');
        }

        return count($ids);
    }

    /* --- arrepentimiento ------------------------------------------------- */

    public function addWithdrawal(string $code, ?int $orderId, ?int $tenantId, string $contact, string $detail, string $outcome): void
    {
        $this->db->prepare(
            'INSERT INTO withdrawal_requests (code, order_id, tenant_id, contact, detail, outcome)
             VALUES (:c, :o, :t, :ct, :d, :out)',
        )->execute(['c' => $code, 'o' => $orderId, 't' => $tenantId, 'ct' => $contact, 'd' => $detail, 'out' => $outcome]);
    }

    private static function newPublicId(): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
