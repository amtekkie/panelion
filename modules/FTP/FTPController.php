<?php
namespace Panelion\Modules\FTP;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;

class FTPController extends Controller
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
            $accounts = $this->db->fetchAll("SELECT f.*, u.username AS owner FROM ftp_accounts f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
        } else {
            $accounts = $this->db->fetchAll("SELECT * FROM ftp_accounts WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
        }

        $ftpService = $this->detectFtpService();

        $this->view('FTP/views/index', [
            'title' => 'FTP Accounts',
            'accounts' => $accounts,
            'ftpService' => $ftpService
        ]);
    }

    public function create()
    {
        $this->validateCSRF();
        $authUser = $this->app->auth()->user();
        $userId = $authUser['id'];

        $username = strtolower(trim($this->input('username')));
        $password = $this->input('password');
        $directory = trim($this->input('directory', ''));
        $quota = (int)$this->input('quota', 0);

        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
        $sysUsername = $user['username'];

        if (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
            $this->app->session()->flash('error', 'Username must be 3-32 characters, lowercase letters, numbers, and underscores.');
            $this->redirect('/ftp');
            return;
        }

        if (strlen($password) < 8) {
            $this->app->session()->flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/ftp');
            return;
        }

        $ftpUser = "{$sysUsername}_{$username}";

        $existing = $this->db->fetch("SELECT id FROM ftp_accounts WHERE username = ?", [$ftpUser]);
        if ($existing) {
            $this->app->session()->flash('error', 'FTP username already exists.');
            $this->redirect('/ftp');
            return;
        }

        $homeDir = "/home/{$sysUsername}";
        if ($directory) {
            $directory = ltrim($directory, '/');
            $directory = str_replace('..', '', $directory);
            $homeDir = "/home/{$sysUsername}/{$directory}";
        }

        try {
            $this->db->insert('ftp_accounts', [
                'user_id' => $userId,
                'username' => $ftpUser,
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'home_directory' => $homeDir,
                'quota' => $quota,
                'status' => 'active'
            ]);

            $ftpService = $this->detectFtpService();
            $this->createSystemFtpUser($ftpUser, $password, $homeDir, $quota, $ftpService);

            Logger::info("FTP account created: {$ftpUser} → {$homeDir}");
            $this->app->session()->flash('success', "FTP account {$ftpUser} created.");
        } catch (\Exception $e) {
            Logger::error("FTP account creation failed: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to create FTP account.');
        }

        $this->redirect('/ftp');
    }

    public function changePassword($id)
    {
        $this->validateCSRF();
        $account = $this->getAccountForUser($id);
        if (!$account) return;

        $password = $this->input('password');
        if (strlen($password) < 8) {
            $this->app->session()->flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/ftp');
            return;
        }

        try {
            $this->db->update('ftp_accounts', [
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
            ], 'id = ?', [$id]);

            $this->cmd->execute("echo " . escapeshellarg("{$account['username']}:{$password}") . " | chpasswd 2>/dev/null", true);

            $this->app->session()->flash('success', 'Password updated.');
        } catch (\Exception $e) {
            $this->app->session()->flash('error', 'Failed to update password.');
        }

        $this->redirect('/ftp');
    }

    public function toggleStatus($id)
    {
        $this->validateCSRF();
        $account = $this->getAccountForUser($id);
        if (!$account) return;

        $newStatus = ($account['status'] === 'active') ? 'inactive' : 'active';
        $this->db->update('ftp_accounts', ['status' => $newStatus], 'id = ?', [$id]);

        if ($newStatus === 'inactive') {
            $this->cmd->execute("usermod -L " . escapeshellarg($account['username']) . " 2>/dev/null", true);
        } else {
            $this->cmd->execute("usermod -U " . escapeshellarg($account['username']) . " 2>/dev/null", true);
        }

        $this->app->session()->flash('success', "Account " . ($newStatus === 'active' ? 'enabled' : 'disabled') . ".");
        $this->redirect('/ftp');
    }

    public function delete($id)
    {
        $this->validateCSRF();
        $account = $this->getAccountForUser($id);
        if (!$account) return;

        try {
            $this->cmd->execute("userdel " . escapeshellarg($account['username']) . " 2>/dev/null", true);
            $this->db->deleteFrom('ftp_accounts', 'id = ?', [$id]);

            Logger::info("FTP account deleted: {$account['username']}");
            $this->app->session()->flash('success', "FTP account {$account['username']} deleted.");
        } catch (\Exception $e) {
            $this->app->session()->flash('error', 'Failed to delete account.');
        }

        $this->redirect('/ftp');
    }

    // ── Private Helpers ──

    private function getAccountForUser($id)
    {
        $user = $this->app->auth()->user();
        $isAdmin = ($user['role'] === 'admin');

        $account = $isAdmin
            ? $this->db->fetch("SELECT * FROM ftp_accounts WHERE id = ?", [$id])
            : $this->db->fetch("SELECT * FROM ftp_accounts WHERE id = ? AND user_id = ?", [$id, $user['id']]);

        if (!$account) {
            $this->app->session()->flash('error', 'Account not found.');
            $this->redirect('/ftp');
            return null;
        }

        return $account;
    }

    private function detectFtpService()
    {
        $services = ['vsftpd', 'proftpd', 'pure-ftpd'];
        foreach ($services as $svc) {
            $result = trim($this->cmd->execute("systemctl is-active " . escapeshellarg($svc) . " 2>/dev/null"));
            if ($result === 'active') return $svc;
        }

        foreach ($services as $svc) {
            $result = trim($this->cmd->execute("which " . escapeshellarg($svc) . " 2>/dev/null"));
            if (!empty($result)) return $svc;
        }

        return 'vsftpd';
    }

    private function createSystemFtpUser($username, $password, $homeDir, $quota, $ftpService)
    {
        $this->cmd->execute("mkdir -p " . escapeshellarg($homeDir), true);
        $this->cmd->execute("useradd -d " . escapeshellarg($homeDir) . " -s /usr/sbin/nologin " . escapeshellarg($username) . " 2>/dev/null", true);
        $this->cmd->execute("echo " . escapeshellarg("{$username}:{$password}") . " | chpasswd", true);
        $this->cmd->execute("chown " . escapeshellarg($username) . ":" . escapeshellarg($username) . " " . escapeshellarg($homeDir), true);

        switch ($ftpService) {
            case 'vsftpd':
                $userListFile = '/etc/vsftpd.userlist';
                $this->cmd->execute("echo " . escapeshellarg($username) . " >> " . escapeshellarg($userListFile), true);
                break;

            case 'proftpd':
                break;

            case 'pure-ftpd':
                $this->cmd->execute("pure-pw useradd " . escapeshellarg($username) . " -u " . escapeshellarg($username) . " -d " . escapeshellarg($homeDir) . " 2>/dev/null", true);
                $this->cmd->execute("pure-pw mkdb", true);
                break;
        }

        if ($quota > 0) {
            $quotaBytes = $quota * 1024 * 1024;
            $softLimit = $quotaBytes;
            $hardLimit = (int)($quotaBytes * 1.1);
            $this->cmd->execute("setquota -u " . escapeshellarg($username) . " {$softLimit} {$hardLimit} 0 0 / 2>/dev/null", true);
        }
    }
}
