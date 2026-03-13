<?php
/**
 * Panelion - Autoloader
 */

namespace Panelion\Core;

class Autoloader
{
    private static array $namespaceMap = [
        'Panelion\\Core\\' => '/core/',
        'Panelion\\Modules\\' => '/modules/',
        'Panelion\\Config\\' => '/config/',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        foreach (self::$namespaceMap as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            $relativeClass = substr($class, $len);
            $file = PANELION_ROOT . $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}
