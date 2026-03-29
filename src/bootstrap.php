<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone']);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Support/helpers.php';

ensure_storage($config);
