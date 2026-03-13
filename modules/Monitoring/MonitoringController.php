<?php
namespace Panelion\Modules\Monitoring;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;

class MonitoringController extends Controller
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

        $stats = [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'load' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
            'hostname' => gethostname(),
            'os' => $this->getOsInfo(),
            'kernel' => php_uname('r'),
            'processes' => $this->getProcessCount()
        ];

        $services = $this->getServiceStatuses();
        $topProcesses = $this->getTopProcesses();
        $networkStats = $this->getNetworkStats();

        $this->view('Monitoring/views/index', [
            'title' => 'Server Monitoring',
            'stats' => $stats,
            'services' => $services,
            'topProcesses' => $topProcesses,
            'networkStats' => $networkStats
        ]);
    }

    public function api()
    {
        $this->requireAdmin();

        $this->json([
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'load' => $this->getLoadAverage(),
            'processes' => $this->getProcessCount()
        ]);
    }

    public function logs()
    {
        $this->requireAdmin();

        $logFile = $this->input('file', 'syslog');
        $lines = (int)$this->input('lines', 100);
        $lines = min(500, max(10, $lines));

        $allowedLogs = [
            'syslog' => '/var/log/syslog',
            'auth' => '/var/log/auth.log',
            'nginx_access' => '/var/log/nginx/access.log',
            'nginx_error' => '/var/log/nginx/error.log',
            'apache_access' => '/var/log/apache2/access.log',
            'apache_error' => '/var/log/apache2/error.log',
            'mysql' => '/var/log/mysql/error.log',
            'mail' => '/var/log/mail.log',
            'panelion' => PANELION_ROOT . '/storage/logs/' . date('Y-m-d') . '.log'
        ];

        $logPath = $allowedLogs[$logFile] ?? $allowedLogs['syslog'];

        $content = '';
        if (file_exists($logPath)) {
            $result = $this->cmd->execute("tail -n " . (int)$lines . " " . escapeshellarg($logPath), true);
            $content = $result['output'] ?? 'Unable to read log file.';
        } else {
            $content = "Log file not found: {$logPath}";
        }

        $this->view('Monitoring/views/logs', [
            'title' => 'Server Logs',
            'logFile' => $logFile,
            'logPath' => $logPath,
            'content' => $content,
            'lines' => $lines,
            'allowedLogs' => $allowedLogs
        ]);
    }

    public function processes()
    {
        $this->requireAdmin();

        $result = $this->cmd->execute("ps aux --sort=-%mem | head -51", true);
        $lines = array_filter(explode("\n", trim($result['output'] ?? '')));

        $processList = [];
        $header = array_shift($lines);
        foreach ($lines as $line) {
            $cols = preg_split('/\s+/', $line, 11);
            if (count($cols) >= 11) {
                $processList[] = [
                    'user' => $cols[0],
                    'pid' => $cols[1],
                    'cpu' => $cols[2],
                    'mem' => $cols[3],
                    'vsz' => $cols[4],
                    'rss' => $cols[5],
                    'tty' => $cols[6],
                    'stat' => $cols[7],
                    'start' => $cols[8],
                    'time' => $cols[9],
                    'command' => $cols[10]
                ];
            }
        }

        $this->view('Monitoring/views/processes', [
            'title' => 'Process Manager',
            'processes' => $processList
        ]);
    }

    public function killProcess()
    {
        $this->requireAdmin();
        $this->validateCSRF();

        $pid = (int)$this->input('pid');
        if ($pid <= 1) {
            $this->json(['success' => false, 'message' => 'Invalid PID.']);
            return;
        }

        // Don't allow killing PID 1 or critical system processes
        $result = $this->cmd->execute("kill " . (int)$pid . " 2>&1", true);
        $this->json(['success' => $result['exit_code'] === 0, 'message' => trim($result['output'] ?? 'Signal sent.')]);
    }

    // ── System Info Methods ──

    private function getCpuUsage()
    {
        $result = $this->cmd->execute("top -bn1 | grep 'Cpu(s)' | awk '{print $2}'");
        $usage = (float)trim($result['output'] ?? '0');
        return round($usage, 1);
    }

    private function getMemoryUsage()
    {
        $result = $this->cmd->execute("free -b");
        $lines = explode("\n", $result['output'] ?? '');
        foreach ($lines as $line) {
            if (strpos($line, 'Mem:') === 0) {
                $parts = preg_split('/\s+/', $line);
                $total = (int)($parts[1] ?? 0);
                $used = (int)($parts[2] ?? 0);
                $available = (int)($parts[6] ?? 0);
                return [
                    'total' => $total,
                    'used' => $total - $available,
                    'free' => $available,
                    'percent' => $total > 0 ? round(($total - $available) / $total * 100, 1) : 0
                ];
            }
        }
        return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
    }

    private function getDiskUsage()
    {
        $result = $this->cmd->execute("df -B1 /");
        $lines = explode("\n", trim($result['output'] ?? ''));
        if (count($lines) >= 2) {
            $parts = preg_split('/\s+/', $lines[1]);
            $total = (int)($parts[1] ?? 0);
            $used = (int)($parts[2] ?? 0);
            $available = (int)($parts[3] ?? 0);
            return [
                'total' => $total,
                'used' => $used,
                'free' => $available,
                'percent' => $total > 0 ? round($used / $total * 100, 1) : 0
            ];
        }
        return ['total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0];
    }

    private function getLoadAverage()
    {
        $result = $this->cmd->execute("cat /proc/loadavg");
        $parts = explode(' ', trim($result['output'] ?? '0 0 0'));
        return [
            '1min' => (float)($parts[0] ?? 0),
            '5min' => (float)($parts[1] ?? 0),
            '15min' => (float)($parts[2] ?? 0)
        ];
    }

    private function getUptime()
    {
        $result = $this->cmd->execute("cat /proc/uptime");
        $seconds = (int)trim(explode(' ', $result['output'] ?? '0')[0]);
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$days}d {$hours}h {$minutes}m";
    }

    private function getOsInfo()
    {
        if (file_exists('/etc/os-release')) {
            $content = file_get_contents('/etc/os-release');
            if (preg_match('/PRETTY_NAME="([^"]+)"/', $content, $m)) {
                return $m[1];
            }
        }
        return php_uname('s') . ' ' . php_uname('r');
    }

    private function getProcessCount()
    {
        $result = $this->cmd->execute("ps aux --no-headers | wc -l");
        return (int)trim($result['output'] ?? '0');
    }

    private function getServiceStatuses()
    {
        $services = ['nginx', 'apache2', 'httpd', 'mysql', 'mariadb', 'postgresql', 'php-fpm', 'named', 'bind9', 'postfix', 'dovecot', 'redis-server', 'mongod', 'fail2ban', 'sshd'];
        $statuses = [];

        foreach ($services as $svc) {
            $result = $this->cmd->execute("systemctl is-active " . escapeshellarg($svc) . " 2>/dev/null", true);
            $status = trim($result['output'] ?? 'unknown');
            if ($status !== 'unknown' && $status !== '') {
                $statuses[$svc] = $status;
            }
        }

        return $statuses;
    }

    private function getTopProcesses($count = 10)
    {
        $result = $this->cmd->execute("ps aux --sort=-%cpu | head -" . ($count + 1));
        $lines = array_filter(explode("\n", trim($result['output'] ?? '')));
        array_shift($lines); // remove header

        $processes = [];
        foreach ($lines as $line) {
            $cols = preg_split('/\s+/', $line, 11);
            if (count($cols) >= 11) {
                $processes[] = [
                    'user' => $cols[0],
                    'pid' => $cols[1],
                    'cpu' => $cols[2],
                    'mem' => $cols[3],
                    'command' => $cols[10]
                ];
            }
        }
        return $processes;
    }

    private function getNetworkStats()
    {
        $result = $this->cmd->execute("cat /proc/net/dev");
        $lines = explode("\n", trim($result['output'] ?? ''));
        $interfaces = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false && !str_contains($line, '|')) {
                $parts = preg_split('/[\s:]+/', trim($line));
                $name = $parts[0];
                if ($name === 'lo') continue;
                $interfaces[$name] = [
                    'rx_bytes' => (int)($parts[1] ?? 0),
                    'rx_packets' => (int)($parts[2] ?? 0),
                    'tx_bytes' => (int)($parts[9] ?? 0),
                    'tx_packets' => (int)($parts[10] ?? 0)
                ];
            }
        }
        return $interfaces;
    }
}
