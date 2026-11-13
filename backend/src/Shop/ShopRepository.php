<?php

declare(strict_types=1);

namespace CopiaFacil\Shop;

use CopiaFacil\Support\HttpException;
use CopiaFacil\Support\Money;
use PDO;

/**
 * El local y su configuración.
 */
final class ShopRepository
{
    /** Columnas de precio, en el orden en que se muestran. */
    public const PRICE_COLUMNS = [
        'bnSimple' => 'price_bn_simple',
        'bnDoble' => 'price_bn_doble',
        'colorSimple' => 'price_color_simple',
        'colorDoble' => 'price_color_doble',
        'abrochado' => 'price_abrochado',
        'anillado' => 'price_anillado',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed> local + settings; 404 si no existe o está dado de baja */
    public function bySlug(string $slug): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug) !== 1) {
            throw HttpException::notFound('No encontramos ese local.', 'local_no_encontrado');
        }

        $stmt = $this->db->prepare(
            'SELECT t.*, s.*
               FROM tenants t
               JOIN tenant_settings s ON s.tenant_id = t.id
              WHERE t.slug = :slug AND t.is_active = 1
              LIMIT 1',
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw HttpException::notFound('No encontramos ese local.', 'local_no_encontrado');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    public function byId(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, s.* FROM tenants t JOIN tenant_settings s ON s.tenant_id = t.id WHERE t.id = :id LIMIT 1',
        );
        $stmt->execute(['id' => $tenantId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw HttpException::notFound('No encontramos ese local.', 'local_no_encontrado');
        }

        return $row;
    }

    /**
     * Precios en centavos, con las claves que usa el front.
     *
     * @param array<string,mixed> $shop
     * @return array<string,int>
     */
    public static function pricesInCents(array $shop): array
    {
        $out = [];
        foreach (self::PRICE_COLUMNS as $key => $col) {
            $out[$key] = Money::toCents($shop[$col]);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $fields columnas ya validadas
     */
    public function updateSettings(int $tenantId, array $fields): void
    {
        $tenantCols = array_intersect_key($fields, array_flip(['name', 'address', 'is_open']));
        $settingCols = array_diff_key($fields, $tenantCols);

        if ($tenantCols !== []) {
            $set = implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($tenantCols)));
            $this->db->prepare("UPDATE tenants SET $set WHERE id = :id")->execute($tenantCols + ['id' => $tenantId]);
        }
        if ($settingCols !== []) {
            $set = implode(', ', array_map(static fn ($c) => "$c = :$c", array_keys($settingCols)));
            $this->db->prepare("UPDATE tenant_settings SET $set WHERE tenant_id = :id")->execute($settingCols + ['id' => $tenantId]);
        }
    }

    /**
     * Guarda la conexión con Mercado Pago. Con OAuth llegan además el refresh
     * token y la duración del access token; pegado a mano, no vence.
     */
    public function saveMercadoPago(
        int $tenantId,
        string $tokenEnc,
        string $userId,
        string $nickname,
        bool $isTest,
        string $via = 'manual',
        ?string $refreshEnc = null,
        ?int $expiresInSeconds = null,
    ): void {
        $this->db->prepare(
            'UPDATE tenant_settings
                SET mp_access_token_enc = :t, mp_refresh_token_enc = :r,
                    mp_token_expires_at = IF(:e IS NULL, NULL, NOW() + INTERVAL :e2 SECOND),
                    mp_connected_via = :via,
                    mp_user_id = :u, mp_nickname = :n, mp_is_test = :test, mp_connected_at = NOW()
              WHERE tenant_id = :id',
        )->execute([
            't' => $tokenEnc, 'r' => $refreshEnc, 'e' => $expiresInSeconds, 'e2' => $expiresInSeconds ?? 0,
            'via' => $via, 'u' => $userId, 'n' => $nickname, 'test' => $isTest ? 1 : 0, 'id' => $tenantId,
        ]);
    }

    /**
     * Hay un token de Mercado Pago usable: cargado y no vencido.
     *
     * @param array<string,mixed> $shop
     */
    public static function mpConnected(array $shop): bool
    {
        if (($shop['mp_access_token_enc'] ?? null) === null) {
            return false;
        }
        $exp = $shop['mp_token_expires_at'] ?? null;

        return $exp === null || strtotime((string) $exp) > time();
    }

    public function clearMercadoPago(int $tenantId): void
    {
        $this->db->prepare(
            'UPDATE tenant_settings
                SET mp_access_token_enc = NULL, mp_refresh_token_enc = NULL, mp_token_expires_at = NULL, mp_connected_via = NULL,
                    mp_user_id = NULL, mp_nickname = NULL, mp_is_test = 0, mp_connected_at = NULL
              WHERE tenant_id = :id',
        )->execute(['id' => $tenantId]);
    }
}
