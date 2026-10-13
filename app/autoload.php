<?php

declare(strict_types=1);

/**
 * Class loading and configuration only - no session, no database. Used by
 * app/bootstrap.php and by anything that must stay light.
 */

date_default_timezone_set('Asia/Kuala_Lumpur');

// Composer packages (PhpSpreadsheet for XLSX upload / export). vendor/ is not
// committed: run `composer install` in cora/ on every server. Without it the
// module still works - uploads and exports fall back to CSV only.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cora\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
