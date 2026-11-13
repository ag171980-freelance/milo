<?php

declare(strict_types=1);

// Solicitudes de demo de la página principal.
//
//   php tools/solicitudes-demo.php                 las nuevas
//   php tools/solicitudes-demo.php --todas         todas
//   php tools/solicitudes-demo.php <id> <estado>   nueva|contactada|activada|descartada
//
// Para activar una: php tools/nuevo-local.php <slug> "<Local>" <mail> --prueba=7

use CopiaFacil\Support\Database;

require __DIR__ . '/../src/bootstrap.php';
$config = require __DIR__ . '/../config/config.php';
$db = Database::connect($config['db']);

$args = array_slice($argv, 1);

if (count($args) === 2 && ctype_digit($args[0])) {
    $states = ['nueva', 'contactada', 'activada', 'descartada'];
    if (!in_array($args[1], $states, true)) {
        fwrite(STDERR, 'Estado inválido. Usá: ' . implode(', ', $states) . "\n");
        exit(1);
    }
    $stmt = $db->prepare('UPDATE demo_requests SET status = :s WHERE id = :id');
    $stmt->execute(['s' => $args[1], 'id' => (int) $args[0]]);
    echo $stmt->rowCount() ? "Solicitud {$args[0]} → {$args[1]}\n" : "No existe la solicitud {$args[0]}\n";
    exit(0);
}

$all = in_array('--todas', $args, true);
$rows = $db->query(
    'SELECT * FROM demo_requests ' . ($all ? '' : "WHERE status = 'nueva' ") . 'ORDER BY created_at DESC LIMIT 200',
)->fetchAll();

if ($rows === []) {
    echo $all ? "No hay solicitudes.\n" : "No hay solicitudes nuevas.\n";
    exit(0);
}

foreach ($rows as $r) {
    echo str_repeat('-', 60) . "\n";
    echo "#{$r['id']} · {$r['created_at']} · {$r['status']}\n";
    echo "{$r['shop_name']} — {$r['name']}\n";
    echo "{$r['email']}" . ($r['phone'] !== '' ? " · {$r['phone']}" : '') . "\n";
    if ($r['address'] !== '') {
        echo "{$r['address']}\n";
    }
    if ($r['message'] !== '') {
        echo "«{$r['message']}»\n";
    }
}
