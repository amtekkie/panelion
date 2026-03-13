<?php
/**
 * Panelion - System Command Executor
 * Secure wrapper for executing system commands
 */

namespace Panelion\Core;

class SystemCommand
{
    private static ?SystemCommand $instance = null;

    private static array $allowedCommands = [
        'systemctl', 'service', 'nginx', 'apache2', 'apachectl', 'httpd',
        'mysql', 'mysqladmin', 'mariadb', 'pg_dump', 'psql', 'createdb', 'dropdb',
        'mongosh', 'redis-cli', 'redis-server',
        'certbot', 'openssl',
        'useradd', 'userdel', 'usermod', 'passwd', 'chown', 'chmod',
        'tar', 'gzip', 'gunzip', 'zip', 'unzip',
        'df', 'du', 'free', 'top', 'ps', 'uptime', 'hostname', 'whoami',
        'ufw', 'iptables', 'firewall-cmd', 'fail2ban-client',
        'named-checkconf', 'named-checkzone', 'rndc',
        'postconf', 'dovecot', 'doveadm',
        'php', 'node', 'npm', 'npx', 'python3', 'pip3', 'ruby', 'gem', 'go', 'cargo',
        'git', 'composer', 'wp',
        'cat', 'ls', 'cp', 'mv', 'rm', 'mkdir', 'rmdir', 'ln', 'find', 'grep',
        'crontab', 'at',
        'proftpd', 'ftpasswd',
        'lsb_release', 'hostnamectl', 'timedatectl',
        'pm2', 'supervisorctl', 'gunicorn', 'uvicorn',
        'docker', 'docker-compose',
        'curl', 'wget',
    ];

    /**
     * Get singleton instance for controllers that use $this->cmd->execute()
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Instance method wrapper: execute(command, useSudo)
     */
    public function execute(string $command, bool $useSudo = false): string
    {
        $result = $useSudo ? self::sudo($command) : self::exec($command);
        return $result['output'] ?? '';
    }

    /**
     * Execute a system command safely
     */
    public static function exec(string $command, array $args = [], ?string $workDir = null): array
    {
        $baseCommand = self::getBaseCommand($command);
        if (!in_array($baseCommand, self::$allowedCommands)) {
            App::getInstance()->logger()->error("Blocked command execution: {$command}");
            return [
                'success' => false,
                'output' => '',
                'error' => 'Command not allowed: ' . $baseCommand,
                'code' => -1,
            ];
        }

        // Build safe command with escaped arguments
        $safeArgs = array_map('escapeshellarg', $args);
        $fullCommand = $command . ' ' . implode(' ', $safeArgs);

        // Set working directory
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => '/root',
            'LANG' => 'en_US.UTF-8',
        ];

        $process = proc_open($fullCommand, $descriptorSpec, $pipes, $workDir, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Failed to execute command',
                'code' => -1,
            ];
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        App::getInstance()->logger()->debug("Command executed: {$fullCommand}", [
            'exit_code' => $exitCode,
            'output_length' => strlen($output),
        ]);

        return [
            'success' => $exitCode === 0,
            'output' => $output,
            'error' => $error,
            'code' => $exitCode,
        ];
    }

    /**
     * Execute a command using sudo
     */
    public static function sudo(string $command, array $args = [], ?string $workDir = null): array
    {
        return self::exec('sudo ' . $command, $args, $workDir);
    }

    /**
     * Execute a service management command
     */
    public static function serviceAction(string $service, string $action): array
    {
        $allowedActions = ['start', 'stop', 'restart', 'reload', 'status', 'enable', 'disable'];
        if (!in_array($action, $allowedActions)) {
            return ['success' => false, 'error' => 'Invalid service action'];
        }

        $service = preg_replace('/[^a-zA-Z0-9._-]/', '', $service);
        return self::exec("sudo systemctl {$action}", [$service]);
    }

    /**
     * Check if a service is running
     */
    public static function isServiceRunning(string $service): bool
    {
        $service = preg_replace('/[^a-zA-Z0-9._-]/', '', $service);
        $result = self::exec("systemctl is-active", [$service]);
        return trim($result['output']) === 'active';
    }

    /**
     * Get the base command from a full command string
     */
    private static function getBaseCommand(string $command): string
    {
        // Remove sudo prefix
        $command = preg_replace('/^sudo\s+/', '', $command);
        // Get the first word (the actual command)
        $parts = preg_split('/\s+/', $command);
        return basename($parts[0]);
    }

    /**
     * Get OS information
     */
    public static function getOSInfo(): array
    {
        $info = [
            'os' => PHP_OS,
            'hostname' => gethostname(),
            'kernel' => php_uname('r'),
            'arch' => php_uname('m'),
        ];

        // Detect distro
        if (file_exists('/etc/os-release')) {
            $osRelease = parse_ini_file('/etc/os-release');
            $info['distro'] = $osRelease['ID'] ?? 'unknown';
            $info['distro_name'] = $osRelease['PRETTY_NAME'] ?? 'Unknown';
            $info['distro_version'] = $osRelease['VERSION_ID'] ?? '';
        }

        return $info;
    }

    /**
     * Check if running on a supported OS
     */
    public static function isSupportedOS(): bool
    {
        $info = self::getOSInfo();
        $supported = ['ubuntu', 'centos', 'almalinux', 'rocky', 'debian'];
        return in_array($info['distro'] ?? '', $supported);
    }

    /**
     * Get system resource usage
     */
    public static function getSystemStats(): array
    {
        $stats = [];

        // CPU
        if (file_exists('/proc/loadavg')) {
            $load = explode(' ', file_get_contents('/proc/loadavg'));
            $stats['cpu'] = [
                'load_1' => (float) $load[0],
                'load_5' => (float) $load[1],
                'load_15' => (float) $load[2],
                'cores' => (int) trim(shell_exec('nproc') ?: '1'),
            ];
        }

        // Memory
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            $totalKB = (int) ($total[1] ?? 0);
            $availKB = (int) ($available[1] ?? 0);
            $stats['memory'] = [
                'total' => $totalKB * 1024,
                'available' => $availKB * 1024,
                'used' => ($totalKB - $availKB) * 1024,
                'percent' => $totalKB > 0 ? round((($totalKB - $availKB) / $totalKB) * 100, 1) : 0,
            ];
        }

        // Disk
        $stats['disk'] = [
            'total' => disk_total_space('/'),
            'free' => disk_free_space('/'),
            'used' => disk_total_space('/') - disk_free_space('/'),
            'percent' => round(((disk_total_space('/') - disk_free_space('/')) / disk_total_space('/')) * 100, 1),
        ];

        // Uptime
        if (file_exists('/proc/uptime')) {
            $uptime = (float) explode(' ', file_get_contents('/proc/uptime'))[0];
            $stats['uptime'] = [
                'seconds' => $uptime,
                'human' => self::formatUptime($uptime),
            ];
        }

        return $stats;
    }

    private static function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";

        return implode(' ', $parts);
    }
}
