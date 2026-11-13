<?php

declare(strict_types=1);

namespace CopiaFacil\Payment;

use CopiaFacil\Support\Crypto;
use PDO;

/**
 * Renueva los access tokens de Mercado Pago conectados por OAuth antes de
 * que venzan (duran 180 días). Lo corre tools/mantenimiento.php.
 *
 * Si la renovación falla (el local revocó el permiso, cambió la app…), el
 * token sigue hasta su vencimiento y el panel le pide al local que vuelva
 * a conectar.
 */
final class TokenRefresher
{
    public function __construct(
        private readonly PDO $db,
        private readonly Crypto $crypto,
        private readonly MercadoPagoOAuth $oauth,
    ) {
    }

    /** @return array{renewed:int, failed:int} */
    public function refreshExpiring(int $daysBefore = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT tenant_id, mp_refresh_token_enc FROM tenant_settings
              WHERE mp_connected_via = 'oauth'
                AND mp_refresh_token_enc IS NOT NULL
                AND mp_token_expires_at < NOW() + INTERVAL :d DAY
              LIMIT 100",
        );
        $stmt->bindValue('d', $daysBefore, PDO::PARAM_INT);
        $stmt->execute();

        $renewed = $failed = 0;
        foreach ($stmt->fetchAll() as $row) {
            try {
                $t = $this->oauth->refresh($this->crypto->decrypt((string) $row['mp_refresh_token_enc']));
                $this->db->prepare(
                    'UPDATE tenant_settings
                        SET mp_access_token_enc = :a,
                            mp_refresh_token_enc = COALESCE(:r, mp_refresh_token_enc),
                            mp_token_expires_at = NOW() + INTERVAL :s SECOND
                      WHERE tenant_id = :t',
                )->execute([
                    'a' => $this->crypto->encrypt($t['access_token']),
                    'r' => $t['refresh_token'] === null ? null : $this->crypto->encrypt($t['refresh_token']),
                    's' => $t['expires_in'],
                    't' => $row['tenant_id'],
                ]);
                $renewed++;
            } catch (\Throwable $e) {
                error_log('[copiafacil] no se pudo renovar el token MP del local ' . $row['tenant_id'] . ': ' . $e->getMessage());
                $failed++;
            }
        }

        return ['renewed' => $renewed, 'failed' => $failed];
    }
}
