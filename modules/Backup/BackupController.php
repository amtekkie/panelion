<?php
namespace Panelion\Modules\Backup;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class BackupController extends Controller
{
    private $db;
    private $cmd;
    private $backupDir;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $this->cmd = SystemCommand::getInstance();
        $this->backupDir = rtrim($this->app->config('paths.backup_dir', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups'), '/\\');
    }

    public function index()
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $backups = $this->db->fetchAll("SELECT b.*, u.username FROM backups b LEFT JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC");
            $schedules = $this->db->fetchAll("SELECT s.*, u.username FROM backup_schedules s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC");
        } else {
            $backups = $this->db->fetchAll("SELECT * FROM backups WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
            $schedules = $this->db->fetchAll("SELECT * FROM backup_schedules WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
        }

        $this->view('Backup/views/index', [
            'title' => 'Backups',
            'backups' => $backups,
            'schedules' => $schedules
        ]);
    }

    public function create()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $type = $this->input('type', 'full'); // full, files, databases
        $description = trim($this->input('description', ''));

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
        $username = $user['username'];

        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = "{$username}_{$type}_{$timestamp}";
            $backupPath = "{$this->backupDir}/{$username}";

            $this->cmd->execute("mkdir -p " . escapeshellarg($backupPath), true);

            // Create backup record
            $this->db->insert('backups', [
                'user_id' => $userId,
                'filename' => $backupName . '.tar.gz',
                'type' => $type,
                'size' => 0,
                'status' => 'in_progress',
                'description' => $description
            ]);
            $backupId = $this->db->lastInsertId();

            $archivePath = "{$backupPath}/{$backupName}.tar.gz";
            $tmpDir = "/tmp/panelion_backup_{$backupId}";
            $this->cmd->execute("mkdir -p " . escapeshellarg($tmpDir), true);

            // Backup files
            if (in_array($type, ['full', 'files'])) {
                $homeDir = "/home/{$username}";
                if (is_dir($homeDir)) {
                    $this->cmd->execute("cp -a " . escapeshellarg($homeDir) . " " . escapeshellarg("{$tmpDir}/files"), true);
                }
            }

            // Backup databases
            if (in_array($type, ['full', 'databases'])) {
                $this->cmd->execute("mkdir -p " . escapeshellarg("{$tmpDir}/databases"), true);
                $databases = $this->db->fetchAll("SELECT * FROM user_databases WHERE user_id = ?", [$userId]);
                foreach ($databases as $dbEntry) {
                    $dbName = $dbEntry['name'];
                    $dumpFile = "{$tmpDir}/databases/{$dbName}.sql";
                    switch ($dbEntry['type']) {
                        case 'mysql':
                        case 'mariadb':
                            $this->cmd->execute("mysqldump --single-transaction " . escapeshellarg($dbName) . " > " . escapeshellarg($dumpFile) . " 2>/dev/null", true);
                            break;
                        case 'postgresql':
                            $this->cmd->execute("pg_dump " . escapeshellarg($dbName) . " > " . escapeshellarg($dumpFile) . " 2>/dev/null", true);
                            break;
                    }
                }
            }

            // Backup email
            if ($type === 'full') {
                $mailDir = "/var/mail/vhosts";
                $domains = $this->db->fetchAll("SELECT domain FROM domains WHERE user_id = ?", [$userId]);
                foreach ($domains as $d) {
                    $domainMailDir = "{$mailDir}/{$d['domain']}";
                    if (is_dir($domainMailDir)) {
                        $this->cmd->execute("cp -a " . escapeshellarg($domainMailDir) . " " . escapeshellarg("{$tmpDir}/mail_{$d['domain']}"), true);
                    }
                }
            }

            // Backup config data (DNS, SSL, cron, etc.)
            if ($type === 'full') {
                $this->cmd->execute("mkdir -p " . escapeshellarg("{$tmpDir}/config"), true);
                // Export user's data from database
                $this->exportUserConfig($userId, "{$tmpDir}/config");
            }

            // Create tar archive
            $this->cmd->execute("cd " . escapeshellarg($tmpDir) . " && tar -czf " . escapeshellarg($archivePath) . " . 2>&1", true);

            // Clean up tmp dir
            $this->cmd->execute("rm -rf " . escapeshellarg($tmpDir), true);

            // Get file size
            $size = 0;
            if (file_exists($archivePath)) {
                $size = filesize($archivePath);
            }

            $this->db->update('backups', [
                'size' => $size,
                'status' => $size > 0 ? 'completed' : 'failed'
            ], 'id = ?', [$backupId]);

            Logger::info("Backup created: {$backupName} for user {$username}");
            $this->app->session()->flash('success', 'Backup created successfully (' . $this->formatSize($size) . ').');
        } catch (\Exception $e) {
            Logger::error("Backup failed for {$username}: " . $e->getMessage());

            if (isset($backupId)) {
                $this->db->update('backups', ['status' => 'failed'], 'id = ?', [$backupId]);
            }

            $this->app->session()->flash('danger', 'Backup failed: ' . $e->getMessage());
        }

