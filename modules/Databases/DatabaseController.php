<?php
/**
 * Panelion - Database Management Controller
 * Supports MySQL/MariaDB, PostgreSQL, MongoDB, Redis, SQLite
 */

namespace Panelion\Modules\Databases;

use Panelion\Core\Controller;
use Panelion\Core\Security;
use Panelion\Core\SystemCommand;

class DatabaseController extends Controller
{
    public function index(array $params = []): void
    {
        $user = $this->app->auth()->user();
        $where = $user['role'] === 'admin' ? '1=1' : 'user_id = ?';
        $whereParams = $user['role'] === 'admin' ? [] : [$user['id']];

        $databases = $this->app->db()->fetchAll(
            "SELECT d.*, u.username FROM user_databases d JOIN users u ON d.user_id = u.id WHERE {$where} ORDER BY d.db_name",
            $whereParams
        );

        $dbUsers = $this->app->db()->fetchAll(
            "SELECT du.*, u.username as owner FROM database_users du JOIN users u ON du.user_id = u.id WHERE {$where} ORDER BY du.db_username",
            $whereParams
        );

        $supportedTypes = $this->app->config('databases', []);

        $this->view('Databases/views/index', [
            'databases' => $databases,
            'dbUsers' => $dbUsers,
            'supportedTypes' => $supportedTypes,
            'services' => $this->getDatabaseServices(),
            'pageTitle' => 'Databases',
            'breadcrumbs' => [['label' => 'Databases', 'active' => true]],
        ]);
    }

    public function create(array $params = []): void
    {
        $this->view('Databases/views/create', [
            'supportedTypes' => $this->app->config('databases', []),
            'pageTitle' => 'Create Database',
            'breadcrumbs' => [['label' => 'Databases', 'url' => '/databases'], ['label' => 'Create', 'active' => true]],
        ]);
    }

    public function store(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/databases/create');
            return;
        }

        $user = $this->app->auth()->user();
        $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'] ?? '');
        $dbType = $_POST['db_type'] ?? 'mysql';
        $dbUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_username'] ?? '');
        $dbPassword = $_POST['db_password'] ?? '';

        $allowedTypes = ['mysql', 'mariadb', 'postgresql', 'mongodb', 'sqlite'];
        if (!in_array($dbType, $allowedTypes)) {
            $this->app->session()->flash('error', 'Invalid database type.');
            $this->redirect('/databases/create');
            return;
        }

        if (empty($dbName) || strlen($dbName) < 2) {
            $this->app->session()->flash('error', 'Invalid database name.');
            $this->redirect('/databases/create');
            return;
        }

        // Prefix with username
        $prefixedName = $user['username'] . '_' . $dbName;
        $prefixedUser = $user['username'] . '_' . $dbUsername;

        // Check limit
        if ($user['max_databases'] != -1) {
            $count = $this->app->db()->count('user_databases', 'user_id = ?', [$user['id']]);
            if ($count >= $user['max_databases']) {
                $this->app->session()->flash('error', 'Database limit reached.');
                $this->redirect('/databases');
                return;
            }
        }

        // Create the database on the server
        $result = $this->createSystemDatabase($dbType, $prefixedName, $prefixedUser, $dbPassword);

        if (!$result['success']) {
            $this->app->session()->flash('error', 'Failed to create database: ' . ($result['error'] ?? ''));
            $this->redirect('/databases/create');
            return;
        }

