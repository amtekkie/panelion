<?php
/**
 * Panelion - Web Hosting Control Panel
 * Main Entry Point
 */

define('PANELION_ROOT', dirname(__DIR__));
define('PANELION_VERSION', '1.0.0');
define('PANELION_START', microtime(true));

// Autoloader
require_once PANELION_ROOT . '/core/Autoloader.php';
\Panelion\Core\Autoloader::register();

// Global URL helper for views
function panelion_url(string $path = '/'): string
{
    return \Panelion\Core\App::getInstance()->url($path);
}

// Load configuration
$config = require_once PANELION_ROOT . '/config/app.php';

// Boot application
$app = \Panelion\Core\App::getInstance();
$app->boot($config);

// Run
$app->run();
