<?php
namespace Panelion\Modules\Settings;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;
use Panelion\Core\Security;
use Panelion\Core\License;

class SettingsController extends Controller
{
    private $db;
    private $cmd;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
    }

    // ── Admin Panel Settings ──

    public function index()
    {
        $this->requireAdmin();

        // Settings table has no group column — keys are flat.
        // We build grouped arrays from key prefixes for the view.
        $rows = $this->db->fetchAll("SELECT * FROM settings ORDER BY `key`");
        $settings = [];
        $flat = [];
        foreach ($rows as $row) {
            $flat[$row['key']] = $row['value'];
        }

        // Map flat keys to view groups for backwards compatibility
        $settings['general'] = [
            'panel_name' => $flat['panel_name'] ?? 'Panelion',
            'language' => $flat['language'] ?? 'en',
            'timezone' => $flat['timezone'] ?? 'UTC',
            'maintenance_mode' => $flat['maintenance_mode'] ?? '0',
        ];
        $settings['security'] = [
            'max_login_attempts' => $flat['max_login_attempts'] ?? '5',
            'lockout_duration' => $flat['lockout_duration'] ?? '15',
            'session_timeout' => $flat['session_timeout'] ?? '30',
            'min_password_length' => $flat['min_password_length'] ?? '8',
            'force_2fa' => $flat['force_2fa'] ?? '0',
        ];
        $settings['notifications'] = [
            'admin_email' => $flat['admin_email'] ?? '',
            'email_on_login' => $flat['email_on_login'] ?? '0',
            'email_on_disk_warning' => $flat['email_on_disk_warning'] ?? '1',
            'email_on_ssl_expiry' => $flat['email_on_ssl_expiry'] ?? '1',
        ];

        $phpVersion = phpversion();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $mysqlVersion = '';
        try {
            $mysqlVersion = $this->db->fetchColumn("SELECT VERSION()");
        } catch (\Exception $e) {}

        $this->view('Settings/views/index', [
            'title' => 'Settings',
            'settings' => $settings,
            'phpVersion' => $phpVersion,
            'serverSoftware' => $serverSoftware,
            'mysqlVersion' => $mysqlVersion
        ]);
    }

    public function update()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $settingsInput = $this->input('settings');

        if (!is_array($settingsInput)) {
            $this->app->session()->flash('error', 'Invalid settings data.');
            $this->redirect('/settings');
            return;
        }

        try {
            foreach ($settingsInput as $key => $value) {
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
                if (empty($key)) continue;

                $existing = $this->db->fetch("SELECT id FROM settings WHERE `key` = ?", [$key]);
                if ($existing) {
                    $this->db->update('settings', ['value' => $value], '`key` = ?', [$key]);
                } else {
                    $this->db->insert('settings', [
                        'key' => $key,
                        'value' => $value,
                        'type' => 'string',
                    ]);
                }
            }

            Logger::info("Settings updated");
            $this->app->session()->flash('success', 'Settings updated.');
        } catch (\Exception $e) {
            Logger::error("Settings update failed: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to update settings.');
        }

        $this->redirect('/settings');
    }

    // ── User Profile ──

    public function profile()
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $loginHistory = $this->db->fetchAll(
            "SELECT * FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$userId]
        );

        $apiTokens = $this->db->fetchAll(
            "SELECT id, name, token, last_used_at, created_at FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );

        $this->view('Settings/views/profile', [
            'title' => 'Profile',
            'user' => $user,
            'loginHistory' => $loginHistory,
            'apiTokens' => $apiTokens
        ]);
    }

    public function updateProfile()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $email = filter_var(trim($this->input('email')), FILTER_VALIDATE_EMAIL);
        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');

        if ($email) {
            $this->db->update('users', ['email' => $email], 'id = ?', [$userId]);
        }

        if ($currentPassword && $newPassword) {
            $dbUser = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
            if (!password_verify($currentPassword, $dbUser['password'])) {
                $this->app->session()->flash('error', 'Current password is incorrect.');
                $this->redirect('/settings/profile');
                return;
            }
            if (strlen($newPassword) < 8) {
                $this->app->session()->flash('error', 'New password must be at least 8 characters.');
                $this->redirect('/settings/profile');
                return;
            }
            $this->db->update('users', [
                'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12])
            ], 'id = ?', [$userId]);

            Logger::info("Password changed for user ID {$userId}");
        }

        $this->app->session()->flash('success', 'Profile updated.');
        $this->redirect('/settings/profile');
    }

    // ── 2FA ──

    public function enable2FA()
    {
        $this->validateCSRF();
        $userId = $this->app->auth()->user()['id'];

        $secret = Security::generateTOTPSecret();
        $this->db->update('users', ['two_factor_secret' => $secret, 'two_factor_enabled' => 1], 'id = ?', [$userId]);

        $this->app->session()->flash('success', '2FA enabled. Use your authenticator app to scan the secret.');
        $this->redirect('/settings/profile');
    }

    public function disable2FA()
    {
        $this->validateCSRF();
        $userId = $this->app->auth()->user()['id'];

        $this->db->update('users', ['two_factor_secret' => null, 'two_factor_enabled' => 0], 'id = ?', [$userId]);

        $this->app->session()->flash('success', '2FA disabled.');
        $this->redirect('/settings/profile');
    }

    // ── API Tokens ──

    public function createApiToken()
    {
        $this->validateCSRF();
        $userId = $this->app->auth()->user()['id'];

        $name = trim($this->input('name'));
        if (empty($name)) {
            $this->app->session()->flash('error', 'Token name is required.');
            $this->redirect('/settings/profile');
            return;
        }

        $token = Security::generateApiKey();

        $this->db->insert('api_tokens', [
            'user_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $token)
        ]);

        $this->app->session()->flash('success', "API Token created. Save this token now — it won't be shown again: <code>" . htmlspecialchars($token) . "</code>");
        $this->redirect('/settings/profile');
    }

    public function deleteApiToken($id)
    {
        $this->validateCSRF();
        $userId = $this->app->auth()->user()['id'];

        $this->db->deleteFrom('api_tokens', 'id = ? AND user_id = ?', [(int)$id, $userId]);

        $this->app->session()->flash('success', 'API token deleted.');
        $this->redirect('/settings/profile');
    }

    // ── Service Management (Admin) ──

    public function services()
    {
        $this->requireAdmin();

        $services = [
            'nginx' => 'Nginx',
            'apache2' => 'Apache',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'postgresql' => 'PostgreSQL',
            'named' => 'BIND DNS',
            'postfix' => 'Postfix',
            'dovecot' => 'Dovecot',
            'redis-server' => 'Redis',
            'mongod' => 'MongoDB',
            'vsftpd' => 'vsftpd',
            'fail2ban' => 'Fail2ban',
            'sshd' => 'SSH'
        ];

        // Add PHP-FPM services dynamically
        $phpVersions = $this->getInstalledPHPVersions();
        foreach ($phpVersions as $ver) {
            $services["php{$ver}-fpm"] = "PHP {$ver} FPM";
        }

        $statuses = [];
        foreach ($services as $svc => $label) {
            $result = trim($this->cmd->execute("systemctl is-active " . escapeshellarg($svc) . " 2>/dev/null"));
            if (!empty($result) && $result !== 'unknown') {
                $statuses[$svc] = ['label' => $label, 'status' => $result];
            }
        }

        $this->json($statuses);
    }

    public function serviceAction()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $service = $this->input('service');
        $action = $this->input('action');

        $allowed = ['nginx', 'apache2', 'httpd', 'mysql', 'mariadb', 'postgresql', 'named', 'bind9', 'postfix', 'dovecot', 'redis-server', 'mongod', 'vsftpd', 'proftpd', 'fail2ban'];
        $allowedActions = ['start', 'stop', 'restart', 'reload'];

        // Also allow PHP-FPM services
        if (preg_match('/^php\d+\.\d+-fpm$/', $service)) {
            $allowed[] = $service;
        }

        if (!in_array($service, $allowed) || !in_array($action, $allowedActions)) {
            $this->json(['success' => false, 'message' => 'Invalid service or action.']);
            return;
        }

        $result = SystemCommand::exec('sudo systemctl', [$action, $service]);

        Logger::info("Service {$action}: {$service}");
        $this->json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Service action completed.' : trim($result['error'] ?? 'Command failed.')
        ]);
    }

    // ── PHP Manager (Admin) ──

    public function phpManager()
    {
        $this->requireAdmin();

        $installedVersions = $this->getInstalledPHPVersions();
        $defaultPhp = $this->db->fetch("SELECT `value` FROM settings WHERE `key` = 'default_php'");
        $defaultPhpVersion = $defaultPhp ? $defaultPhp['value'] : '8.2';

        $versionData = [];
        foreach ($installedVersions as $ver) {
            $modules = $this->getPhpModulesForVersion($ver);
            $fpmRunning = trim($this->cmd->execute("systemctl is-active php{$ver}-fpm 2>/dev/null")) === 'active';
            $versionData[$ver] = [
                'installed_modules' => $modules['installed'],
                'available_modules' => $modules['available'],
                'fpm_running' => $fpmRunning,
            ];
        }

        $this->view('Settings/views/php-manager', [
            'title' => 'PHP Manager',
            'versions' => $installedVersions,
            'versionData' => $versionData,
            'defaultPhp' => $defaultPhpVersion,
            'pageTitle' => 'PHP Manager',
            'breadcrumbs' => [
                ['label' => 'Settings', 'url' => '/settings'],
                ['label' => 'PHP Manager', 'active' => true],
            ],
        ]);
    }

    public function setDefaultPhp()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $version = $this->input('version');
        $installed = $this->getInstalledPHPVersions();

        if (!in_array($version, $installed)) {
            $this->app->session()->flash('error', "PHP {$version} is not installed.");
            $this->redirect('/settings/php');
            return;
        }

        $existing = $this->db->fetch("SELECT id FROM settings WHERE `key` = 'default_php'");
        if ($existing) {
            $this->db->update('settings', ['value' => $version], '`key` = ?', ['default_php']);
        } else {
            $this->db->insert('settings', ['key' => 'default_php', 'value' => $version, 'type' => 'string']);
        }

        // Update CLI default on the server
        SystemCommand::exec('sudo update-alternatives', ['--set', 'php', "/usr/bin/php{$version}"]);

        Logger::info("Default PHP version set to {$version}");
        $this->app->session()->flash('success', "Default PHP version set to {$version}.");
        $this->redirect('/settings/php');
    }

    public function installPhpModule()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $version = $this->input('version');
        $module = $this->input('module');

        $installed = $this->getInstalledPHPVersions();
        if (!in_array($version, $installed)) {
            $this->json(['success' => false, 'message' => 'Invalid PHP version.']);
            return;
        }

        // Sanitize module name (only allow alphanumeric, dash, underscore)
        $module = preg_replace('/[^a-z0-9_-]/', '', strtolower($module));
        if (empty($module)) {
            $this->json(['success' => false, 'message' => 'Invalid module name.']);
            return;
        }

        $package = "php{$version}-{$module}";
        $result = SystemCommand::exec('sudo apt-get install', ['-y', $package]);

        if ($result['success']) {
            // Restart PHP-FPM for this version
            SystemCommand::exec('sudo systemctl', ['restart', "php{$version}-fpm"]);
            // Reload webserver
            $webserver = $this->app->config('services.webserver', 'nginx');
            SystemCommand::exec('sudo systemctl', ['reload', $webserver]);

            Logger::info("PHP module installed: {$package}");
            $this->json(['success' => true, 'message' => "{$package} installed. PHP-FPM and web server reloaded."]);
        } else {
            $this->json(['success' => false, 'message' => "Failed to install {$package}: " . trim($result['error'] ?? $result['output'] ?? '')]);
        }
    }

    public function removePhpModule()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $version = $this->input('version');
        $module = $this->input('module');

        $installed = $this->getInstalledPHPVersions();
        if (!in_array($version, $installed)) {
            $this->json(['success' => false, 'message' => 'Invalid PHP version.']);
            return;
        }

        $module = preg_replace('/[^a-z0-9_-]/', '', strtolower($module));
        if (empty($module)) {
            $this->json(['success' => false, 'message' => 'Invalid module name.']);
            return;
        }

        // Prevent removing critical modules
        $protected = ['common', 'cli', 'fpm'];
        if (in_array($module, $protected)) {
            $this->json(['success' => false, 'message' => "Cannot remove {$module} — it is a core module."]);
            return;
        }

        $package = "php{$version}-{$module}";
        $result = SystemCommand::exec('sudo apt-get remove', ['-y', $package]);

        if ($result['success']) {
            SystemCommand::exec('sudo systemctl', ['restart', "php{$version}-fpm"]);
            $webserver = $this->app->config('services.webserver', 'nginx');
            SystemCommand::exec('sudo systemctl', ['reload', $webserver]);

            Logger::info("PHP module removed: {$package}");
            $this->json(['success' => true, 'message' => "{$package} removed. PHP-FPM and web server reloaded."]);
        } else {
            $this->json(['success' => false, 'message' => "Failed to remove {$package}."]);
        }
    }

    public function phpModulesApi()
    {
        $this->requireAdmin();

        $version = $_GET['version'] ?? '';
        $installed = $this->getInstalledPHPVersions();

        if (!in_array($version, $installed)) {
            $this->json(['error' => 'Invalid PHP version.']);
            return;
        }

        $this->json($this->getPhpModulesForVersion($version));
    }

    // ── Helpers ──

    private function getInstalledPHPVersions(): array
    {
        $versions = [];
        $result = SystemCommand::exec('ls', ['/etc/php/']);
        if ($result['success']) {
            foreach (explode("\n", trim($result['output'])) as $dir) {
                if (preg_match('/^\d+\.\d+$/', trim($dir))) {
                    $versions[] = trim($dir);
                }
            }
        }
        sort($versions);
        return $versions ?: ['8.2'];
    }

    private function getPhpModulesForVersion(string $version): array
    {
        // Get installed modules
        $output = trim($this->cmd->execute("php{$version} -m 2>/dev/null"));
        $installedRaw = array_filter(array_map('trim', explode("\n", $output)));
        $installed = [];
        $inSection = false;
        foreach ($installedRaw as $line) {
            if ($line === '[PHP Modules]') { $inSection = true; continue; }
            if ($line === '[Zend Modules]') { $inSection = false; continue; }
            if ($inSection && !empty($line)) {
                $installed[] = strtolower($line);
            }
        }

        // Get available (installable) modules from apt
        $searchOutput = trim($this->cmd->execute("apt-cache search php{$version}- 2>/dev/null | grep '^php{$version}-' | awk '{print \$1}' | sed 's/php{$version}-//' | sort"));
        $allAvailable = array_filter(array_map('trim', explode("\n", $searchOutput)));

        // Filter out already-installed packages from available list
        $available = array_values(array_diff($allAvailable, $installed));

        return [
            'installed' => array_values($installed),
            'available' => $available,
        ];
    }

    // ── License Management ──

    public function license()
    {
        $this->requireAdmin();

        $license = new License($this->db, PANELION_ROOT);
        $licenseData = $license->getLicenseData();

        $this->view('settings.license', [
            'pageTitle' => 'License',
            'licenseData' => $licenseData,
            'domain' => License::getServerDomain(),
            'isValid' => $license->isValid(),
        ]);
    }

    public function deactivateLicense()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $license = new License($this->db, PANELION_ROOT);
        $license->deactivate();

        header('Location: ' . $this->app->url('/settings/license'));
        exit;
    }
}
