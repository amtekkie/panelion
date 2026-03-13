<?php
/**
 * Panelion - Domain Management Controller
 */

namespace Panelion\Modules\Domains;

use Panelion\Core\Controller;
use Panelion\Core\Security;
use Panelion\Core\SystemCommand;

class DomainController extends Controller
{
    public function index(array $params = []): void
    {
        $user = $this->app->auth()->user();
        $where = $user['role'] === 'admin' ? '1=1' : 'user_id = ?';
        $whereParams = $user['role'] === 'admin' ? [] : [$user['id']];

        $domains = $this->app->db()->fetchAll(
            "SELECT d.*, u.username FROM domains d JOIN users u ON d.user_id = u.id WHERE {$where} ORDER BY d.domain",
            $whereParams
        );

        $this->view('Domains/views/index', [
            'domains' => $domains,
            'pageTitle' => 'Domains',
            'breadcrumbs' => [['label' => 'Domains', 'active' => true]],
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('Domains/views/create', [
            'phpVersions' => $this->app->config('runtimes.php', []),
            'pageTitle' => 'Add Domain',
            'breadcrumbs' => [['label' => 'Domains', 'url' => '/domains'], ['label' => 'Add', 'active' => true]],
        ]);
    }

    public function store(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/domains/create');
            return;
        }

        $user = $this->app->auth()->user();
        $domain = strtolower(trim($_POST['domain'] ?? ''));
        $phpVersion = $_POST['php_version'] ?? '8.2';

        if (!Security::validateDomain($domain)) {
            $this->app->session()->flash('error', 'Invalid domain name.');
            $this->redirect('/domains/create');
            return;
        }

        // Check domain limit
        if ($user['max_domains'] != -1) {
            $count = $this->app->db()->count('domains', 'user_id = ?', [$user['id']]);
            if ($count >= $user['max_domains']) {
                $this->app->session()->flash('error', 'Domain limit reached.');
                $this->redirect('/domains');
                return;
            }
        }

        // Check if domain exists
        if ($this->app->db()->count('domains', 'domain = ?', [$domain]) > 0) {
            $this->app->session()->flash('error', 'Domain already exists.');
            $this->redirect('/domains/create');
            return;
        }

        $docRoot = "/home/{$user['username']}/public_html/{$domain}";

        $domainId = $this->app->db()->insert('domains', [
            'user_id' => $user['id'],
            'domain' => $domain,
            'type' => 'primary',
            'document_root' => $docRoot,
            'php_version' => $phpVersion,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create document root
        SystemCommand::exec('sudo mkdir', ['-p', $docRoot]);
        SystemCommand::exec('sudo chown', ['-R', "{$user['username']}:{$user['username']}", $docRoot]);

        // Create default index.html
        $indexContent = "<!DOCTYPE html><html><head><title>Welcome to {$domain}</title></head><body><h1>Welcome to {$domain}</h1><p>Your site is ready.</p></body></html>";
        SystemCommand::exec('sudo bash', ['-c', "echo " . escapeshellarg($indexContent) . " > {$docRoot}/index.html"]);
        SystemCommand::exec('sudo chown', ["{$user['username']}:{$user['username']}", "{$docRoot}/index.html"]);

        // Generate web server config
        $this->generateVhostConfig($domain, $docRoot, $phpVersion);

        // Create DNS zone
        $this->createDNSZone($domainId, $domain);

        $this->app->logger()->info("Domain created: {$domain} for user {$user['username']}");
        $this->app->session()->flash('success', "Domain '{$domain}' created successfully.");
        $this->redirect('/domains');
    }

    public function edit(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) {
            $this->redirect('/domains');
            return;
        }

        $this->view('Domains/views/edit', [
            'domain' => $domain,
            'phpVersions' => $this->app->config('runtimes.php', []),
            'pageTitle' => "Edit: {$domain['domain']}",
            'breadcrumbs' => [['label' => 'Domains', 'url' => '/domains'], ['label' => 'Edit', 'active' => true]],
        ]);
    }

    public function update(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/domains');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) {
            $this->redirect('/domains');
            return;
        }

        $phpVersion = $_POST['php_version'] ?? $domain['php_version'];
        $this->app->db()->update('domains', ['php_version' => $phpVersion], 'id = ?', [$id]);

        // Regenerate vhost config
        $this->generateVhostConfig($domain['domain'], $domain['document_root'], $phpVersion);

        $this->app->session()->flash('success', 'Domain updated successfully.');
        $this->redirect('/domains');
    }

    public function delete(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/domains');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) {
            $this->redirect('/domains');
            return;
        }

        // Remove vhost config
        $webserver = $this->app->config('services.webserver', 'nginx');
        if ($webserver === 'nginx') {
            SystemCommand::exec('sudo rm', ["-f", "/etc/nginx/sites-enabled/{$domain['domain']}.conf"]);
            SystemCommand::exec('sudo rm', ["-f", "/etc/nginx/sites-available/{$domain['domain']}.conf"]);
            SystemCommand::serviceAction('nginx', 'reload');
        } else {
            SystemCommand::exec('sudo rm', ["-f", "/etc/apache2/sites-enabled/{$domain['domain']}.conf"]);
            SystemCommand::exec('sudo rm', ["-f", "/etc/apache2/sites-available/{$domain['domain']}.conf"]);
            SystemCommand::serviceAction('apache2', 'reload');
        }

        $this->app->db()->deleteFrom('domains', 'id = ?', [$id]);
        $this->app->logger()->info("Domain deleted: {$domain['domain']}");
        $this->app->session()->flash('success', "Domain '{$domain['domain']}' deleted.");
        $this->redirect('/domains');
    }

