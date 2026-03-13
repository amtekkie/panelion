<?php
/**
 * Panelion API Entry Point
 */

define('PANELION_ROOT', dirname(__DIR__));
define('PANELION_VERSION', '1.0.0');
define('PANELION_API', true);
define('PANELION_START', microtime(true));

require_once PANELION_ROOT . '/core/Autoloader.php';
\Panelion\Core\Autoloader::register();

$config = require_once PANELION_ROOT . '/config/app.php';

$app = \Panelion\Core\App::getInstance();
$app->boot($config);

$api = new \Panelion\Core\API($app);
$api->handle();
