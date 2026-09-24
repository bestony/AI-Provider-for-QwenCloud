<?php

/**
 * Minimal PSR-4 autoloader.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'QwenCloud\\AiProvider\\';
    $length = strlen($prefix);

    if (strncmp($class, $prefix, $length) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, $length)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
