<?php

declare(strict_types=1);

namespace CopiaFacil\Admin;

use CopiaFacil\Support\HttpException;
use PDO;

/**
 * Quién entra al panel.
 *
 * El navegador guarda un token opaco y lo manda en `Authorization: Bearer`.
 * En la base queda su SHA-256: un volcado no trae sesiones vivas.
 * Un usuario pertenece a un local y sólo ve ese local.
 */
final class Auth
{
    private const DAYS = 14;

    /** Hash de descarte para que «mail inexistente» tarde lo mismo que «clave mala». */
    private const DECOY_HASH = '$2y$12$VcEJeFZRT9q3TCpyVmyBkuVol8V/usZtsblUnUMPkNzzb1kZzjIOe';

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{token:string, user:array<string,mixed>} */
    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.tenant_id, u.email, u.name, u.password_hash, u.is_active,
                    t.slug AS tenant_slug, t.name AS tenant_name, t.is_active AS tenant_active
               FROM users u JOIN tenants t ON t.id = u.tenant_id
              WHERE u.email = :email LIMIT 1',
        );
        $stmt->execute(['email' => mb_strtolower($email)]);
        $user = $stmt->fetch();

        if ($user === false) {
            password_verify($password, self::DECOY_HASH);
            throw HttpException::unauthorized('Mail o contraseña incorrectos.', 'credenciales_invalidas');
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            throw HttpException::unauthorized('Mail o contraseña incorrectos.', 'credenciales_invalidas');
        }
        if (!$user['is_active'] || !$user['tenant_active']) {
            throw HttpException::forbidden('Esta cuenta está dada de baja.', 'cuenta_inactiva');
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute(['h' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $user['id']]);
        }

        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            'INSERT INTO sessions (token_hash, user_id, expires_at, last_seen_at)
             VALUES (:h, :u, NOW() + INTERVAL ' . self::DAYS . ' DAY, NOW())',
        )->execute(['h' => hash('sha256', $token), 'u' => $user['id']]);
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
        $this->db->exec('DELETE FROM sessions WHERE expires_at < NOW()');

        return ['token' => $token, 'user' => self::publicUser($user)];
    }

    /** @return array<string,mixed> el usuario con tenant_id; 401 si no hay sesión */
    public function requireUser(): array
    {
        $token = self::requestToken();
        if ($token === null) {
            throw HttpException::unauthorized();
        }

        $stmt = $this->db->prepare(
            'SELECT u.id, u.tenant_id, u.email, u.name, u.is_active,
                    t.slug AS tenant_slug, t.name AS tenant_name, t.is_active AS tenant_active
               FROM sessions s
               JOIN users u ON u.id = s.user_id
               JOIN tenants t ON t.id = u.tenant_id
              WHERE s.token_hash = :h AND s.expires_at > NOW() LIMIT 1',
        );
        $stmt->execute(['h' => hash('sha256', $token)]);
        $user = $stmt->fetch();

        if ($user === false) {
            throw HttpException::unauthorized('La sesión venció. Entrá de nuevo.', 'sesion_vencida');
        }
        if (!$user['is_active'] || !$user['tenant_active']) {
            throw HttpException::forbidden('Esta cuenta está dada de baja.', 'cuenta_inactiva');
        }

        $this->db->prepare('UPDATE sessions SET last_seen_at = NOW() WHERE token_hash = :h')
            ->execute(['h' => hash('sha256', $token)]);

        return $user;
    }

    public function logout(): void
    {
        $token = self::requestToken();
        if ($token !== null) {
            $this->db->prepare('DELETE FROM sessions WHERE token_hash = :h')->execute(['h' => hash('sha256', $token)]);
        }
    }

    public function changePassword(int $userId, string $current, string $new): void
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || !password_verify($current, (string) $hash)) {
            throw HttpException::forbidden('La contraseña actual no coincide.', 'clave_incorrecta');
        }
        if (mb_strlen($new) < 10) {
            throw HttpException::badRequest('La contraseña nueva necesita al menos 10 caracteres.', 'clave_corta');
        }

        $this->db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
            ->execute(['h' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), 'id' => $userId]);

        // Cambiar la clave cierra las otras sesiones.
        $this->db->prepare('DELETE FROM sessions WHERE user_id = :id AND token_hash <> :h')
            ->execute(['id' => $userId, 'h' => hash('sha256', self::requestToken() ?? '')]);
    }

    /** @param array<string,mixed> $user */
    public static function publicUser(array $user): array
    {
        return [
            'email' => $user['email'],
            'name' => $user['name'],
            'shop' => ['slug' => $user['tenant_slug'], 'name' => $user['tenant_name']],
        ];
    }

    private static function requestToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                    break;
                }
            }
        }

        return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim((string) $header), $m) === 1 ? strtolower($m[1]) : null;
    }
}
