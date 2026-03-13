<?php
/**
 * Panelion Application Configuration
 */
return [
    'name' => 'Panelion',
    'version' => '1.0.0',
    'debug' => true,
    'timezone' => 'UTC',
    'url' => 'http://localhost/panelion/public',
    'port' => 80,
    'ssl_port' => 443,

    // Security
    'security' => [
        'session_lifetime' => 3600,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'password_min_length' => 12,
        'require_2fa' => false,
        'allowed_ips' => [],
        'csrf_token_lifetime' => 3600,
        'api_rate_limit' => 60,
    ],

    // Paths
    'paths' => [
        'root' => dirname(__DIR__),
        'storage' => dirname(__DIR__) . '/storage',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'cache' => dirname(__DIR__) . '/storage/cache',
        'sessions' => dirname(__DIR__) . '/storage/sessions',
        'backups' => dirname(__DIR__) . '/storage/backups',
        'vhosts' => dirname(__DIR__) . '/storage/vhosts',
        'ssl_certs' => dirname(__DIR__) . '/storage/ssl',
        'user_data' => dirname(__DIR__) . '/storage/users',
    ],

    // Database
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'panelion',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    // Mail
    'mail' => [
        'driver' => 'smtp',
        'host' => 'localhost',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_address' => 'admin@panelion.local',
        'from_name' => 'Panelion',
    ],

    // Services
    'services' => [
        'webserver' => 'nginx', // nginx or apache
        'dns' => 'bind',
        'mail' => 'postfix',
        'ftp' => 'proftpd',
        'firewall' => 'ufw',
    ],

    // Supported runtimes
    'runtimes' => [
        'php' => ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'],
        'node' => ['18', '20', '22'],
        'python' => ['3.8', '3.9', '3.10', '3.11', '3.12'],
        'ruby' => ['3.0', '3.1', '3.2', '3.3'],
        'go' => ['1.21', '1.22'],
        'rust' => ['latest'],
        'java' => ['17', '21'],
    ],

    // Supported databases
    'databases' => [
        'mysql' => true,
        'mariadb' => true,
        'postgresql' => true,
        'mongodb' => true,
        'redis' => true,
        'sqlite' => true,
    ],

    // License (FMZ License Manager)
    'license' => [
        'api_base' => 'https://tektove.com/wp-json/fmz-license/v1',
        'product_slug' => 'panelion',
        'product_url' => 'https://tektove.com/shop/saas/panelion/',
        'check_interval' => 86400,   // Re-verify with API every 24 hours
        'grace_period' => 604800,    // Trust cache for 7 days if API unreachable
        'public_key' => null,        // Set to PEM string or leave null to use built-in key
    ],
];
