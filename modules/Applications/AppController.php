<?php
namespace Panelion\Modules\Applications;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class AppController extends Controller
{
    private $db;
    private $cmd;

    private $runtimes = [
        'php' => ['name' => 'PHP', 'icon' => 'filetype-php', 'versions' => ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4']],
        'nodejs' => ['name' => 'Node.js', 'icon' => 'filetype-js', 'versions' => ['18', '20', '22']],
        'python' => ['name' => 'Python', 'icon' => 'filetype-py', 'versions' => ['3.8', '3.9', '3.10', '3.11', '3.12']],
        'ruby' => ['name' => 'Ruby', 'icon' => 'gem', 'versions' => ['3.0', '3.1', '3.2', '3.3']],
        'go' => ['name' => 'Go', 'icon' => 'code-slash', 'versions' => ['1.21', '1.22']],
        'rust' => ['name' => 'Rust', 'icon' => 'gear', 'versions' => ['stable', 'nightly']],
        'java' => ['name' => 'Java', 'icon' => 'cup-hot', 'versions' => ['17', '21']],
        'static' => ['name' => 'Static/HTML', 'icon' => 'filetype-html', 'versions' => ['—']],
        'docker' => ['name' => 'Docker', 'icon' => 'box', 'versions' => ['latest']]
    ];

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
    }

    public function index()
    {
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['role'] === 'admin');

        if ($isAdmin) {
            $applications = $this->db->fetchAll("SELECT a.*, d.domain, u.username FROM applications a LEFT JOIN domains d ON a.domain_id = d.id LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC");
        } else {
            $applications = $this->db->fetchAll("SELECT a.*, d.domain FROM applications a LEFT JOIN domains d ON a.domain_id = d.id WHERE a.user_id = ? ORDER BY a.created_at DESC", [$userId]);
        }

        // Get app status for each
        foreach ($applications as &$app) {
            $app['live_status'] = $this->getAppStatus($app);
        }

