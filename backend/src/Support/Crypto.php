<?php

declare(strict_types=1);

namespace CopiaFacil\Support;

use RuntimeException;

/**
 * Cifrado simétrico con libsodium (XSalsa20-Poly1305) y firmas HMAC.
 *
 * Se usa para dos cosas:
 * - guardar el access token de Mercado Pago de cada local cifrado en la base;
 * - firmar los links de descarga de archivos y los hashes de IP.
 *
 * La clave es APP_KEY del .env (32 bytes en base64). Sin ella la app no
 * arranca las partes que la necesitan, en vez de guardar tokens en claro.
 */
final class Crypto
{
    private readonly string $key;

    public function __construct(string $appKeyBase64)
    {
        $key = base64_decode($appKeyBase64, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('APP_KEY falta o es inválida. Generala con: php tools/generar-clave.php');
        }
        $this->key = $key;
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Dato cifrado corrupto.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar: ¿cambió APP_KEY?');
        }

        return $plain;
    }

    public function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, $this->key);
    }

    /**
     * Un token firmado y con vencimiento, apto para URL.
     *
     * @param array<string,scalar> $claims
     */
    public function sign(array $claims, int $ttlSeconds): string
    {
        $claims['exp'] = time() + $ttlSeconds;
        $body = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $body . '.' . $this->hmac($body);
    }

    /** @return array<string,mixed>|null null si la firma no cierra o venció */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !hash_equals($this->hmac($parts[0]), $parts[1])) {
            return null;
        }
        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);
        if (!is_array($claims) || (int) ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        return $claims;
    }
}
