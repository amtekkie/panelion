<?php
namespace Panelion\Modules\Cron;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class CronController extends Controller
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
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $jobs = $this->db->fetchAll("SELECT c.*, u.username FROM cron_jobs c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
        } else {
            $jobs = $this->db->fetchAll("SELECT * FROM cron_jobs WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
        }

        $this->view('Cron/views/index', [
            'title' => 'Cron Jobs',
            'jobs' => $jobs
        ]);
    }

    public function create()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $command = trim($this->input('command'));
        $schedule = trim($this->input('schedule'));
        $description = trim($this->input('description', ''));

        // Preset schedule shortcuts
        $presets = [
            'every_minute' => '* * * * *',
            'every_5min' => '*/5 * * * *',
            'every_15min' => '*/15 * * * *',
            'every_30min' => '*/30 * * * *',
            'hourly' => '0 * * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *'
        ];
        if (isset($presets[$schedule])) {
            $schedule = $presets[$schedule];
        }

        if (empty($command)) {
            $this->app->session()->flash('danger', 'Command is required.');
            $this->redirect('/cron');
            return;
        }

        if (!$this->isValidCronSchedule($schedule)) {
            $this->app->session()->flash('danger', 'Invalid cron schedule format.');
            $this->redirect('/cron');
            return;
        }

        // Security: block dangerous commands for non-admin users
        if ($user['role'] !== 'admin') {
            $dangerous = ['rm -rf /', 'mkfs', 'dd if=', ':(){', 'shutdown', 'reboot', 'halt', 'init 0', 'init 6'];
            foreach ($dangerous as $d) {
                if (stripos($command, $d) !== false) {
                    $this->app->session()->flash('danger', 'This command is not allowed.');
                    $this->redirect('/cron');
                    return;
                }
            }
        }

        try {
            $this->db->insert('cron_jobs', [
                'user_id' => $userId,
                'command' => $command,
                'schedule' => $schedule,
                'description' => $description,
                'status' => 'active'
            ]);

            $dbUser = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
            $this->updateUserCrontab($userId, $dbUser['username']);

            Logger::info("Cron job created by {$dbUser['username']}: {$schedule} {$command}");
            $this->app->session()->flash('success', 'Cron job created.');
        } catch (\Exception $e) {
            $this->app->session()->flash('danger', 'Failed to create cron job.');
        }

        $this->redirect('/cron');
    }

    public function toggle($id)
    {
        $this->validateCSRF();
        $job = $this->getJobForUser($id);
        if (!$job) return;

        $newStatus = ($job['status'] === 'active') ? 'inactive' : 'active';
        $this->db->update('cron_jobs', ['status' => $newStatus], 'id = ?', [$id]);

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$job['user_id']]);
        $this->updateUserCrontab($job['user_id'], $user['username']);

        if ($this->isAjax()) {
            $this->json(['success' => true, 'status' => $newStatus]);
        } else {
            $this->redirect('/cron');
        }
    }

    public function delete($id)
    {
        $this->validateCSRF();
        $job = $this->getJobForUser($id);
        if (!$job) return;

        $this->db->deleteFrom('cron_jobs', 'id = ?', [$id]);

        $dbUser = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$job['user_id']]);
        $this->updateUserCrontab($job['user_id'], $dbUser['username']);

        $this->app->session()->flash('success', 'Cron job deleted.');
        $this->redirect('/cron');
    }

    // ── Private Helpers ──

    private function getJobForUser($id)
    {
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        $job = $isAdmin
            ? $this->db->fetch("SELECT * FROM cron_jobs WHERE id = ?", [$id])
            : $this->db->fetch("SELECT * FROM cron_jobs WHERE id = ? AND user_id = ?", [$id, $userId]);

        if (!$job) {
            $this->app->session()->flash('danger', 'Cron job not found.');
            $this->redirect('/cron');
            return null;
        }

        return $job;
    }

    private function isValidCronSchedule($schedule)
    {
        $parts = preg_split('/\s+/', trim($schedule));
        if (count($parts) !== 5) return false;

        foreach ($parts as $part) {
            if (!preg_match('/^(\*|(\d+(-\d+)?(,\d+(-\d+)?)*)(\/\d+)?|\*\/\d+)$/', $part)) {
                return false;
            }
        }
        return true;
    }

    private function updateUserCrontab($userId, $username)
    {
        $activeJobs = $this->db->fetchAll("SELECT * FROM cron_jobs WHERE user_id = ? AND status = 'active'", [$userId]);

        $content = "# Panelion cron jobs for {$username}\n";
        $content .= "# DO NOT EDIT MANUALLY - managed by Panelion\n";
        foreach ($activeJobs as $job) {
            if ($job['description']) {
                $content .= "# {$job['description']}\n";
            }
            $content .= "{$job['schedule']} {$job['command']}\n";
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_cron_');
        file_put_contents($tmpFile, $content);

        $this->cmd->execute("crontab -u " . escapeshellarg($username) . " " . escapeshellarg($tmpFile), true);
        unlink($tmpFile);
    }
}