        $this->view('Applications/views/index', [
            'title' => 'Applications',
            'applications' => $applications,
            'runtimes' => $this->runtimes
        ]);
    }

    public function create()
    {
        $userId = $_SESSION['user_id'];
        $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE user_id = ? AND status = 'active' ORDER BY domain", [$userId]);

        $this->view('Applications/views/create', [
            'title' => 'Create Application',
            'domains' => $domains,
            'runtimes' => $this->runtimes
        ]);
    }

    public function store()
    {
        $this->validateCSRF();
        $userId = $_SESSION['user_id'];

        $name = trim($this->input('name'));
        $type = $this->input('type');
        $version = $this->input('version');
        $domainId = (int)$this->input('domain_id');
        $path = trim($this->input('path', '/'));
        $port = (int)$this->input('port', 0);
        $startupCommand = trim($this->input('startup_command', ''));
        $envVars = trim($this->input('env_vars', ''));

        // Validate
        if (empty($name) || empty($type)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Application name and type are required.'];
            $this->redirect('/applications/create');
            return;
        }

        if (!isset($this->runtimes[$type])) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid application type.'];
            $this->redirect('/applications/create');
            return;
        }

        // Validate name (alphanumeric, hyphens, underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Application name can only contain letters, numbers, hyphens, and underscores.'];
            $this->redirect('/applications/create');
            return;
        }

        // Validate domain belongs to user
        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        if (!$domain) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid domain selected.'];
            $this->redirect('/applications/create');
            return;
        }

        // Check limits
        $user = $this->db->fetch("SELECT u.*, p.max_applications FROM users u LEFT JOIN packages p ON u.package_id = p.id WHERE u.id = ?", [$userId]);
        if ($user['max_applications'] > 0) {
            $currentApps = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE user_id = ?", [$userId]);
            if ($currentApps >= $user['max_applications']) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Application limit reached for your package.'];
                $this->redirect('/applications/create');
                return;
            }
        }

        // Sanitize path
        $path = '/' . ltrim($path, '/');
        if (!preg_match('#^/[a-zA-Z0-9/_.-]*$#', $path)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid application path.'];
            $this->redirect('/applications/create');
            return;
        }

        // Auto-assign port for non-PHP/static apps
        if (!in_array($type, ['php', 'static']) && $port === 0) {
            $port = $this->getNextAvailablePort();
        }

        // Validate port range
        if ($port > 0 && ($port < 3000 || $port > 65535)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Port must be between 3000 and 65535.'];
            $this->redirect('/applications/create');
            return;
        }

        try {
            $appDir = "/home/{$user['username']}/apps/{$name}";

            $this->db->insert('applications', [
                'user_id' => $userId,
                'domain_id' => $domainId,
                'name' => $name,
                'type' => $type,
                'version' => $version,
                'path' => $path,
                'port' => $port,
                'startup_command' => $startupCommand,
                'env_vars' => $envVars,
                'directory' => $appDir,
                'status' => 'stopped'
            ]);

            // Create app directory
            $this->cmd->execute("mkdir -p " . escapeshellarg($appDir), true);
            $this->cmd->execute("chown -R {$user['username']}:{$user['username']} " . escapeshellarg($appDir), true);

            // Setup based on type
            $this->setupApplication($name, $type, $version, $appDir, $user['username'], $port, $startupCommand, $envVars);

            // Configure reverse proxy if needed
            if (!in_array($type, ['php', 'static']) && $port > 0) {
                $this->configureReverseProxy($domain['domain'], $path, $port);
            }

            Logger::info("Application created: {$name} ({$type}) for user {$user['username']}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Application '{$name}' created successfully."];
        } catch (\Exception $e) {
            Logger::error("Failed to create application: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to create application: ' . $e->getMessage()];
        }

        $this->redirect('/applications');
    }

    public function manage($id)
    {
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['role'] === 'admin');

        if ($isAdmin) {
            $app = $this->db->fetch("SELECT a.*, d.domain, u.username FROM applications a LEFT JOIN domains d ON a.domain_id = d.id LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?", [$id]);
        } else {
            $app = $this->db->fetch("SELECT a.*, d.domain FROM applications a LEFT JOIN domains d ON a.domain_id = d.id WHERE a.id = ? AND a.user_id = ?", [$id, $userId]);
        }

        if (!$app) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Application not found.'];
            $this->redirect('/applications');
            return;
        }

        $app['live_status'] = $this->getAppStatus($app);
        $app['logs'] = $this->getAppLogs($app);

        $this->view('Applications/views/manage', [
            'title' => 'Manage Application - ' . $app['name'],
            'app' => $app,
            'runtimes' => $this->runtimes
        ]);
    }

    public function start($id)
    {
        $this->validateCSRF();
        $app = $this->getAppForUser($id);
        if (!$app) return;

        try {
            $this->startApp($app);
            $this->db->update('applications', ['status' => 'running'], 'id = ?', [$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Application '{$app['name']}' started."];
        } catch (\Exception $e) {
            Logger::error("Failed to start app {$app['name']}: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to start application.'];
        }

        $this->redirect("/applications/{$id}");
    }

    public function stop($id)
    {
        $this->validateCSRF();
        $app = $this->getAppForUser($id);
        if (!$app) return;

        try {
            $this->stopApp($app);
            $this->db->update('applications', ['status' => 'stopped'], 'id = ?', [$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Application '{$app['name']}' stopped."];
        } catch (\Exception $e) {
            Logger::error("Failed to stop app {$app['name']}: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to stop application.'];
        }

        $this->redirect("/applications/{$id}");
    }

    public function restart($id)
    {
        $this->validateCSRF();
        $app = $this->getAppForUser($id);
        if (!$app) return;

        try {
            $this->stopApp($app);
            sleep(1);
            $this->startApp($app);
            $this->db->update('applications', ['status' => 'running'], 'id = ?', [$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Application '{$app['name']}' restarted."];
        } catch (\Exception $e) {
            Logger::error("Failed to restart app {$app['name']}: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to restart application.'];
        }

        $this->redirect("/applications/{$id}");
    }

    public function delete($id)
    {
        $this->validateCSRF();
        $app = $this->getAppForUser($id);
        if (!$app) return;

        try {
            // Stop app first
            $this->stopApp($app);

            // Remove process manager config
            $this->removeProcessConfig($app);

            // Remove reverse proxy config
            if (!in_array($app['type'], ['php', 'static'])) {
                $this->removeReverseProxy($app);
            }

            $this->db->deleteFrom('applications', 'id = ?', [$id]);

            Logger::info("Application deleted: {$app['name']}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Application '{$app['name']}' deleted."];
        } catch (\Exception $e) {
            Logger::error("Failed to delete app {$app['name']}: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete application.'];
        }

        $this->redirect('/applications');
    }

    public function logs($id)
    {
        $app = $this->getAppForUser($id);
        if (!$app) return;

        $logs = $this->getAppLogs($app);
        $this->json(['success' => true, 'logs' => $logs]);
    }

    // ── Private Helpers ──

    private function getAppForUser($id)
    {
        $userId = $_SESSION['user_id'];
        $isAdmin = ($_SESSION['role'] === 'admin');

        if ($isAdmin) {
            $app = $this->db->fetch("SELECT a.*, d.domain, u.username FROM applications a LEFT JOIN domains d ON a.domain_id = d.id LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?", [$id]);
        } else {
            $app = $this->db->fetch("SELECT a.*, d.domain, u.username FROM applications a LEFT JOIN domains d ON a.domain_id = d.id LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ? AND a.user_id = ?", [$id, $userId]);
        }

        if (!$app) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Application not found.'];
            $this->redirect('/applications');
            return null;
        }

        return $app;
    }

    private function getNextAvailablePort()
    {
        $maxPort = $this->db->fetchColumn("SELECT MAX(port) FROM applications WHERE port > 0");
        return max(3000, ($maxPort ?: 2999) + 1);
    }

    private function setupApplication($name, $type, $version, $appDir, $username, $port, $startupCommand, $envVars)
    {
        switch ($type) {
            case 'nodejs':
                $this->setupNodeApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars);
                break;
            case 'python':
                $this->setupPythonApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars);
                break;
            case 'ruby':
                $this->setupRubyApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars);
                break;
            case 'go':
            case 'rust':
            case 'java':
                $this->setupGenericApp($name, $type, $version, $appDir, $username, $port, $startupCommand, $envVars);
                break;
            case 'docker':
                $this->setupDockerApp($name, $appDir, $username, $port, $envVars);
                break;
            case 'php':
                // PHP apps use the web server directly, just set up the directory
                $this->cmd->execute("mkdir -p " . escapeshellarg("{$appDir}/public"), true);
                file_put_contents("{$appDir}/public/index.php", "<?php\necho 'Hello from {$name}';\n");
                break;
            case 'static':
                $this->cmd->execute("mkdir -p " . escapeshellarg($appDir), true);
                file_put_contents("{$appDir}/index.html", "<!DOCTYPE html>\n<html><head><title>{$name}</title></head><body><h1>Welcome to {$name}</h1></body></html>\n");
                break;
        }
    }

    private function setupNodeApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars)
    {
        // Create package.json if not exists
        $packageJson = json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'main' => 'app.js',
            'scripts' => ['start' => $startupCommand ?: "node app.js"]
        ], JSON_PRETTY_PRINT);

        file_put_contents("{$appDir}/package.json", $packageJson);

        // Create starter app.js
        $appJs = <<<JS
const http = require('http');
const port = process.env.PORT || {$port};

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end('<h1>Hello from {$name}!</h1><p>Node.js {$version} running on port ' + port + '</p>');
});

server.listen(port, () => {
    console.log(`{$name} running on port \${port}`);
});
JS;
        file_put_contents("{$appDir}/app.js", $appJs);

        // Create systemd service
        $this->createSystemdService($name, $username, $appDir, $startupCommand ?: "node app.js", $port, $envVars, "node");
    }

    private function setupPythonApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars)
    {
        // Create virtual environment
        $pythonBin = "python{$version}";
        $this->cmd->execute("cd " . escapeshellarg($appDir) . " && {$pythonBin} -m venv venv", true);

        // Create starter app
        $appPy = <<<PYTHON
from http.server import HTTPServer, SimpleHTTPRequestHandler
import os

port = int(os.environ.get('PORT', {$port}))

class Handler(SimpleHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(f'<h1>Hello from {$name}!</h1><p>Python {$version} running on port {port}</p>'.encode())

if __name__ == '__main__':
    server = HTTPServer(('0.0.0.0', port), Handler)
    print(f'{$name} running on port {port}')
    server.serve_forever()
PYTHON;
        file_put_contents("{$appDir}/app.py", $appPy);

        // Create requirements.txt
        file_put_contents("{$appDir}/requirements.txt", "# Add your dependencies here\n");

        $cmd = $startupCommand ?: "{$appDir}/venv/bin/python app.py";
        $this->createSystemdService($name, $username, $appDir, $cmd, $port, $envVars, "python");
    }

    private function setupRubyApp($name, $version, $appDir, $username, $port, $startupCommand, $envVars)
    {
        $appRb = <<<RUBY
require 'socket'

port = ENV['PORT'] || {$port}
server = TCPServer.new('0.0.0.0', port.to_i)
puts "{$name} running on port #{port}"

loop do
  client = server.accept
  request = client.gets
  client.print "HTTP/1.1 200 OK\\r\\nContent-Type: text/html\\r\\n\\r\\n"
  client.print "<h1>Hello from {$name}!</h1><p>Ruby running on port #{port}</p>"
  client.close
end
RUBY;
        file_put_contents("{$appDir}/app.rb", $appRb);
        file_put_contents("{$appDir}/Gemfile", "source 'https://rubygems.org'\n# Add your gems here\n");

        $cmd = $startupCommand ?: "ruby app.rb";
        $this->createSystemdService($name, $username, $appDir, $cmd, $port, $envVars, "ruby");
    }

    private function setupGenericApp($name, $type, $version, $appDir, $username, $port, $startupCommand, $envVars)
    {
        if (empty($startupCommand)) {
            file_put_contents("{$appDir}/README.md", "# {$name}\n\nUpload your {$type} application files here.\n\nPort: {$port}\n");
        }

        if (!empty($startupCommand)) {
            $this->createSystemdService($name, $username, $appDir, $startupCommand, $port, $envVars, $type);
        }
    }

    private function setupDockerApp($name, $appDir, $username, $port, $envVars)
    {
        $dockerfile = <<<DOCKER
FROM node:20-alpine
WORKDIR /app
COPY . .
EXPOSE {$port}
CMD ["node", "app.js"]
DOCKER;
        file_put_contents("{$appDir}/Dockerfile", $dockerfile);

        $compose = <<<YAML
version: '3.8'
services:
  {$name}:
    build: .
    ports:
      - "{$port}:{$port}"
    restart: unless-stopped
YAML;
        file_put_contents("{$appDir}/docker-compose.yml", $compose);
    }

    private function createSystemdService($name, $username, $appDir, $command, $port, $envVars, $type)
    {
        $envLines = "";
        if (!empty($envVars)) {
            foreach (explode("\n", $envVars) as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '=') !== false) {
                    $envLines .= "Environment=\"{$line}\"\n";
                }
            }
        }
        $envLines .= "Environment=\"PORT={$port}\"\n";

        $service = <<<UNIT
