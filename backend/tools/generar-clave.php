<?php

declare(strict_types=1);

// Genera un APP_KEY nuevo. Uso: php tools/generar-clave.php
// Pegalo en el .env como APP_KEY=... y guardalo en un lugar seguro:
// si se pierde, los tokens de Mercado Pago guardados no se pueden leer.

echo 'APP_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;