    public function subdomains(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) {
            $this->redirect('/domains');
            return;
        }

        $subdomains = $this->app->db()->fetchAll("SELECT * FROM domains WHERE parent_domain_id = ? AND type = 'subdomain'", [$id]);
        $this->view('Domains/views/subdomains', [
            'domain' => $domain,
            'subdomains' => $subdomains,
            'pageTitle' => "Subdomains: {$domain['domain']}",
        ]);
    }

    public function createSubdomain(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/domains');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) {
            $this->redirect('/domains');
            return;
        }

        $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/', $subdomain)) {
            $this->app->session()->flash('error', 'Invalid subdomain name.');
            $this->redirect("/domains/subdomains/{$id}");
            return;
        }

        $fullDomain = "{$subdomain}.{$domain['domain']}";
        $user = $this->app->auth()->user();
        $docRoot = "/home/{$user['username']}/public_html/{$fullDomain}";

        $this->app->db()->insert('domains', [
            'user_id' => $user['id'],
            'domain' => $fullDomain,
            'type' => 'subdomain',
            'parent_domain_id' => $id,
            'document_root' => $docRoot,
            'php_version' => $domain['php_version'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        SystemCommand::exec('sudo mkdir', ['-p', $docRoot]);
        SystemCommand::exec('sudo chown', ['-R', "{$user['username']}:{$user['username']}", $docRoot]);
        $this->generateVhostConfig($fullDomain, $docRoot, $domain['php_version']);

        $this->app->session()->flash('success', "Subdomain '{$fullDomain}' created.");
        $this->redirect("/domains/subdomains/{$id}");
    }

    public function aliases(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) { $this->redirect('/domains'); return; }

        $aliases = $this->app->db()->fetchAll("SELECT * FROM domains WHERE parent_domain_id = ? AND type = 'alias'", [$id]);
        $this->view('Domains/views/aliases', ['domain' => $domain, 'aliases' => $aliases, 'pageTitle' => "Aliases: {$domain['domain']}"]);
    }

    public function redirects(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $domain = $this->getDomainForUser($id);
        if (!$domain) { $this->redirect('/domains'); return; }
        $this->view('Domains/views/redirects', ['domain' => $domain, 'pageTitle' => "Redirects: {$domain['domain']}"]);
    }

    private function getDomainForUser(int $id): ?array
    {
        $user = $this->app->auth()->user();
        if ($user['role'] === 'admin') {
            return $this->app->db()->fetch("SELECT * FROM domains WHERE id = ?", [$id]);
        }
        return $this->app->db()->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    }

    private function generateVhostConfig(string $domain, string $docRoot, string $phpVersion): void
    {
        $webserver = $this->app->config('services.webserver', 'nginx');

        if ($webserver === 'nginx') {
            $config = "server {\n";
            $config .= "    listen 80;\n";
            $config .= "    listen [::]:80;\n";
            $config .= "    server_name {$domain} www.{$domain};\n";
            $config .= "    root {$docRoot};\n";
            $config .= "    index index.php index.html index.htm;\n\n";
            $config .= "    access_log /var/log/nginx/{$domain}.access.log;\n";
            $config .= "    error_log /var/log/nginx/{$domain}.error.log;\n\n";
            $config .= "    location / {\n";
            $config .= "        try_files \$uri \$uri/ /index.php?\$query_string;\n";
            $config .= "    }\n\n";
            $config .= "    location ~ \\.php$ {\n";
            $config .= "        fastcgi_pass unix:/run/php/php{$phpVersion}-fpm.sock;\n";
            $config .= "        fastcgi_index index.php;\n";
            $config .= "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
            $config .= "        include fastcgi_params;\n";
            $config .= "    }\n\n";
            $config .= "    location ~ /\\.ht {\n";
            $config .= "        deny all;\n";
            $config .= "    }\n";
            $config .= "}\n";

            $configFile = "/etc/nginx/sites-available/{$domain}.conf";
            SystemCommand::exec('sudo bash', ['-c', "echo " . escapeshellarg($config) . " > {$configFile}"]);
            SystemCommand::exec('sudo ln', ['-sf', $configFile, "/etc/nginx/sites-enabled/{$domain}.conf"]);
            SystemCommand::serviceAction('nginx', 'reload');
        } else {
            $config = "<VirtualHost *:80>\n";
            $config .= "    ServerName {$domain}\n";
            $config .= "    ServerAlias www.{$domain}\n";
            $config .= "    DocumentRoot {$docRoot}\n";
            $config .= "    <Directory {$docRoot}>\n";
            $config .= "        AllowOverride All\n";
            $config .= "        Require all granted\n";
            $config .= "    </Directory>\n";
            $config .= "    ErrorLog \${APACHE_LOG_DIR}/{$domain}-error.log\n";
            $config .= "    CustomLog \${APACHE_LOG_DIR}/{$domain}-access.log combined\n";
            $config .= "    <FilesMatch \\.php$>\n";
            $config .= "        SetHandler \"proxy:unix:/run/php/php{$phpVersion}-fpm.sock|fcgi://localhost\"\n";
            $config .= "    </FilesMatch>\n";
            $config .= "</VirtualHost>\n";

            $configFile = "/etc/apache2/sites-available/{$domain}.conf";
            SystemCommand::exec('sudo bash', ['-c', "echo " . escapeshellarg($config) . " > {$configFile}"]);
            SystemCommand::exec('sudo a2ensite', ["{$domain}.conf"]);
            SystemCommand::serviceAction('apache2', 'reload');
        }
    }

    private function createDNSZone(int $domainId, string $domain): void
    {
        $zoneId = $this->app->db()->insert('dns_zones', [
            'domain_id' => $domainId,
            'domain' => $domain,
            'ttl' => 3600,
            'soa_email' => "admin.{$domain}",
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Default records
        $defaultRecords = [
            ['name' => '@', 'type' => 'A', 'content' => $_SERVER['SERVER_ADDR'] ?? '0.0.0.0', 'ttl' => 3600],
            ['name' => 'www', 'type' => 'CNAME', 'content' => $domain . '.', 'ttl' => 3600],
            ['name' => '@', 'type' => 'MX', 'content' => "mail.{$domain}.", 'ttl' => 3600, 'priority' => 10],
            ['name' => 'mail', 'type' => 'A', 'content' => $_SERVER['SERVER_ADDR'] ?? '0.0.0.0', 'ttl' => 3600],
        ];

        foreach ($defaultRecords as $record) {
            $this->app->db()->insert('dns_records', array_merge($record, [
                'zone_id' => $zoneId,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }
}