        $this->redirect('/backups');
    }

    public function restore($id)
    {
        $this->validateCSRF();
        $backup = $this->getBackupForUser($id);
        if (!$backup) return;

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$backup['user_id']]);
        $backupPath = "{$this->backupDir}/{$user['username']}/{$backup['filename']}";

        if (!file_exists($backupPath)) {
            $this->app->session()->flash('danger', 'Backup file not found.');
            $this->redirect('/backups');
            return;
        }

        try {
            $tmpDir = "/tmp/panelion_restore_" . bin2hex(random_bytes(8));
            $this->cmd->execute("mkdir -p " . escapeshellarg($tmpDir), true);
            $this->cmd->execute("cd " . escapeshellarg($tmpDir) . " && tar -xzf " . escapeshellarg($backupPath) . " 2>&1", true);

            // Restore files
            if (is_dir("{$tmpDir}/files")) {
                $homeDir = "/home/{$user['username']}";
                $this->cmd->execute("rsync -a " . escapeshellarg("{$tmpDir}/files/") . " " . escapeshellarg($homeDir) . "/ 2>&1", true);
                $this->cmd->execute("chown -R {$user['username']}:{$user['username']} " . escapeshellarg($homeDir), true);
            }

            // Restore databases
            if (is_dir("{$tmpDir}/databases")) {
                $dumpFiles = glob("{$tmpDir}/databases/*.sql");
                foreach ($dumpFiles as $dumpFile) {
                    $dbName = pathinfo($dumpFile, PATHINFO_FILENAME);
                    $this->cmd->execute("mysql " . escapeshellarg($dbName) . " < " . escapeshellarg($dumpFile) . " 2>/dev/null", true);
                }
            }

            // Clean up
            $this->cmd->execute("rm -rf " . escapeshellarg($tmpDir), true);

            Logger::info("Backup restored: {$backup['filename']} for user {$user['username']}");
            $this->app->session()->flash('success', 'Backup restored successfully.');
        } catch (\Exception $e) {
            Logger::error("Restore failed: " . $e->getMessage());
            $this->app->session()->flash('danger', 'Restore failed: ' . $e->getMessage());
        }

        $this->redirect('/backups');
    }

    public function download($id)
    {
        $backup = $this->getBackupForUser($id);
        if (!$backup) return;

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$backup['user_id']]);
        $backupPath = "{$this->backupDir}/{$user['username']}/{$backup['filename']}";

        if (!file_exists($backupPath)) {
            $this->app->session()->flash('danger', 'Backup file not found.');
            $this->redirect('/backups');
            return;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
        header('Content-Length: ' . filesize($backupPath));
        header('Cache-Control: must-revalidate');
        readfile($backupPath);
        exit;
    }

    public function delete($id)
    {
        $this->validateCSRF();
        $backup = $this->getBackupForUser($id);
        if (!$backup) return;

        try {
            $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$backup['user_id']]);
            $backupPath = "{$this->backupDir}/{$user['username']}/{$backup['filename']}";

            if (file_exists($backupPath)) {
                unlink($backupPath);
            }

            $this->db->deleteFrom('backups', 'id = ?', [$id]);

            $this->app->session()->flash('success', 'Backup deleted.');
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to delete backup.');
        }

        $this->redirect('/backups');
    }

    public function createSchedule()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $frequency = $this->input('frequency'); // daily, weekly, monthly
        $type = $this->input('type', 'full');
        $retention = (int)$this->input('retention', 7);
        $time = $this->input('time', '02:00');

        if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
            $this->app->session()->flash('danger', 'Invalid frequency.');
            $this->redirect('/backups');
            return;
        }

        try {
            $this->db->insert('backup_schedules', [
                'user_id' => $userId,
                'frequency' => $frequency,
                'type' => $type,
                'retention' => max(1, min(365, $retention)),
                'time' => $time,
                'status' => 'active'
            ]);

            $dbUser = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
            $this->updateBackupCron($userId, $dbUser['username']);

            Logger::info("Backup schedule created for user {$dbUser['username']}: {$frequency} {$type}");
            $this->app->session()->flash('success', 'Backup schedule created.');
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to create schedule.');
        }

        $this->redirect('/backups');
    }

    public function deleteSchedule($id)
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        $where = $isAdmin ? 'id = ?' : 'id = ? AND user_id = ?';
        $params = $isAdmin ? [$id] : [$id, $userId];

        $this->db->deleteFrom('backup_schedules', $where, $params);

        $this->app->session()->flash('success', 'Schedule deleted.');
        $this->redirect('/backups');
    }

    // ── Private Helpers ──

    private function getBackupForUser($id)
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $backup = $this->db->fetch("SELECT * FROM backups WHERE id = ?", [$id]);
        } else {
            $backup = $this->db->fetch("SELECT * FROM backups WHERE id = ? AND user_id = ?", [$id, $userId]);
        }

        if (!$backup) {
            $this->app->session()->flash('danger', 'Backup not found.');
            $this->redirect('/backups');
            return null;
        }

        return $backup;
    }

    private function formatSize($bytes)
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    private function exportUserConfig($userId, $dir)
    {
        // Export DNS records
        $dnsData = json_encode($this->db->fetchAll("SELECT z.domain, r.name, r.type, r.content, r.ttl, r.priority FROM dns_zones z JOIN dns_records r ON r.zone_id = z.id WHERE z.user_id = ?", [$userId]), JSON_PRETTY_PRINT);
        file_put_contents("{$dir}/dns_records.json", $dnsData);

        // Export email accounts
        $emailData = json_encode($this->db->fetchAll("SELECT e.email, e.quota, d.domain FROM email_accounts e JOIN domains d ON e.domain_id = d.id WHERE e.user_id = ?", [$userId]), JSON_PRETTY_PRINT);
        file_put_contents("{$dir}/email_accounts.json", $emailData);

        // Export cron jobs
        $cronData = json_encode($this->db->fetchAll("SELECT command, schedule, status FROM cron_jobs WHERE user_id = ?", [$userId]), JSON_PRETTY_PRINT);
        file_put_contents("{$dir}/cron_jobs.json", $cronData);

        // Export domain list
        $domainData = json_encode($this->db->fetchAll("SELECT domain, document_root, php_version FROM domains WHERE user_id = ?", [$userId]), JSON_PRETTY_PRINT);
        file_put_contents("{$dir}/domains.json", $domainData);
    }

    private function updateBackupCron($userId, $username)
    {
        $schedules = $this->db->fetchAll("SELECT * FROM backup_schedules WHERE user_id = ? AND status = 'active'", [$userId]);

        $cronLines = "# Panelion backup schedules for {$username}\n";
        foreach ($schedules as $sched) {
            $timeParts = explode(':', $sched['time']);
            $hour = (int)($timeParts[0] ?? 2);
            $minute = (int)($timeParts[1] ?? 0);

            switch ($sched['frequency']) {
                case 'daily':
                    $cronExpr = "{$minute} {$hour} * * *";
                    break;
                case 'weekly':
                    $cronExpr = "{$minute} {$hour} * * 0";
                    break;
                case 'monthly':
                    $cronExpr = "{$minute} {$hour} 1 * *";
                    break;
                default:
                    continue 2;
            }

            $backupCmd = "/usr/local/panelion/scripts/backup.sh {$username} {$sched['type']} {$sched['retention']}";
            $cronLines .= "{$cronExpr} {$backupCmd}\n";
        }

        $cronFile = "/etc/cron.d/panelion-backup-{$username}";
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_cron_');
        file_put_contents($tmpFile, $cronLines);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($cronFile), true);
        $this->cmd->execute("chmod 644 " . escapeshellarg($cronFile), true);
        unlink($tmpFile);
    }
}