[Unit]
Description=Panelion App: {$name}
After=network.target

[Service]
Type=simple
User={$username}
Group={$username}
WorkingDirectory={$appDir}
ExecStart=/bin/bash -c '{$command}'
Restart=on-failure
RestartSec=5
{$envLines}
StandardOutput=append:/var/log/panelion/apps/{$name}.log
StandardError=append:/var/log/panelion/apps/{$name}.error.log

[Install]
WantedBy=multi-user.target
UNIT;

        $servicePath = "/etc/systemd/system/panelion-app-{$name}.service";
        $this->cmd->execute("mkdir -p /var/log/panelion/apps", true);

        // Write service file securely
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_');
        file_put_contents($tmpFile, $service);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($servicePath), true);
        $this->cmd->execute("chmod 644 " . escapeshellarg($servicePath), true);
        unlink($tmpFile);

        $this->cmd->execute("systemctl daemon-reload", true);
        $this->cmd->execute("systemctl enable panelion-app-{$name}", true);
    }

    private function startApp($app)
    {
        if ($app['type'] === 'docker') {
            $this->cmd->execute("cd " . escapeshellarg($app['directory']) . " && docker-compose up -d", true);
        } elseif (in_array($app['type'], ['php', 'static'])) {
            // No process to start
            return;
        } else {
            $this->cmd->execute("systemctl start panelion-app-{$app['name']}", true);
        }
    }

    private function stopApp($app)
    {
        if ($app['type'] === 'docker') {
            $this->cmd->execute("cd " . escapeshellarg($app['directory']) . " && docker-compose down", true);
        } elseif (in_array($app['type'], ['php', 'static'])) {
            return;
        } else {
            $this->cmd->execute("systemctl stop panelion-app-{$app['name']}", true);
        }
    }

    private function getAppStatus($app)
    {
        if (in_array($app['type'], ['php', 'static'])) {
            return 'active';
        }

        if ($app['type'] === 'docker') {
            $result = $this->cmd->execute("docker ps --filter name={$app['name']} --format '{{.Status}}'");
            return !empty(trim($result)) ? 'running' : 'stopped';
        }

        $result = $this->cmd->execute("systemctl is-active panelion-app-{$app['name']} 2>/dev/null");
        return trim($result) === 'active' ? 'running' : 'stopped';
    }

    private function getAppLogs($app, $lines = 100)
    {
        $logFile = "/var/log/panelion/apps/{$app['name']}.log";
        if ($app['type'] === 'docker') {
            $result = $this->cmd->execute("cd " . escapeshellarg($app['directory']) . " && docker-compose logs --tail={$lines} 2>&1");
            return $result ?: 'No logs available.';
        }

        $result = $this->cmd->execute("tail -n {$lines} " . escapeshellarg($logFile) . " 2>/dev/null");
        return $result ?: 'No logs available.';
    }

    private function removeProcessConfig($app)
    {
        if ($app['type'] === 'docker') {
            $this->cmd->execute("cd " . escapeshellarg($app['directory']) . " && docker-compose down --rmi all 2>/dev/null", true);
        } elseif (!in_array($app['type'], ['php', 'static'])) {
            $this->cmd->execute("systemctl disable panelion-app-{$app['name']} 2>/dev/null", true);
            $this->cmd->execute("rm -f /etc/systemd/system/panelion-app-{$app['name']}.service", true);
            $this->cmd->execute("systemctl daemon-reload", true);
        }
    }

    private function configureReverseProxy($domain, $path, $port)
    {
        // Add proxy pass to nginx
        $proxyConf = <<<NGINX
    location {$path} {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400;
    }
NGINX;

        $confFile = "/etc/nginx/panelion.d/{$domain}_proxy.conf";
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_');
        file_put_contents($tmpFile, $proxyConf);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($confFile), true);
        unlink($tmpFile);

        $this->cmd->execute("nginx -t && systemctl reload nginx", true);
    }

    private function removeReverseProxy($app)
    {
        if (!empty($app['domain'])) {
            $confFile = "/etc/nginx/panelion.d/{$app['domain']}_proxy.conf";
            $this->cmd->execute("rm -f " . escapeshellarg($confFile), true);
            $this->cmd->execute("nginx -t && systemctl reload nginx 2>/dev/null", true);
        }
    }
}
