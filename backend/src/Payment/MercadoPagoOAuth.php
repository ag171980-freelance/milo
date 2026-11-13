<?php

declare(strict_types=1);

namespace CopiaFacil\Payment;

use CopiaFacil\Support\HttpException;

/**
 * «Conectar con Mercado Pago»: OAuth con la aplicación de la plataforma.
 *
 * La plataforma registra UNA aplicación en Mercado Pago Developers
 * (MP_CLIENT_ID / MP_CLIENT_SECRET). Cada local autoriza esa aplicación
 * con su cuenta y Mercado Pago nos entrega un access token de ESE local,
 * que se usa exactamente igual que uno pegado a mano: la plata va a la
 * cuenta del local.
 *
 * Flujo (authorization code + PKCE):
 *   1. authorizationUrl(): el local va a Mercado Pago y autoriza.
 *   2. Mercado Pago vuelve a MP_REDIRECT_URI con ?code=…&state=…
 *   3. exchangeCode(): el código (un solo uso, 10 minutos) se cambia por
 *      access_token + refresh_token. El access token vence a los 180 días.
 *   4. refresh(): antes de que venza, se pide uno nuevo con el refresh token.
 *      Mercado Pago puede devolver un refresh token nuevo: se guarda siempre
 *      el último.
 */
final class MercadoPagoOAuth
{
    private const TOKEN_URL = 'https://api.mercadopago.com/oauth/token';

    /** @param array<string,mixed> $config la sección mercadopago_oauth del config */
    public function __construct(private readonly array $config)
    {
    }

    public static function configured(array $config): bool
    {
        return ($config['client_id'] ?? '') !== '' && ($config['client_secret'] ?? '') !== '' && ($config['redirect_uri'] ?? '') !== '';
    }

    /** @return array{verifier:string, challenge:string} */
    public static function pkcePair(): array
    {
        $verifier = self::b64url(random_bytes(48));

        return ['verifier' => $verifier, 'challenge' => self::b64url(hash('sha256', $verifier, true))];
    }

    public function authorizationUrl(string $state, ?string $codeChallenge): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $this->config['redirect_uri'],
        ];
        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            // La documentación nombra el método de las dos formas según el
            // país; se mandan ambas y Mercado Pago ignora la que no usa.
            $params['code_challenge_method'] = 'S256';
            $params['code_method'] = 'S256';
        }

        return $this->config['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * @return array{access_token:string, refresh_token:?string, expires_in:int, user_id:string}
     */
    public function exchangeCode(string $code, ?string $verifier): array
    {
        $body = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        ];
        if ($verifier !== null) {
            $body['code_verifier'] = $verifier;
        }
        if (($this->config['test_token'] ?? false) === true) {
            $body['test_token'] = 'true';
        }

        return $this->tokenRequest($body);
    }

    /** @return array{access_token:string, refresh_token:?string, expires_in:int, user_id:string} */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * @param array<string,string> $body
     * @return array{access_token:string, refresh_token:?string, expires_in:int, user_id:string}
     */
    private function tokenRequest(array $body): array
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($raw === false) {
            throw new HttpException(502, 'No pudimos conectarnos con Mercado Pago. Probá de nuevo.', 'mp_sin_conexion');
        }
        $data = json_decode((string) $raw, true);

        if ($status < 200 || $status >= 300 || !is_array($data) || !is_string($data['access_token'] ?? null)) {
            $detail = is_array($data) ? (string) ($data['message'] ?? $data['error_description'] ?? $data['error'] ?? 'error') : 'respuesta inválida';
            throw new HttpException(502, 'Mercado Pago no aceptó la conexión: ' . $detail, 'mp_oauth_error');
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'expires_in' => (int) ($data['expires_in'] ?? 15552000),
            'user_id' => (string) ($data['user_id'] ?? ''),
        ];
    }

    private static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
