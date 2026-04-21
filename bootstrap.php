<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'WpPageForPostType\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $filePath      = __DIR__ . '/source/php/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    }
});
