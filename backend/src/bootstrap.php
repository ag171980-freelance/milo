<?php

declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

/** Autoload PSR-4 a mano: el proyecto no usa Composer. */
spl_autoload_register(static function (string $class): void {
    $prefix = 'CopiaFacil\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
