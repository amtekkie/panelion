<?php
namespace Panelion\Modules\Firewall;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class FirewallController extends Controller
{
    private $db;
    private $cmd;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
    }

    public function index()
    {
        $this->requireAdmin();

        $rules = $this->db->fetchAll("SELECT * FROM firewall_rules ORDER BY priority ASC, created_at DESC");
        $blockedIps = $this->db->fetchAll("SELECT * FROM blocked_ips ORDER BY created_at DESC");

        // Detect firewall backend
        $backend = $this->detectBackend();
        $status = $this->getFirewallStatus($backend);

        $this->view('Firewall/views/index', [
            'title' => 'Firewall',
            'rules' => $rules,
            'blockedIps' => $blockedIps,
            'backend' => $backend,
            'firewallStatus' => $status
        ]);
    }

    public function addRule()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $protocol = $this->input('protocol', 'tcp');
        $port = $this->input('port');
        $source = $this->input('source', 'any');
        $action = $this->input('action', 'allow');
        $direction = $this->input('direction', 'in');
        $description = trim($this->input('description', ''));
        $priority = (int)$this->input('priority', 100);

        if (!$this->validatePort($port)) {
            $this->app->session()->flash('danger', 'Invalid port number or range.');
            $this->redirect('/firewall');
            return;
        }

        if (!in_array($protocol, ['tcp', 'udp', 'both'])) {
            $this->app->session()->flash('danger', 'Invalid protocol.');
            $this->redirect('/firewall');
            return;
        }

        if (!in_array($action, ['allow', 'deny'])) {
            $this->app->session()->flash('danger', 'Invalid action.');
            $this->redirect('/firewall');
            return;
        }

        if ($source !== 'any' && !filter_var($source, FILTER_VALIDATE_IP) && !$this->isValidCIDR($source)) {
            $this->app->session()->flash('danger', 'Invalid source IP or CIDR.');
            $this->redirect('/firewall');
            return;
        }

        try {
            $this->db->insert('firewall_rules', [
                'protocol' => $protocol,
                'port' => $port,
                'source' => $source,
                'action' => $action,
                'direction' => $direction,
                'description' => $description,
                'priority' => max(1, min(999, $priority)),
                'status' => 'active'
            ]);

            $this->applyRule($protocol, $port, $source, $action, $direction);

            Logger::info("Firewall rule added: {$action} {$protocol}/{$port} from {$source}");
            $this->app->session()->flash('success', 'Firewall rule added.');
        } catch (\Exception $e) {
            Logger::error("Failed to add firewall rule: " . $e->getMessage());
            $this->app->session()->flash('danger', 'Failed to add rule.');
        }

        $this->redirect('/firewall');
    }

    public function deleteRule($id)
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $rule = $this->db->fetch("SELECT * FROM firewall_rules WHERE id = ?", [$id]);
        if (!$rule) {
            $this->app->session()->flash('danger', 'Rule not found.');
            $this->redirect('/firewall');
            return;
        }

        try {
            $this->removeRule($rule);
            $this->db->deleteFrom('firewall_rules', 'id = ?', [$id]);

            Logger::info("Firewall rule deleted: {$rule['action']} {$rule['protocol']}/{$rule['port']}");
            $this->app->session()->flash('success', 'Rule deleted.');
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to delete rule.');
        }

        $this->redirect('/firewall');
    }

    public function toggleRule($id)
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $rule = $this->db->fetch("SELECT * FROM firewall_rules WHERE id = ?", [$id]);
        if (!$rule) {
            $this->json(['success' => false, 'message' => 'Rule not found.']);
            return;
        }

        $newStatus = ($rule['status'] === 'active') ? 'inactive' : 'active';
        $this->db->update('firewall_rules', ['status' => $newStatus], 'id = ?', [$id]);

        if ($newStatus === 'active') {
            $this->applyRule($rule['protocol'], $rule['port'], $rule['source'], $rule['action'], $rule['direction']);
        } else {
            $this->removeRule($rule);
        }

        $this->json(['success' => true, 'status' => $newStatus]);
    }

    public function blockIp()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $ip = trim($this->input('ip'));
        $reason = trim($this->input('reason', ''));
        $expiry = $this->input('expiry');

        if (!filter_var($ip, FILTER_VALIDATE_IP) && !$this->isValidCIDR($ip)) {
            $this->app->session()->flash('danger', 'Invalid IP address.');
            $this->redirect('/firewall');
            return;
        }

        // Prevent blocking own IP
        $serverIps = $this->getServerIPs();
        if (in_array($ip, $serverIps)) {
            $this->app->session()->flash('danger', 'Cannot block a server IP.');
            $this->redirect('/firewall');
            return;
        }

        $existing = $this->db->fetch("SELECT id FROM blocked_ips WHERE ip_address = ?", [$ip]);
        if ($existing) {
            $this->app->session()->flash('warning', 'IP already blocked.');
            $this->redirect('/firewall');
            return;
        }

        try {
            $expiresAt = null;
            if ($expiry && is_numeric($expiry)) {
                $expiresAt = date('Y-m-d H:i:s', time() + ((int)$expiry * 3600));
            }

            $this->db->insert('blocked_ips', [
                'ip_address' => $ip,
                'reason' => $reason,
                'expires_at' => $expiresAt
            ]);

            $this->applyIpBlock($ip);

            Logger::info("IP blocked: {$ip}" . ($reason ? " - {$reason}" : ''));
            $this->app->session()->flash('success', "IP {$ip} blocked.");
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to block IP.');
        }

        $this->redirect('/firewall');
    }

    public function unblockIp($id)
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $entry = $this->db->fetch("SELECT * FROM blocked_ips WHERE id = ?", [$id]);
        if ($entry) {
            $this->removeIpBlock($entry['ip_address']);
            $this->db->deleteFrom('blocked_ips', 'id = ?', [$id]);

            Logger::info("IP unblocked: {$entry['ip_address']}");
            $this->app->session()->flash('success', "IP {$entry['ip_address']} unblocked.");
        }

        $this->redirect('/firewall');
    }

    // ── Private Helpers ──

    private function detectBackend()
    {
        $result = trim($this->cmd->execute("which ufw 2>/dev/null"));
        if (!empty($result)) return 'ufw';

        $result = trim($this->cmd->execute("which firewall-cmd 2>/dev/null"));
        if (!empty($result)) return 'firewalld';

        return 'iptables';
    }

    private function getFirewallStatus($backend)
    {
        switch ($backend) {
            case 'ufw':
                return trim($this->cmd->execute("ufw status", true)) ?: 'Unknown';
            case 'firewalld':
                return trim($this->cmd->execute("firewall-cmd --state", true)) ?: 'Unknown';
            default:
                return trim($this->cmd->execute("iptables -L -n --line-numbers 2>/dev/null | head -20", true)) ?: 'Unknown';
        }
    }

    private function applyRule($protocol, $port, $source, $action, $direction)
    {
        $backend = $this->detectBackend();
        $protocols = ($protocol === 'both') ? ['tcp', 'udp'] : [$protocol];

        foreach ($protocols as $proto) {
            switch ($backend) {
                case 'ufw':
                    $cmd = "ufw ";
                    $cmd .= ($action === 'allow') ? 'allow' : 'deny';
                    $cmd .= ($direction === 'out') ? ' out' : '';
                    if ($source !== 'any') {
                        $cmd .= " from " . escapeshellarg($source);
                    }
                    $cmd .= " to any port " . escapeshellarg($port) . " proto {$proto}";
                    $this->cmd->execute($cmd, true);
                    break;

                case 'firewalld':
                    if ($action === 'allow') {
                        $this->cmd->execute("firewall-cmd --permanent --add-port=" . escapeshellarg("{$port}/{$proto}"), true);
                    } else {
                        $this->cmd->execute("firewall-cmd --permanent --add-rich-rule='rule family=ipv4 port port=\"{$port}\" protocol=\"{$proto}\" drop'", true);
                    }
                    $this->cmd->execute("firewall-cmd --reload", true);
                    break;

                default: // iptables
                    $chain = ($direction === 'out') ? 'OUTPUT' : 'INPUT';
                    $target = ($action === 'allow') ? 'ACCEPT' : 'DROP';
                    $cmd = "iptables -A {$chain} -p {$proto} --dport " . escapeshellarg($port);
                    if ($source !== 'any') {
                        $cmd .= " -s " . escapeshellarg($source);
                    }
                    $cmd .= " -j {$target}";
                    $this->cmd->execute($cmd, true);
                    break;
            }
        }
    }

    private function removeRule($rule)
    {
        $backend = $this->detectBackend();
        $protocols = ($rule['protocol'] === 'both') ? ['tcp', 'udp'] : [$rule['protocol']];

        foreach ($protocols as $proto) {
            switch ($backend) {
                case 'ufw':
                    $cmd = "ufw delete ";
                    $cmd .= ($rule['action'] === 'allow') ? 'allow' : 'deny';
                    if ($rule['source'] !== 'any') {
                        $cmd .= " from " . escapeshellarg($rule['source']);
                    }
                    $cmd .= " to any port " . escapeshellarg($rule['port']) . " proto {$proto}";
                    $this->cmd->execute($cmd . " 2>/dev/null", true);
                    break;

                case 'firewalld':
                    if ($rule['action'] === 'allow') {
                        $this->cmd->execute("firewall-cmd --permanent --remove-port=" . escapeshellarg("{$rule['port']}/{$proto}") . " 2>/dev/null", true);
                    }
                    $this->cmd->execute("firewall-cmd --reload", true);
                    break;

                default:
                    $chain = ($rule['direction'] === 'out') ? 'OUTPUT' : 'INPUT';
                    $target = ($rule['action'] === 'allow') ? 'ACCEPT' : 'DROP';
                    $cmd = "iptables -D {$chain} -p {$proto} --dport " . escapeshellarg($rule['port']);
                    if ($rule['source'] !== 'any') {
                        $cmd .= " -s " . escapeshellarg($rule['source']);
                    }
                    $cmd .= " -j {$target}";
                    $this->cmd->execute($cmd . " 2>/dev/null", true);
                    break;
            }
        }
    }

    private function applyIpBlock($ip)
    {
        $backend = $this->detectBackend();

        switch ($backend) {
            case 'ufw':
                $this->cmd->execute("ufw deny from " . escapeshellarg($ip), true);
                break;
            case 'firewalld':
                $this->cmd->execute("firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address=\"{$ip}\" drop'", true);
                $this->cmd->execute("firewall-cmd --reload", true);
                break;
            default:
                $this->cmd->execute("iptables -I INPUT -s " . escapeshellarg($ip) . " -j DROP", true);
                break;
        }
    }

    private function removeIpBlock($ip)
    {
        $backend = $this->detectBackend();

        switch ($backend) {
            case 'ufw':
                $this->cmd->execute("ufw delete deny from " . escapeshellarg($ip) . " 2>/dev/null", true);
                break;
            case 'firewalld':
                $this->cmd->execute("firewall-cmd --permanent --remove-rich-rule='rule family=ipv4 source address=\"{$ip}\" drop' 2>/dev/null", true);
                $this->cmd->execute("firewall-cmd --reload", true);
                break;
            default:
                $this->cmd->execute("iptables -D INPUT -s " . escapeshellarg($ip) . " -j DROP 2>/dev/null", true);
                break;
        }
    }

    private function getServerIPs()
    {
        $output = trim($this->cmd->execute("hostname -I 2>/dev/null"));
        $ips = array_filter(explode(' ', $output));
        $ips[] = '127.0.0.1';
        $ips[] = '::1';
        return $ips;
    }

    private function validatePort($port)
    {
        // Allow single port or range (e.g., 3000:3010)
        if (preg_match('/^\d+$/', $port)) {
            return (int)$port >= 1 && (int)$port <= 65535;
        }
        if (preg_match('/^(\d+):(\d+)$/', $port, $m)) {
            return (int)$m[1] >= 1 && (int)$m[2] <= 65535 && (int)$m[1] < (int)$m[2];
        }
        return false;
    }

    private function isValidCIDR($cidr)
    {
        return (bool)preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $cidr);
    }
}
