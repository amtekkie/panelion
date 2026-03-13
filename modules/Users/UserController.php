<?php
/**
 * Panelion - User Management Controller
 */

namespace Panelion\Modules\Users;

use Panelion\Core\Controller;
use Panelion\Core\Security;
use Panelion\Core\SystemCommand;

class UserController extends Controller
{
    public function index(array $params = []): void
    {
        $this->requireAdmin();
        $users = $this->app->db()->fetchAll("SELECT u.*, p.name as package_name FROM users u LEFT JOIN packages p ON u.package_id = p.id ORDER BY u.created_at DESC");
        $this->view('Users/views/index', [
            'users' => $users,
            'pageTitle' => 'User Management',
            'breadcrumbs' => [['label' => 'Users', 'active' => true]],
        ]);
    }

    public function create(array $params = []): void
    {
        $this->requireAdmin();
        $packages = $this->app->db()->fetchAll("SELECT * FROM packages WHERE is_active = 1 ORDER BY name");
        $groups = $this->app->db()->fetchAll("SELECT * FROM user_groups ORDER BY name");
        $this->view('Users/views/create', [
            'packages' => $packages,
            'groups' => $groups,
            'pageTitle' => 'Create User',
            'breadcrumbs' => [['label' => 'Users', 'url' => '/users'], ['label' => 'Create', 'active' => true]],
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->validateCSRF()) {
            $this->redirect('/users/create');
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';

        // Validation
        if (!Security::validateUsername($username)) {
            $this->app->session()->flash('error', 'Invalid username format.');
            $this->redirect('/users/create');
            return;
        }

        if (!Security::validateEmail($email)) {
            $this->app->session()->flash('error', 'Invalid email address.');
            $this->redirect('/users/create');
            return;
        }

        $passwordErrors = Security::checkPasswordStrength($password);
        if (!empty($passwordErrors)) {
            $this->app->session()->flash('error', implode(', ', $passwordErrors));
            $this->redirect('/users/create');
            return;
        }

        // Check uniqueness
        if ($this->app->db()->count('users', 'username = ?', [$username]) > 0) {
            $this->app->session()->flash('error', 'Username already exists.');
            $this->redirect('/users/create');
            return;
        }

        $allowedRoles = ['admin', 'reseller', 'user'];
        if (!in_array($role, $allowedRoles)) {
            $role = 'user';
        }

        $packageId = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
        $package = $packageId ? $this->app->db()->fetch("SELECT * FROM packages WHERE id = ?", [$packageId]) : null;

        $userId = $this->app->db()->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => Security::hashPassword($password),
            'first_name' => Security::sanitize($_POST['first_name'] ?? ''),
            'last_name' => Security::sanitize($_POST['last_name'] ?? ''),
            'role' => $role,
            'status' => 'active',
            'package_id' => $packageId,
            'max_domains' => $package['max_domains'] ?? (int)($_POST['max_domains'] ?? 1),
            'max_databases' => $package['max_databases'] ?? (int)($_POST['max_databases'] ?? 1),
            'max_email_accounts' => $package['max_email_accounts'] ?? (int)($_POST['max_email_accounts'] ?? 5),
            'max_ftp_accounts' => $package['max_ftp_accounts'] ?? (int)($_POST['max_ftp_accounts'] ?? 5),
            'max_disk_quota' => $package['max_disk_quota'] ?? (int)($_POST['max_disk_quota'] ?? 1073741824),
            'max_bandwidth' => $package['max_bandwidth'] ?? (int)($_POST['max_bandwidth'] ?? 10737418240),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create system user and home directory
        SystemCommand::exec('sudo useradd', ['-m', '-d', "/home/{$username}", '-s', '/bin/bash', $username]);
        SystemCommand::exec('sudo mkdir', ['-p', "/home/{$username}/public_html"]);
        SystemCommand::exec('sudo chown', ['-R', "{$username}:{$username}", "/home/{$username}"]);

        $this->app->logger()->info("User created: {$username} by admin {$this->app->auth()->user()['username']}");

        // Assign user groups
        $groupIds = $_POST['groups'] ?? [];
        if (empty($groupIds)) {
            // Assign default group
            $defaultGroup = $this->app->db()->fetch("SELECT id FROM user_groups WHERE is_default = 1 LIMIT 1");
            if ($defaultGroup) {
                $groupIds = [$defaultGroup['id']];
            }
        }
        foreach ((array)$groupIds as $gid) {
            $gid = (int)$gid;
            if ($gid > 0) {
                $this->app->db()->insert('user_group_members', ['user_id' => $userId, 'group_id' => $gid]);
            }
        }

        $this->app->session()->flash('success', "User '{$username}' created successfully.");
        $this->redirect('/users');
    }

    public function edit(array $params = []): void
    {
        $this->requireAdmin();
        $id = (int)($params['id'] ?? 0);
        $editUser = $this->app->db()->fetch("SELECT * FROM users WHERE id = ?", [$id]);

        if (!$editUser) {
            $this->redirect('/users');
            return;
        }

        $packages = $this->app->db()->fetchAll("SELECT * FROM packages WHERE is_active = 1 ORDER BY name");
        $groups = $this->app->db()->fetchAll("SELECT * FROM user_groups ORDER BY name");
        $userGroupIds = array_column(
            $this->app->db()->fetchAll("SELECT group_id FROM user_group_members WHERE user_id = ?", [$id]),
            'group_id'
        );
        $this->view('Users/views/edit', [
            'editUser' => $editUser,
            'packages' => $packages,
            'groups' => $groups,
            'userGroupIds' => $userGroupIds,
            'pageTitle' => "Edit User: {$editUser['username']}",
            'breadcrumbs' => [['label' => 'Users', 'url' => '/users'], ['label' => 'Edit', 'active' => true]],
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->validateCSRF()) {
            $this->redirect('/users');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $data = [
            'email' => Security::sanitize($_POST['email'] ?? ''),
            'first_name' => Security::sanitize($_POST['first_name'] ?? ''),
            'last_name' => Security::sanitize($_POST['last_name'] ?? ''),
            'role' => in_array($_POST['role'] ?? '', ['admin', 'reseller', 'user']) ? $_POST['role'] : 'user',
            'max_domains' => (int)($_POST['max_domains'] ?? 1),
            'max_databases' => (int)($_POST['max_databases'] ?? 1),
            'max_email_accounts' => (int)($_POST['max_email_accounts'] ?? 5),
            'max_disk_quota' => (int)($_POST['max_disk_quota'] ?? 1073741824),
            'max_bandwidth' => (int)($_POST['max_bandwidth'] ?? 10737418240),
        ];

        if (!empty($_POST['password'])) {
            $data['password'] = Security::hashPassword($_POST['password']);
        }

        $this->app->db()->update('users', $data, 'id = ?', [$id]);

        // Sync user groups
        $this->app->db()->deleteFrom('user_group_members', 'user_id = ?', [$id]);
        $groupIds = $_POST['groups'] ?? [];
        foreach ((array)$groupIds as $gid) {
            $gid = (int)$gid;
            if ($gid > 0) {
                $this->app->db()->insert('user_group_members', ['user_id' => $id, 'group_id' => $gid]);
            }
        }

        $this->app->session()->flash('success', 'User updated successfully.');
        $this->redirect('/users');
    }

    public function delete(array $params = []): void
    {
        $this->requireAdmin();
        if (!$this->validateCSRF()) {
            $this->redirect('/users');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $targetUser = $this->app->db()->fetch("SELECT * FROM users WHERE id = ?", [$id]);

        if (!$targetUser || $targetUser['id'] === $this->app->auth()->user()['id']) {
            $this->app->session()->flash('error', 'Cannot delete this user.');
            $this->redirect('/users');
            return;
        }

        // Remove system user
        SystemCommand::exec('sudo userdel', ['-r', $targetUser['username']]);

        $this->app->db()->deleteFrom('users', 'id = ?', [$id]);
        $this->app->logger()->info("User deleted: {$targetUser['username']}");
        $this->app->session()->flash('success', 'User deleted successfully.');
        $this->redirect('/users');
    }

    public function suspend(array $params = []): void
    {
        $this->requireAdmin();
        $id = (int)($params['id'] ?? 0);
        $this->app->db()->update('users', ['status' => 'suspended'], 'id = ?', [$id]);
        $this->app->session()->flash('success', 'User suspended.');
        $this->redirect('/users');
    }

    public function unsuspend(array $params = []): void
    {
        $this->requireAdmin();
        $id = (int)($params['id'] ?? 0);
        $this->app->db()->update('users', ['status' => 'active'], 'id = ?', [$id]);
        $this->app->session()->flash('success', 'User unsuspended.');
        $this->redirect('/users');
    }
}
