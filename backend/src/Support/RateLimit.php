<?php

declare(strict_types=1);

namespace CopiaFacil\Support;

use PDO;

/**
 * Límite de intentos por ventana de tiempo, guardado en MySQL.
 *
 * Sin esto, un script llena el panel de un local de pedidos falsos o
 * prueba contraseñas sin parar. La clave que llega ya viene hasheada:
 * acá no se guardan IPs.
 */
final class RateLimit
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function hit(string $bucketHash, int $max, int $windowSeconds, string $message): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT window_start, hits FROM rate_limits WHERE bucket = :b FOR UPDATE');
            $stmt->execute(['b' => $bucketHash]);
            $row = $stmt->fetch();

            $now = time();
            if ($row === false || strtotime((string) $row['window_start']) + $windowSeconds < $now) {
                $this->db->prepare(
                    'REPLACE INTO rate_limits (bucket, window_start, hits) VALUES (:b, NOW(), 1)',
                )->execute(['b' => $bucketHash]);
                $this->db->commit();
                return;
            }

            if ((int) $row['hits'] >= $max) {
                $this->db->commit();
                throw new HttpException(429, $message, 'demasiados_intentos');
            }

            $this->db->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = :b')->execute(['b' => $bucketHash]);
            $this->db->commit();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public static function clientIp(): string
    {
        // Detrás del proxy de Netlify la IP real viene en X-Forwarded-For
        // (el primer valor). Sólo se usa hasheada y para limitar.
        $fwd = $_SERVER['HTTP_X_NF_CLIENT_CONNECTION_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd !== '') {
            return trim(explode(',', $fwd)[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
