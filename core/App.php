<?php
/**
 * Panelion - Application Core
 */

namespace Panelion\Core;

class App
{
    private static ?App $instance = null;
    private array $config = [];
    private ?Database $db = null;
    private ?Router $router = null;
    private ?Session $session = null;
    private ?Auth $auth = null;
    private ?Logger $logger = null;
    private string $basePath = '';

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(array $config): void
    {
        $this->config = $config;

        // Set timezone
        date_default_timezone_set($config['timezone'] ?? 'UTC');

        // Error handling
        if ($config['debug'] ?? false) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // Initialize logger
        $this->logger = new Logger($config['paths']['logs'] ?? PANELION_ROOT . '/storage/logs');

        // Initialize database
        $this->db = new Database($config['database']);

        // Initialize session
        $this->session = new Session($config['paths']['sessions'] ?? PANELION_ROOT . '/storage/sessions');
        $this->session->start();

        // Initialize auth
        $this->auth = new Auth($this->db, $this->session, $config['security'] ?? []);

        // Initialize router
        $this->router = new Router($this);

        // Detect base path for subdirectory installations
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $this->basePath = rtrim(dirname($scriptName), '/\\');

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // Auth routes
        $this->router->get('/login', 'Auth@loginForm');
        $this->router->post('/login', 'Auth@login');
        $this->router->get('/logout', 'Auth@logout');
        $this->router->get('/two-factor', 'Auth@twoFactorForm');
        $this->router->post('/two-factor', 'Auth@twoFactorVerify');

        // Dashboard
        $this->router->get('/', 'Dashboard\\DashboardController@index');
        $this->router->get('/dashboard', 'Dashboard\\DashboardController@index');
        $this->router->get('/dashboard/stats', 'Dashboard\\DashboardController@stats');

        // User management
        $this->router->get('/users', 'Users\\UserController@index');
        $this->router->get('/users/create', 'Users\\UserController@create');
        $this->router->post('/users/store', 'Users\\UserController@store');
        $this->router->get('/users/edit/{id}', 'Users\\UserController@edit');
        $this->router->post('/users/update/{id}', 'Users\\UserController@update');
        $this->router->post('/users/delete/{id}', 'Users\\UserController@delete');
        $this->router->get('/users/suspend/{id}', 'Users\\UserController@suspend');
        $this->router->get('/users/unsuspend/{id}', 'Users\\UserController@unsuspend');

        // Domain management
        $this->router->get('/domains', 'Domains\\DomainController@index');
        $this->router->get('/domains/create', 'Domains\\DomainController@create');
        $this->router->post('/domains/store', 'Domains\\DomainController@store');
        $this->router->get('/domains/edit/{id}', 'Domains\\DomainController@edit');
        $this->router->post('/domains/update/{id}', 'Domains\\DomainController@update');
        $this->router->post('/domains/delete/{id}', 'Domains\\DomainController@delete');
        $this->router->get('/domains/subdomains/{id}', 'Domains\\DomainController@subdomains');
        $this->router->post('/domains/subdomains/{id}/create', 'Domains\\DomainController@createSubdomain');

        // Web Server
        $this->router->get('/webserver', 'WebServer\\WebServerController@index');
        $this->router->get('/webserver/vhosts', 'WebServer\\WebServerController@vhosts');
        $this->router->post('/webserver/vhosts/create', 'WebServer\\WebServerController@createVhost');
        $this->router->post('/webserver/vhosts/update/{id}', 'WebServer\\WebServerController@updateVhost');
        $this->router->post('/webserver/vhosts/delete/{id}', 'WebServer\\WebServerController@deleteVhost');
        $this->router->get('/webserver/php', 'WebServer\\WebServerController@phpConfig');
        $this->router->post('/webserver/php/update', 'WebServer\\WebServerController@updatePhpConfig');
        $this->router->post('/webserver/restart', 'WebServer\\WebServerController@restart');

        // Database management
        $this->router->get('/databases', 'Databases\\DatabaseController@index');
        $this->router->get('/databases/create', 'Databases\\DatabaseController@create');
        $this->router->post('/databases/store', 'Databases\\DatabaseController@store');
        $this->router->post('/databases/delete/{id}', 'Databases\\DatabaseController@delete');
        $this->router->get('/databases/users', 'Databases\\DatabaseController@users');
        $this->router->post('/databases/users/create', 'Databases\\DatabaseController@createUser');
        $this->router->post('/databases/users/delete/{id}', 'Databases\\DatabaseController@deleteUser');
        $this->router->get('/databases/phpmyadmin', 'Databases\\DatabaseController@phpMyAdmin');

        // Application management (views use /applications/{id}/action pattern)
        $this->router->get('/apps', 'Applications\\AppController@index');
        $this->router->get('/applications', 'Applications\\AppController@index');
        $this->router->get('/applications/create', 'Applications\\AppController@create');
        $this->router->post('/applications', 'Applications\\AppController@store');
        $this->router->get('/applications/installer', 'Applications\\AppController@installer');
        $this->router->post('/applications/installer/install', 'Applications\\AppController@installApp');
        $this->router->get('/applications/{id}', 'Applications\\AppController@manage');
        $this->router->get('/applications/{id}/logs', 'Applications\\AppController@logs');
        $this->router->post('/applications/{id}/start', 'Applications\\AppController@start');
        $this->router->post('/applications/{id}/stop', 'Applications\\AppController@stop');
        $this->router->post('/applications/{id}/restart', 'Applications\\AppController@restart');
        $this->router->post('/applications/{id}/delete', 'Applications\\AppController@delete');

        // DNS (views use zone ID, not domain name)
        $this->router->get('/dns', 'DNS\\DNSController@index');
        $this->router->get('/dns/create', 'DNS\\DNSController@createZone');
        $this->router->post('/dns', 'DNS\\DNSController@storeZone');
        $this->router->get('/dns/zone/{domain}', 'DNS\\DNSController@zone');
        $this->router->get('/dns/{id}', 'DNS\\DNSController@zone');
        $this->router->post('/dns/{id}/delete', 'DNS\\DNSController@deleteZone');
        $this->router->post('/dns/{id}/records', 'DNS\\DNSController@addRecord');
        $this->router->post('/dns/{id}/records/{recordId}', 'DNS\\DNSController@updateRecord');
        $this->router->post('/dns/{id}/records/{recordId}/delete', 'DNS\\DNSController@deleteRecord');

        // Email
        $this->router->get('/email', 'Email\\EmailController@index');
        $this->router->get('/email/create', 'Email\\EmailController@create');
        $this->router->post('/email', 'Email\\EmailController@store');
        $this->router->post('/email/{id}/delete', 'Email\\EmailController@delete');
        $this->router->get('/email/accounts', 'Email\\EmailController@index');
        $this->router->post('/email/forwarders', 'Email\\EmailController@createForwarder');
        $this->router->post('/email/forwarders/{id}/delete', 'Email\\EmailController@deleteForwarder');
        $this->router->post('/email/autoresponders', 'Email\\EmailController@createAutoresponder');
        $this->router->post('/email/autoresponders/{id}/delete', 'Email\\EmailController@deleteAutoresponder');
        $this->router->get('/email/webmail', 'Email\\EmailController@webmail');

        // SSL
        $this->router->get('/ssl', 'SSL\\SSLController@index');
        $this->router->get('/ssl/csr', 'SSL\\SSLController@generateCSR');
        $this->router->post('/ssl/csr', 'SSL\\SSLController@generateCSR');
        $this->router->get('/ssl/upload', 'SSL\\SSLController@uploadCustom');
        $this->router->post('/ssl/custom', 'SSL\\SSLController@uploadCustom');
        $this->router->post('/ssl/letsencrypt', 'SSL\\SSLController@letsencrypt');
        $this->router->post('/ssl/{id}/renew', 'SSL\\SSLController@renew');
        $this->router->post('/ssl/{id}/delete', 'SSL\\SSLController@delete');

        // File Manager (views use /filemanager, also support /files)
        $this->router->get('/files', 'FileManager\\FileManagerController@index');
        $this->router->get('/filemanager', 'FileManager\\FileManagerController@index');
        $this->router->get('/filemanager/edit', 'FileManager\\FileManagerController@edit');
        $this->router->get('/files/edit', 'FileManager\\FileManagerController@edit');
        $this->router->post('/filemanager/save', 'FileManager\\FileManagerController@save');
        $this->router->post('/files/save', 'FileManager\\FileManagerController@save');
        $this->router->get('/filemanager/download', 'FileManager\\FileManagerController@download');
        $this->router->get('/files/download', 'FileManager\\FileManagerController@download');
        $this->router->post('/filemanager/upload', 'FileManager\\FileManagerController@upload');
        $this->router->post('/files/upload', 'FileManager\\FileManagerController@upload');
        $this->router->post('/filemanager/create', 'FileManager\\FileManagerController@createFile');
        $this->router->post('/files/create', 'FileManager\\FileManagerController@createFile');
        $this->router->post('/files/mkdir', 'FileManager\\FileManagerController@createFile');
        $this->router->post('/files/rename', 'FileManager\\FileManagerController@rename');
        $this->router->post('/filemanager/rename', 'FileManager\\FileManagerController@rename');
        $this->router->post('/files/delete', 'FileManager\\FileManagerController@deleteFile');
        $this->router->post('/filemanager/delete', 'FileManager\\FileManagerController@deleteFile');
        $this->router->post('/files/copy', 'FileManager\\FileManagerController@copy');
        $this->router->post('/filemanager/copy', 'FileManager\\FileManagerController@copy');
        $this->router->post('/files/move', 'FileManager\\FileManagerController@move');
        $this->router->post('/filemanager/move', 'FileManager\\FileManagerController@move');
        $this->router->post('/files/compress', 'FileManager\\FileManagerController@compress');
        $this->router->post('/filemanager/compress', 'FileManager\\FileManagerController@compress');
        $this->router->post('/files/extract', 'FileManager\\FileManagerController@extract');
        $this->router->post('/filemanager/extract', 'FileManager\\FileManagerController@extract');
        $this->router->post('/files/permissions', 'FileManager\\FileManagerController@permissions');
        $this->router->post('/filemanager/permissions', 'FileManager\\FileManagerController@permissions');
        $this->router->post('/files/upload-ajax', 'FileManager\\FileManagerController@uploadAjax');
        $this->router->post('/filemanager/upload-ajax', 'FileManager\\FileManagerController@uploadAjax');

        // Backup (views use /backups plural, keep /backup too)
        $this->router->get('/backup', 'Backup\\BackupController@index');
        $this->router->post('/backups', 'Backup\\BackupController@create');
        $this->router->post('/backup/create', 'Backup\\BackupController@create');
        $this->router->get('/backups/{id}/download', 'Backup\\BackupController@download');
        $this->router->get('/backup/download/{id}', 'Backup\\BackupController@download');
        $this->router->post('/backups/{id}/restore', 'Backup\\BackupController@restore');
        $this->router->post('/backups/{id}/delete', 'Backup\\BackupController@delete');
        $this->router->post('/backups/schedules', 'Backup\\BackupController@createSchedule');
        $this->router->post('/backups/schedules/{id}/delete', 'Backup\\BackupController@deleteSchedule');

        // Firewall (views use /firewall/rules and /firewall/unblock/{id})
        $this->router->get('/firewall', 'Firewall\\FirewallController@index');
        $this->router->post('/firewall/rules', 'Firewall\\FirewallController@addRule');
        $this->router->post('/firewall/rules/{id}/delete', 'Firewall\\FirewallController@deleteRule');
        $this->router->post('/firewall/block', 'Firewall\\FirewallController@blockIp');
        $this->router->post('/firewall/unblock/{id}', 'Firewall\\FirewallController@unblockIp');

        // Monitoring
        $this->router->get('/monitoring', 'Monitoring\\MonitoringController@index');
        $this->router->get('/monitoring/api', 'Monitoring\\MonitoringController@api');
        $this->router->get('/monitoring/processes', 'Monitoring\\MonitoringController@processes');
        $this->router->get('/monitoring/logs', 'Monitoring\\MonitoringController@logs');
        $this->router->post('/monitoring/processes/{pid}/kill', 'Monitoring\\MonitoringController@killProcess');

        // Cron Jobs (views POST to /cron directly)
        $this->router->get('/cron', 'Cron\\CronController@index');
        $this->router->post('/cron', 'Cron\\CronController@create');
        $this->router->post('/cron/{id}/toggle', 'Cron\\CronController@toggle');
        $this->router->post('/cron/{id}/delete', 'Cron\\CronController@delete');

        // FTP (views POST to /ftp directly)
        $this->router->get('/ftp', 'FTP\\FTPController@index');
        $this->router->post('/ftp', 'FTP\\FTPController@create');
        $this->router->post('/ftp/{id}/toggle', 'FTP\\FTPController@toggleStatus');
        $this->router->post('/ftp/{id}/delete', 'FTP\\FTPController@delete');
        $this->router->post('/ftp/{id}/password', 'FTP\\FTPController@changePassword');

        // Settings (views POST to /settings directly)
        $this->router->get('/settings', 'Settings\\SettingsController@index');
        $this->router->post('/settings', 'Settings\\SettingsController@update');
        $this->router->get('/settings/profile', 'Settings\\SettingsController@profile');
        $this->router->post('/settings/profile', 'Settings\\SettingsController@updateProfile');
        $this->router->post('/settings/2fa/enable', 'Settings\\SettingsController@enable2FA');
        $this->router->post('/settings/2fa/disable', 'Settings\\SettingsController@disable2FA');
        $this->router->post('/settings/api-tokens', 'Settings\\SettingsController@createApiToken');
        $this->router->post('/settings/api-tokens/{id}/delete', 'Settings\\SettingsController@deleteApiToken');
        $this->router->get('/settings/services', 'Settings\\SettingsController@services');
    }

    public function run(): void
    {
        $route = $_GET['route'] ?? '';
        $route = '/' . trim($route, '/');
        $method = $_SERVER['REQUEST_METHOD'];

        // Check if user needs to authenticate
        $publicRoutes = ['/login', '/two-factor'];
        if (!in_array($route, $publicRoutes) && !$this->auth->check()) {
            header('Location: ' . $this->url('/login'));
            exit;
        }

        try {
            $this->router->dispatch($method, $route);
        } catch (\Exception $e) {
            $this->logger->error('Route dispatch error: ' . $e->getMessage());
            if ($this->config['debug'] ?? false) {
                throw $e;
            }
            http_response_code(500);
            require PANELION_ROOT . '/views/errors/500.php';
        }
    }

    public function config(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }

        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function db(): Database { return $this->db; }
    public function auth(): Auth { return $this->auth; }
    public function session(): Session { return $this->session; }
    public function logger(): Logger { return $this->logger; }
    public function router(): Router { return $this->router; }
    public function basePath(): string { return $this->basePath; }

    /**
     * Generate a URL with the base path prepended.
     */
    public function url(string $path = '/'): string
    {
        return $this->basePath . '/' . ltrim($path, '/');
    }
}
