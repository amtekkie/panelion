<?php
/**
 * Panelion - Authentication
 */

namespace Panelion\Core;

class Auth
{
    private Database $db;
    private Session $session;
    private array $config;
    private ?array $user = null;

    public function __construct(Database $db, Session $session, array $config)
    {
        $this->db = $db;
        $this->session = $session;
        $this->config = $config;

        // Load user from session
        $userId = $this->session->get('user_id');
        if ($userId) {
            $this->user = $this->db->fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$userId]);
            if (!$this->user) {
                $this->session->delete('user_id');
            }
        }
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->user) return false;
        if ($this->user['role'] === 'admin') return true;

        // Check user-level permissions
        $userPerms = json_decode($this->user['permissions'] ?? '[]', true) ?: [];
        if (in_array('*', $userPerms) || in_array($permission, $userPerms)) return true;

        // Check group-level permissions
        $groups = $this->db->fetchAll(
            "SELECT g.permissions FROM user_groups g
             JOIN user_group_members m ON m.group_id = g.id
             WHERE m.user_id = ?",
            [$this->user['id']]
        );
        foreach ($groups as $group) {
            $groupPerms = json_decode($group['permissions'] ?? '[]', true) ?: [];
            if (in_array('*', $groupPerms) || in_array($permission, $groupPerms)) return true;
        }

        return false;
    }

    private function redirectTo(string $path): void
    {
        $url = $path;
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = App::getInstance()->url($url);
        }
        header('Location: ' . $url);
        exit;
    }

    private function renderAuthView(string $viewFile, array $data = []): void
    {
        $data['csrf_token'] = $this->session->get('csrf_token');
        extract($data);

        ob_start();
        require PANELION_ROOT . '/views/auth/' . $viewFile . '.php';
        $content = ob_get_clean();

        ob_start();
        require PANELION_ROOT . '/views/layouts/auth.php';
        $html = ob_get_clean();

        $basePath = App::getInstance()->basePath();
        if ($basePath !== '') {
            $html = preg_replace_callback(
                '/(href|action|src)="\/(?!\/|https?:)([^"]*)"/',
                function ($m) use ($basePath) {
                    return $m[1] . '="' . $basePath . '/' . $m[2] . '"';
                },
                $html
            );
        }

        echo $html;
    }

    public function loginForm(array $params = []): void
    {
        if ($this->check()) {
            $this->redirectTo('/dashboard');
        }

        $error = $this->session->flash('login_error');
        $this->renderAuthView('login', ['error' => $error]);
    }

    public function login(array $params = []): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = Security::getClientIP();

        // Rate limiting
        if (!Security::checkRateLimit($this->db, 'login:' . $ip, $this->config['max_login_attempts'] ?? 5, $this->config['lockout_duration'] ?? 900)) {
            $this->session->flash('login_error', 'Too many login attempts. Please try again later.');
            $this->redirectTo('/login');
        }

        if (empty($username) || empty($password)) {
            $this->session->flash('login_error', 'Please enter username and password.');
            $this->redirectTo('/login');
        }

        $user = $this->db->fetch("SELECT * FROM users WHERE username = ? AND status = 'active'", [$username]);

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            Security::recordRateLimitHit($this->db, 'login:' . $ip);
            App::getInstance()->logger()->warning("Failed login attempt for user: {$username} from IP: {$ip}");
            $this->session->flash('login_error', 'Invalid username or password.');
            $this->redirectTo('/login');
        }

        // Check for 2FA
        if (!empty($user['two_factor_secret'])) {
            $this->session->set('2fa_user_id', $user['id']);
            $this->redirectTo('/two-factor');
        }

        $this->completeLogin($user);
    }

    public function twoFactorForm(array $params = []): void
    {
        if (!$this->session->get('2fa_user_id')) {
            $this->redirectTo('/login');
        }

        $error = $this->session->flash('2fa_error');
        $this->renderAuthView('two-factor', ['error' => $error]);
    }

    public function twoFactorVerify(array $params = []): void
    {
        $userId = $this->session->get('2fa_user_id');
        if (!$userId) {
            $this->redirectTo('/login');
        }

        $code = trim($_POST['code'] ?? '');
        $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

        if (!$user || !Security::verifyTOTP($user['two_factor_secret'], $code)) {
            $this->session->flash('2fa_error', 'Invalid verification code.');
            $this->redirectTo('/two-factor');
        }

        $this->session->delete('2fa_user_id');
        $this->completeLogin($user);
    }

    private function completeLogin(array $user): void
    {
        session_regenerate_id(true);
        $this->session->set('user_id', $user['id']);
        $this->user = $user;

        // Log successful login
        $ip = Security::getClientIP();
        $this->db->insert('login_logs', [
            'user_id' => $user['id'],
            'ip_address' => $ip,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'status' => 'success',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update last login
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s'), 'last_login_ip' => $ip], 'id = ?', [$user['id']]);

        App::getInstance()->logger()->info("User {$user['username']} logged in from {$ip}");

        $this->redirectTo('/dashboard');
    }

    public function logout(array $params = []): void
    {
        if ($this->user) {
            App::getInstance()->logger()->info("User {$this->user['username']} logged out");
        }
        $this->session->destroy();
        $this->redirectTo('/login');
    }
}
