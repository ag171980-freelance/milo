<?php

declare(strict_types=1);

/**
 * Da de alta un local con su usuario del panel.
 *
 *   php tools/nuevo-local.php <slug> "<Nombre del local>" <email> [contraseña] [--prueba=7]
 *
 * Con --prueba=N el local queda en período de prueba por N días: al
 * vencer deja de recibir pedidos hasta que se le quite el vencimiento
 * (UPDATE tenants SET trial_ends_at = NULL WHERE slug = '…').
 *
 * Si no pasás contraseña se genera una y se muestra una sola vez.
 */

use CopiaFacil\Support\Database;

require __DIR__ . '/../src/bootstrap.php';
$config = require __DIR__ . '/../config/config.php';

$trialDays = 0;
$positional = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--prueba=(\d{1,3})$/', $arg, $m) === 1) {
        $trialDays = (int) $m[1];
    } else {
        $positional[] = $arg;
    }
}
[$slug, $name, $email, $password] = $positional + ['', '', '', ''];

const RESERVED = ['admin', 'api', 'terminos', 'privacidad', 'comercios', 'arrepentimiento', 'demo', 'files', 'assets', 'static'];

if (preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', (string) $slug) !== 1 || in_array($slug, RESERVED, true)) {
    fwrite(STDERR, "Slug inválido o reservado. Usá minúsculas, números y guiones (ej: kiosco-el-puente).\n");
    exit(1);
}
if (trim((string) $name) === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php tools/nuevo-local.php <slug> \"<Nombre>\" <email> [contraseña]\n");
    exit(1);
}
$generated = false;
if ($password === '') {
    $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    $generated = true;
}
if (mb_strlen($password) < 10) {
    fwrite(STDERR, "La contraseña necesita al menos 10 caracteres.\n");
    exit(1);
}

$db = Database::connect($config['db']);
$db->beginTransaction();
try {
    $db->prepare(
        'INSERT INTO tenants (slug, name, trial_ends_at) VALUES (:s, :n, IF(:d > 0, NOW() + INTERVAL :d2 DAY, NULL))',
    )->execute(['s' => $slug, 'n' => $name, 'd' => $trialDays, 'd2' => $trialDays]);
    $tenantId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tenant_settings (tenant_id) VALUES (:t)')->execute(['t' => $tenantId]);
    $db->prepare('INSERT INTO order_counters (tenant_id, last_number) VALUES (:t, 100)')->execute(['t' => $tenantId]);
    $db->prepare('INSERT INTO users (tenant_id, email, name, password_hash) VALUES (:t, :e, :n, :h)')->execute([
        't' => $tenantId,
        'e' => mb_strtolower($email),
        'n' => $name,
        'h' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    fwrite(STDERR, str_contains($e->getMessage(), 'Duplicate') ? "Ese slug o ese mail ya existen.\n" : $e->getMessage() . "\n");
    exit(1);
}

echo "Local creado.\n";
echo "  Página del cliente: {$config['front_url']}/$slug\n";
echo "  Panel:              {$config['front_url']}/admin\n";
echo "  Usuario:            $email\n";
if ($trialDays > 0) {
    echo "  Prueba gratis:      $trialDays días\n";
}
if ($generated) {
    echo "  Contraseña:         $password   (se muestra una sola vez)\n";
}
