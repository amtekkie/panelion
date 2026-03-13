<?php
namespace Panelion\Modules\Settings;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Logger;
use Panelion\Core\Security;

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

        $settings = [];
        $rows = $this->db->fetchAll("SELECT * FROM settings ORDER BY `group`, `key`");
        foreach ($rows as $row) {
            $settings[$row['group']][$row['key']] = $row['value'];
        }

        // Server info
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

        $group = $this->input('group', 'general');
        $settingsInput = $this->input('settings');

        if (!is_array($settingsInput)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid settings data.'];
            $this->redirect('/settings');
            return;
        }

        try {
            foreach ($settingsInput as $key => $value) {
                $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
                if (empty($key)) continue;

                $existing = $this->db->fetch("SELECT id FROM settings WHERE `group` = ? AND `key` = ?", [$group, $key]);
                if ($existing) {
                    $this->db->update('settings', ['value' => $value], '`group` = ? AND `key` = ?', [$group, $key]);
                } else {
                    $this->db->insert('settings', [
                        'group' => $group,
                        'key' => $key,
                        'value' => $value
                    ]);
                }
            }

            Logger::info("Settings updated: {$group}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Settings updated.'];
        } catch (\Exception $e) {
            Logger::error("Settings update failed: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update settings.'];
        }

        $this->redirect('/settings');
    }

    // ── User Profile ──

    public function profile()
    {
        $userId = $_SESSION['user_id'];
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

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
        $userId = $_SESSION['user_id'];

        $email = filter_var(trim($this->input('email')), FILTER_VALIDATE_EMAIL);
        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');

        if ($email) {
            $this->db->update('users', ['email' => $email], 'id = ?', [$userId]);
        }

        if ($currentPassword && $newPassword) {
            $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
            if (!password_verify($currentPassword, $user['password'])) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Current password is incorrect.'];
                $this->redirect('/settings/profile');
                return;
            }
            if (strlen($newPassword) < 8) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'New password must be at least 8 characters.'];
                $this->redirect('/settings/profile');
                return;
            }
            $this->db->update('users', [
                'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12])
            ], 'id = ?', [$userId]);

            Logger::info("Password changed for user ID {$userId}");
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated.'];
        $this->redirect('/settings/profile');
    }

    // ── 2FA ──

    public function enable2FA()
    {
        $this->validateCSRF();
        $userId = $_SESSION['user_id'];

        $secret = Security::generateTOTPSecret();
        $this->db->update('users', ['two_factor_secret' => $secret, 'two_factor_enabled' => 1], 'id = ?', [$userId]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => '2FA enabled. Use your authenticator app to scan the secret.'];
        $this->redirect('/settings/profile');
    }

    public function disable2FA()
    {
        $this->validateCSRF();
        $userId = $_SESSION['user_id'];

        $this->db->update('users', ['two_factor_secret' => null, 'two_factor_enabled' => 0], 'id = ?', [$userId]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => '2FA disabled.'];
        $this->redirect('/settings/profile');
    }

    // ── API Tokens ──

    public function createApiToken()
    {
        $this->validateCSRF();
        $userId = $_SESSION['user_id'];

        $name = trim($this->input('name'));
        if (empty($name)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token name is required.'];
            $this->redirect('/settings/profile');
            return;
        }

        $token = Security::generateApiKey();

        $this->db->insert('api_tokens', [
            'user_id' => $userId,
            'name' => $name,
            'token' => hash('sha256', $token)
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => "API Token created. Save this token now — it won't be shown again: <code>{$token}</code>"];
        $this->redirect('/settings/profile');
    }

    public function deleteApiToken($id)
    {
        $this->validateCSRF();
        $userId = $_SESSION['user_id'];

        $this->db->deleteFrom('api_tokens', 'id = ? AND user_id = ?', [$id, $userId]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'API token deleted.'];
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
            'php-fpm' => 'PHP-FPM',
            'named' => 'BIND DNS',
            'postfix' => 'Postfix',
            'dovecot' => 'Dovecot',
            'redis-server' => 'Redis',
            'mongod' => 'MongoDB',
            'vsftpd' => 'vsftpd',
            'fail2ban' => 'Fail2ban',
            'sshd' => 'SSH'
        ];

        $statuses = [];
        foreach ($services as $svc => $label) {
            $result = $this->cmd->execute("systemctl is-active " . escapeshellarg($svc) . " 2>/dev/null");
            $status = trim($result['output'] ?? 'unknown');
            if ($status !== 'unknown' && $status !== '') {
                $statuses[$svc] = ['label' => $label, 'status' => $status];
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

        $allowed = ['nginx', 'apache2', 'httpd', 'mysql', 'mariadb', 'postgresql', 'php-fpm', 'named', 'bind9', 'postfix', 'dovecot', 'redis-server', 'mongod', 'vsftpd', 'proftpd', 'fail2ban'];
        $allowedActions = ['start', 'stop', 'restart', 'reload'];

        if (!in_array($service, $allowed) || !in_array($action, $allowedActions)) {
            $this->json(['success' => false, 'message' => 'Invalid service or action.']);
            return;
        }

        $result = $this->cmd->execute("systemctl {$action} " . escapeshellarg($service) . " 2>&1", true);

        Logger::info("Service {$action}: {$service}");
        $this->json([
            'success' => $result['exit_code'] === 0,
            'message' => trim($result['output'] ?? 'Command executed.')
        ]);
    }
}
