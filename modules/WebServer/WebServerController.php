<?php
/**
 * Panelion - Web Server Management Controller
 */

namespace Panelion\Modules\WebServer;

use Panelion\Core\Controller;
use Panelion\Core\SystemCommand;

class WebServerController extends Controller
{
    public function index(array $params = []): void
    {
        $this->requireAdmin();
        $webserver = $this->app->config('services.webserver', 'nginx');

        $data = [
            'webserver' => $webserver,
            'is_running' => SystemCommand::isServiceRunning($webserver),
            'vhosts' => $this->getVhosts($webserver),
            'php_versions' => $this->getInstalledPHPVersions(),
            'pageTitle' => 'Web Server',
            'breadcrumbs' => [['label' => 'Web Server', 'active' => true]],
        ];

        $this->view('WebServer/views/index', $data);
    }

    public function vhosts(array $params = []): void
    {
        $this->requireAdmin();
        $webserver = $this->app->config('services.webserver', 'nginx');
        $vhosts = $this->getVhosts($webserver);

        $this->view('WebServer/views/vhosts', [
            'vhosts' => $vhosts,
            'webserver' => $webserver,
            'pageTitle' => 'Virtual Hosts',
        ]);
    }

    public function restart(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->validateCSRF()) {
            $this->redirect('/webserver');
            return;
        }

        $webserver = $this->app->config('services.webserver', 'nginx');
        $result = SystemCommand::serviceAction($webserver, 'restart');

        if ($result['success']) {
            $this->app->session()->flash('success', ucfirst($webserver) . ' restarted successfully.');
        } else {
            $this->app->session()->flash('error', 'Failed to restart: ' . ($result['error'] ?? 'Unknown error'));
        }

        $this->redirect('/webserver');
    }

    public function phpConfig(array $params = []): void
    {
        $this->requireAdmin();
        $phpVersions = $this->getInstalledPHPVersions();
        $configs = [];

        foreach ($phpVersions as $version) {
            $iniFile = "/etc/php/{$version}/fpm/php.ini";
            if (file_exists($iniFile)) {
                $configs[$version] = [
                    'memory_limit' => $this->getPhpIniValue($iniFile, 'memory_limit'),
                    'upload_max_filesize' => $this->getPhpIniValue($iniFile, 'upload_max_filesize'),
                    'post_max_size' => $this->getPhpIniValue($iniFile, 'post_max_size'),
                    'max_execution_time' => $this->getPhpIniValue($iniFile, 'max_execution_time'),
                    'max_input_time' => $this->getPhpIniValue($iniFile, 'max_input_time'),
                    'max_input_vars' => $this->getPhpIniValue($iniFile, 'max_input_vars'),
                    'display_errors' => $this->getPhpIniValue($iniFile, 'display_errors'),
                ];
            }
        }

        $this->view('WebServer/views/php-config', [
            'phpVersions' => $phpVersions,
            'configs' => $configs,
            'pageTitle' => 'PHP Configuration',
        ]);
    }

    public function updatePhpConfig(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->validateCSRF()) {
            $this->redirect('/webserver/php');
            return;
        }

        $version = $_POST['version'] ?? '';
        $allowedVersions = $this->getInstalledPHPVersions();
        if (!in_array($version, $allowedVersions)) {
            $this->app->session()->flash('error', 'Invalid PHP version.');
            $this->redirect('/webserver/php');
            return;
        }

        $settings = [
            'memory_limit' => $_POST['memory_limit'] ?? '256M',
            'upload_max_filesize' => $_POST['upload_max_filesize'] ?? '64M',
            'post_max_size' => $_POST['post_max_size'] ?? '64M',
            'max_execution_time' => $_POST['max_execution_time'] ?? '300',
            'max_input_time' => $_POST['max_input_time'] ?? '300',
            'max_input_vars' => $_POST['max_input_vars'] ?? '5000',
        ];

        foreach ($settings as $key => $value) {
            $safeValue = preg_replace('/[^0-9MmGgKk]/', '', $value);
            SystemCommand::exec('sudo sed', ['-i', "s/^{$key} =.*/{$key} = {$safeValue}/", "/etc/php/{$version}/fpm/php.ini"]);
        }

        SystemCommand::serviceAction("php{$version}-fpm", 'restart');
        $this->app->session()->flash('success', "PHP {$version} configuration updated.");
        $this->redirect('/webserver/php');
    }

    public function createVhost(array $params = []): void
    {
        $this->requireAdmin();
        $this->app->session()->flash('success', 'Use Domain Management to create virtual hosts.');
        $this->redirect('/domains/create');
    }

    public function updateVhost(array $params = []): void { $this->redirect('/webserver'); }
    public function deleteVhost(array $params = []): void { $this->redirect('/webserver'); }

    private function getVhosts(string $webserver): array
    {
        $vhosts = [];
        if ($webserver === 'nginx') {
            $dir = '/etc/nginx/sites-available/';
        } else {
            $dir = '/etc/apache2/sites-available/';
        }

        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $enabled = false;
                if ($webserver === 'nginx') {
                    $enabled = file_exists("/etc/nginx/sites-enabled/{$file}");
                } else {
                    $enabled = file_exists("/etc/apache2/sites-enabled/{$file}");
                }
                $vhosts[] = ['name' => $file, 'enabled' => $enabled];
            }
        }

        return $vhosts;
    }

    private function getInstalledPHPVersions(): array
    {
        $versions = [];
        $result = SystemCommand::exec('ls', ['/etc/php/']);
        if ($result['success']) {
            foreach (explode("\n", trim($result['output'])) as $dir) {
                if (preg_match('/^\d+\.\d+$/', $dir)) {
                    $versions[] = $dir;
                }
            }
        }
        return $versions ?: ['8.2'];
    }

    private function getPhpIniValue(string $file, string $key): string
    {
        $result = SystemCommand::exec('grep', ["^{$key}", $file]);
        if ($result['success'] && preg_match("/{$key}\s*=\s*(.+)/", $result['output'], $m)) {
            return trim($m[1]);
        }
        return 'N/A';
    }
}
