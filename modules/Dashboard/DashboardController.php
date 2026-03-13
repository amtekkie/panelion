<?php
/**
 * Panelion - Dashboard Controller
 */

namespace Panelion\Modules\Dashboard;

use Panelion\Core\Controller;
use Panelion\Core\SystemCommand;

class DashboardController extends Controller
{
    public function index(array $params = []): void
    {
        $user = $this->app->auth()->user();
        $db = $this->app->db();

        // Get stats based on user role
        if ($user['role'] === 'admin') {
            $data = $this->getAdminStats($db);
        } else {
            $data = $this->getUserStats($db, $user);
        }

        // System stats
        $data['system'] = SystemCommand::getSystemStats();
        $data['pageTitle'] = 'Dashboard';
        $data['breadcrumbs'] = [];

        $this->view('Dashboard/views/index', $data);
    }

    public function stats(array $params = []): void
    {
        $data = [
            'system' => SystemCommand::getSystemStats(),
            'services' => $this->getServiceStatus(),
        ];
        $this->json($data);
    }

    private function getAdminStats($db): array
    {
        return [
            'total_users' => $db->count('users'),
            'active_users' => $db->count('users', "status = 'active'"),
            'total_domains' => $db->count('domains'),
            'total_databases' => $db->count('user_databases'),
            'total_emails' => $db->count('email_accounts'),
            'total_apps' => $db->count('applications'),
            'recent_logins' => $db->fetchAll(
                "SELECT l.*, u.username FROM login_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 10"
            ),
            'recent_users' => $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5"),
            'services' => $this->getServiceStatus(),
            'is_admin' => true,
        ];
    }

    private function getUserStats($db, $user): array
    {
        $userId = $user['id'];
        return [
            'total_domains' => $db->count('domains', 'user_id = ?', [$userId]),
            'total_databases' => $db->count('user_databases', 'user_id = ?', [$userId]),
            'total_emails' => $db->count('email_accounts', 'user_id = ?', [$userId]),
            'total_apps' => $db->count('applications', 'user_id = ?', [$userId]),
            'total_ftp' => $db->count('ftp_accounts', 'user_id = ?', [$userId]),
            'total_cron' => $db->count('cron_jobs', 'user_id = ?', [$userId]),
            'domains' => $db->fetchAll("SELECT * FROM domains WHERE user_id = ? ORDER BY domain", [$userId]),
            'disk_quota' => $user['max_disk_quota'],
            'disk_used' => $user['disk_used'],
            'bandwidth_quota' => $user['max_bandwidth'],
            'bandwidth_used' => $user['bandwidth_used'],
            'is_admin' => false,
        ];
    }

    private function getServiceStatus(): array
    {
        $services = [
            'nginx' => 'Web Server (Nginx)',
            'apache2' => 'Web Server (Apache)',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'postgresql' => 'PostgreSQL',
            'redis-server' => 'Redis',
            'mongod' => 'MongoDB',
            'postfix' => 'Mail (Postfix)',
            'dovecot' => 'Mail (Dovecot)',
            'named' => 'DNS (BIND)',
            'proftpd' => 'FTP (ProFTPD)',
            'fail2ban' => 'Fail2Ban',
        ];

        $status = [];
        foreach ($services as $service => $label) {
            $status[$service] = [
                'label' => $label,
                'running' => SystemCommand::isServiceRunning($service),
            ];
        }
        return $status;
    }
}