        // Record in panelion
        $dbId = $this->app->db()->insert('user_databases', [
            'user_id' => $user['id'],
            'db_name' => $prefixedName,
            'db_type' => $dbType,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!empty($dbUsername)) {
            $dbUserId = $this->app->db()->insert('database_users', [
                'user_id' => $user['id'],
                'db_username' => $prefixedUser,
                'db_type' => $dbType,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->app->db()->insert('database_user_grants', [
                'database_id' => $dbId,
                'database_user_id' => $dbUserId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->app->logger()->info("Database created: {$prefixedName} ({$dbType}) for user {$user['username']}");
        $this->app->session()->flash('success', "Database '{$prefixedName}' created successfully.");
        $this->redirect('/databases');
    }

    public function delete(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/databases');
            return;
        }

        $id = (int)($params['id'] ?? 0);
        $user = $this->app->auth()->user();

        $where = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND user_id = ?';
        $whereParams = $user['role'] === 'admin' ? [$id] : [$id, $user['id']];

        $database = $this->app->db()->fetch("SELECT * FROM user_databases WHERE {$where}", $whereParams);
        if (!$database) {
            $this->redirect('/databases');
            return;
        }

        // Drop the database on the server
        $this->dropSystemDatabase($database['db_type'], $database['db_name']);

        $this->app->db()->deleteFrom('user_databases', 'id = ?', [$id]);
        $this->app->logger()->info("Database deleted: {$database['db_name']}");
        $this->app->session()->flash('success', "Database '{$database['db_name']}' deleted.");
        $this->redirect('/databases');
    }

    public function users(array $params = []): void
    {
        $user = $this->app->auth()->user();
        $where = $user['role'] === 'admin' ? '1=1' : 'user_id = ?';
        $whereParams = $user['role'] === 'admin' ? [] : [$user['id']];

        $dbUsers = $this->app->db()->fetchAll(
            "SELECT * FROM database_users WHERE {$where} ORDER BY db_username",
            $whereParams
        );

        $this->view('Databases/views/users', [
            'dbUsers' => $dbUsers,
            'pageTitle' => 'Database Users',
        ]);
    }

    public function createUser(array $params = []): void
    {
        if (!$this->validateCSRF()) {
            $this->redirect('/databases/users');
            return;
        }

        $user = $this->app->auth()->user();
        $dbUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_username'] ?? '');
        $dbPassword = $_POST['db_password'] ?? '';
        $dbType = $_POST['db_type'] ?? 'mysql';

        $prefixedUser = $user['username'] . '_' . $dbUsername;

        if ($dbType === 'mysql' || $dbType === 'mariadb') {
            SystemCommand::exec('sudo mysql', ['-e', "CREATE USER '{$prefixedUser}'@'localhost' IDENTIFIED BY " . escapeshellarg($dbPassword)]);
        } elseif ($dbType === 'postgresql') {
            SystemCommand::exec('sudo -u postgres psql', ['-c', "CREATE USER {$prefixedUser} WITH PASSWORD " . escapeshellarg($dbPassword)]);
        }

        $this->app->db()->insert('database_users', [
            'user_id' => $user['id'],
            'db_username' => $prefixedUser,
            'db_type' => $dbType,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->app->session()->flash('success', "Database user '{$prefixedUser}' created.");
        $this->redirect('/databases/users');
    }

    public function deleteUser(array $params = []): void
    {
        if (!$this->validateCSRF()) { $this->redirect('/databases/users'); return; }
        $id = (int)($params['id'] ?? 0);
        $dbUser = $this->app->db()->fetch("SELECT * FROM database_users WHERE id = ?", [$id]);
        if ($dbUser) {
            if ($dbUser['db_type'] === 'mysql' || $dbUser['db_type'] === 'mariadb') {
                SystemCommand::exec('sudo mysql', ['-e', "DROP USER IF EXISTS '{$dbUser['db_username']}'@'localhost'"]);
            } elseif ($dbUser['db_type'] === 'postgresql') {
                SystemCommand::exec('sudo -u postgres psql', ['-c', "DROP USER IF EXISTS {$dbUser['db_username']}"]);
            }
            $this->app->db()->deleteFrom('database_users', 'id = ?', [$id]);
        }
        $this->app->session()->flash('success', 'Database user deleted.');
        $this->redirect('/databases/users');
    }

    public function phpMyAdmin(array $params = []): void
    {
        $this->redirect('/phpmyadmin');
    }

    public function phpPgAdmin(array $params = []): void
    {
        $this->redirect('/phppgadmin');
    }

    private function createSystemDatabase(string $type, string $name, string $username, string $password): array
    {
        switch ($type) {
            case 'mysql':
            case 'mariadb':
                $result = SystemCommand::exec('sudo mysql', ['-e', "CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"]);
                if ($result['success'] && !empty($username)) {
                    SystemCommand::exec('sudo mysql', ['-e', "CREATE USER IF NOT EXISTS '{$username}'@'localhost' IDENTIFIED BY " . escapeshellarg($password)]);
                    SystemCommand::exec('sudo mysql', ['-e', "GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$username}'@'localhost'"]);
                    SystemCommand::exec('sudo mysql', ['-e', "FLUSH PRIVILEGES"]);
                }
                return $result;

            case 'postgresql':
                if (!empty($username)) {
                    SystemCommand::exec('sudo -u postgres psql', ['-c', "CREATE USER {$username} WITH PASSWORD " . escapeshellarg($password)]);
                }
                $result = SystemCommand::exec('sudo -u postgres createdb', ['-O', $username ?: 'postgres', $name]);
                return $result;

            case 'mongodb':
                $result = SystemCommand::exec('mongosh', ['--eval', "use {$name}; db.createCollection('init');"]);
                return $result;

            case 'sqlite':
                $sqlitePath = "/home/" . basename($name, '_') . "/databases/{$name}.sqlite";
                SystemCommand::exec('sudo mkdir', ['-p', dirname($sqlitePath)]);
                SystemCommand::exec('sudo touch', [$sqlitePath]);
                return ['success' => true, 'output' => $sqlitePath];

            default:
                return ['success' => false, 'error' => 'Unsupported database type'];
        }
    }

    private function dropSystemDatabase(string $type, string $name): array
    {
        switch ($type) {
            case 'mysql':
            case 'mariadb':
                return SystemCommand::exec('sudo mysql', ['-e', "DROP DATABASE IF EXISTS `{$name}`"]);
            case 'postgresql':
                return SystemCommand::exec('sudo -u postgres dropdb', ['--if-exists', $name]);
            case 'mongodb':
                return SystemCommand::exec('mongosh', ['--eval', "use {$name}; db.dropDatabase();"]);
            default:
                return ['success' => false, 'error' => 'Unsupported type'];
        }
    }

    private function getDatabaseServices(): array
    {
        return [
            'mysql' => SystemCommand::isServiceRunning('mysql') || SystemCommand::isServiceRunning('mariadb'),
            'postgresql' => SystemCommand::isServiceRunning('postgresql'),
            'mongodb' => SystemCommand::isServiceRunning('mongod'),
            'redis' => SystemCommand::isServiceRunning('redis-server'),
        ];
    }
}
