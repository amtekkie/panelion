<?php
namespace Panelion\Modules\Email;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;
use Panelion\Core\Security;
use Panelion\Core\Logger;

class EmailController extends Controller
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
            $accounts = $this->db->fetchAll("SELECT e.*, d.domain, u.username FROM email_accounts e JOIN domains d ON e.domain_id = d.id LEFT JOIN users u ON e.user_id = u.id ORDER BY e.email");
            $forwarders = $this->db->fetchAll("SELECT f.*, d.domain FROM email_forwarders f JOIN domains d ON f.domain_id = d.id ORDER BY f.source");
            $autoresponders = $this->db->fetchAll("SELECT a.*, d.domain FROM email_autoresponders a JOIN domains d ON a.domain_id = d.id ORDER BY a.email");
            $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE status = 'active' ORDER BY domain");
        } else {
            $accounts = $this->db->fetchAll("SELECT e.*, d.domain FROM email_accounts e JOIN domains d ON e.domain_id = d.id WHERE e.user_id = ? ORDER BY e.email", [$userId]);
            $forwarders = $this->db->fetchAll("SELECT f.*, d.domain FROM email_forwarders f JOIN domains d ON f.domain_id = d.id WHERE f.user_id = ? ORDER BY f.source", [$userId]);
            $autoresponders = $this->db->fetchAll("SELECT a.*, d.domain FROM email_autoresponders a JOIN domains d ON a.domain_id = d.id WHERE a.user_id = ? ORDER BY a.email", [$userId]);
            $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE user_id = ? AND status = 'active' ORDER BY domain", [$userId]);
        }

        // Get mail service status
        $postfixStatus = trim($this->cmd->execute("systemctl is-active postfix 2>/dev/null"));
        $dovecotStatus = trim($this->cmd->execute("systemctl is-active dovecot 2>/dev/null"));

        // Get Roundcube URL from settings
        $roundcubeUrl = $this->db->fetchColumn("SELECT value FROM settings WHERE `key` = 'roundcube_url'") ?: '/roundcube';

        $this->view('Email/views/index', [
            'title' => 'Email Management',
            'accounts' => $accounts,
            'forwarders' => $forwarders,
            'autoresponders' => $autoresponders,
            'domains' => $domains,
            'postfixStatus' => $postfixStatus,
            'dovecotStatus' => $dovecotStatus,
            'roundcubeUrl' => $roundcubeUrl
        ]);
    }

    public function create()
    {
        $user = $this->app->auth()->user();
        $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE user_id = ? AND status = 'active' ORDER BY domain", [$user['id']]);

        $this->view('Email/views/create', [
            'title' => 'Create Email Account',
            'domains' => $domains
        ]);
    }

    public function store()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $username = strtolower(trim($this->input('username')));
        $domainId = (int)$this->input('domain_id');
        $password = $this->input('password');
        $quota = (int)$this->input('quota', 1024);

        if (empty($username) || !preg_match('/^[a-z0-9._-]+$/', $username)) {
            $this->app->session()->flash('error', 'Invalid email username. Use only lowercase letters, numbers, dots, hyphens, and underscores.');
            $this->redirect('/email/create');
            return;
        }

        if (strlen($password) < 8) {
            $this->app->session()->flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/email/create');
            return;
        }

        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        if (!$domain) {
            $this->app->session()->flash('error', 'Invalid domain.');
            $this->redirect('/email/create');
            return;
        }

        $email = $username . '@' . $domain['domain'];

        if ($this->db->fetch("SELECT id FROM email_accounts WHERE email = ?", [$email])) {
            $this->app->session()->flash('error', 'Email account already exists.');
            $this->redirect('/email/create');
            return;
        }

        $userInfo = $this->db->fetch("SELECT u.*, p.max_email_accounts FROM users u LEFT JOIN packages p ON u.package_id = p.id WHERE u.id = ?", [$userId]);
        if ($userInfo['max_email_accounts'] > 0) {
            $currentCount = $this->db->fetchColumn("SELECT COUNT(*) FROM email_accounts WHERE user_id = ?", [$userId]);
            if ($currentCount >= $userInfo['max_email_accounts']) {
                $this->app->session()->flash('error', 'Email account limit reached.');
                $this->redirect('/email/create');
                return;
            }
        }

        try {
            $hashedPassword = $this->hashMailPassword($password);
            $mailDir = "/var/mail/vhosts/{$domain['domain']}/{$username}";

            $this->db->insert('email_accounts', [
                'user_id' => $userId,
                'domain_id' => $domainId,
                'email' => $email,
                'password' => $hashedPassword,
                'quota' => $quota,
                'status' => 'active'
            ]);

            $this->cmd->execute("mkdir -p " . escapeshellarg($mailDir), true);
            $this->cmd->execute("chown -R vmail:vmail " . escapeshellarg("/var/mail/vhosts/{$domain['domain']}"), true);
            $this->updateVirtualMailboxes();

            Logger::info("Email account created: {$email}");
            $this->app->session()->flash('success', "Email account '{$email}' created.");
        } catch (\Exception $e) {
            Logger::error("Failed to create email account: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to create email account.');
        }

        $this->redirect('/email');
    }

    public function delete($id)
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $account = $this->db->fetch("SELECT e.*, d.domain FROM email_accounts e JOIN domains d ON e.domain_id = d.id WHERE e.id = ?", [$id]);
        } else {
            $account = $this->db->fetch("SELECT e.*, d.domain FROM email_accounts e JOIN domains d ON e.domain_id = d.id WHERE e.id = ? AND e.user_id = ?", [$id, $userId]);
        }

        if (!$account) {
            $this->app->session()->flash('error', 'Email account not found.');
            $this->redirect('/email');
            return;
        }

        try {
            $this->db->deleteFrom('email_accounts', 'id = ?', [$id]);
            $this->updateVirtualMailboxes();

            Logger::info("Email account deleted: {$account['email']}");
            $this->app->session()->flash('success', "Email account '{$account['email']}' deleted.");
        } catch (\Exception $e) {
            Logger::error("Failed to delete email: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to delete email account.');
        }

        $this->redirect('/email');
    }

    public function createForwarder()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $source = strtolower(trim($this->input('source')));
        $domainId = (int)$this->input('domain_id');
        $destination = strtolower(trim($this->input('destination')));

        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        if (!$domain) {
            $this->app->session()->flash('error', 'Invalid domain.');
            $this->redirect('/email');
            return;
        }

        if (empty($source) || empty($destination) || !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('error', 'Valid source and destination are required.');
            $this->redirect('/email');
            return;
        }

        $fullSource = $source . '@' . $domain['domain'];

        try {
            $this->db->insert('email_forwarders', [
                'user_id' => $userId,
                'domain_id' => $domainId,
                'source' => $fullSource,
                'destination' => $destination
            ]);

            $this->updateVirtualAliases();

            Logger::info("Email forwarder created: {$fullSource} -> {$destination}");
            $this->app->session()->flash('success', 'Email forwarder created.');
        } catch (\Exception $e) {
            Logger::error("Failed to create forwarder: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to create forwarder.');
        }

        $this->redirect('/email');
    }

    public function deleteForwarder($id)
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $isAdmin = ($user['role'] === 'admin');

        $where = $isAdmin ? 'id = ?' : 'id = ? AND user_id = ?';
        $params = $isAdmin ? [$id] : [$id, $user['id']];

        $this->db->deleteFrom('email_forwarders', $where, $params);
        $this->updateVirtualAliases();

        $this->app->session()->flash('success', 'Forwarder deleted.');
        $this->redirect('/email');
    }

    public function createAutoresponder()
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];

        $email = strtolower(trim($this->input('email')));
        $domainId = (int)$this->input('domain_id');
        $subject = trim($this->input('subject'));
        $body = trim($this->input('body'));
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        if (!$domain) {
            $this->app->session()->flash('error', 'Invalid domain.');
            $this->redirect('/email');
            return;
        }

        $fullEmail = $email . '@' . $domain['domain'];

        try {
            $this->db->insert('email_autoresponders', [
                'user_id' => $userId,
                'domain_id' => $domainId,
                'email' => $fullEmail,
                'subject' => $subject,
                'body' => $body,
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'status' => 'active'
            ]);

            Logger::info("Autoresponder created for: {$fullEmail}");
            $this->app->session()->flash('success', 'Autoresponder created.');
        } catch (\Exception $e) {
            Logger::error("Failed to create autoresponder: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to create autoresponder.');
        }

        $this->redirect('/email');
    }

    public function deleteAutoresponder($id)
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $isAdmin = ($user['role'] === 'admin');

        $where = $isAdmin ? 'id = ?' : 'id = ? AND user_id = ?';
        $params = $isAdmin ? [$id] : [$id, $user['id']];

        $this->db->deleteFrom('email_autoresponders', $where, $params);

        $this->app->session()->flash('success', 'Autoresponder deleted.');
        $this->redirect('/email');
    }

    public function changePassword($id)
    {
        $this->validateCSRF();
        $user = $this->app->auth()->user();
        $userId = $user['id'];
        $isAdmin = ($user['role'] === 'admin');

        if ($isAdmin) {
            $account = $this->db->fetch("SELECT * FROM email_accounts WHERE id = ?", [$id]);
        } else {
            $account = $this->db->fetch("SELECT * FROM email_accounts WHERE id = ? AND user_id = ?", [$id, $userId]);
        }

        if (!$account) {
            $this->app->session()->flash('error', 'Email account not found.');
            $this->redirect('/email');
            return;
        }

        $password = $this->input('password');
        if (strlen($password) < 8) {
            $this->app->session()->flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/email');
            return;
        }

        $hashedPassword = $this->hashMailPassword($password);
        $this->db->update('email_accounts', ['password' => $hashedPassword], 'id = ?', [$id]);

        $this->app->session()->flash('success', 'Password changed for ' . $account['email']);
        $this->redirect('/email');
    }

    public function webmail()
    {
        $roundcubeUrl = $this->db->fetchColumn("SELECT value FROM settings WHERE `key` = 'roundcube_url'") ?: '/roundcube';
        $this->redirect($roundcubeUrl);
    }

    // ── Private Helpers ──

    private function hashMailPassword($password)
    {
        $salt = bin2hex(random_bytes(8));
        return '{SHA512-CRYPT}' . crypt($password, '$6$' . $salt . '$');
    }

    private function updateVirtualMailboxes()
    {
        $accounts = $this->db->fetchAll("SELECT e.email, d.domain FROM email_accounts e JOIN domains d ON e.domain_id = d.id WHERE e.status = 'active'");

        $content = "# Virtual mailboxes - managed by Panelion\n";
        foreach ($accounts as $acc) {
            $parts = explode('@', $acc['email']);
            $content .= "{$acc['email']}    {$acc['domain']}/{$parts[0]}/\n";
        }

        $mapFile = '/etc/postfix/virtual_mailboxes';
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_mail_');
        file_put_contents($tmpFile, $content);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($mapFile), true);
        unlink($tmpFile);

        $this->cmd->execute("postmap " . escapeshellarg($mapFile), true);
        $this->cmd->execute("systemctl reload postfix 2>/dev/null", true);
    }

    private function updateVirtualAliases()
    {
        $forwarders = $this->db->fetchAll("SELECT source, destination FROM email_forwarders");

        $content = "# Virtual aliases - managed by Panelion\n";
        foreach ($forwarders as $fwd) {
            $content .= "{$fwd['source']}    {$fwd['destination']}\n";
        }

        $mapFile = '/etc/postfix/virtual_aliases';
        $tmpFile = tempnam(sys_get_temp_dir(), 'pnl_alias_');
        file_put_contents($tmpFile, $content);
        $this->cmd->execute("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($mapFile), true);
        unlink($tmpFile);

        $this->cmd->execute("postmap " . escapeshellarg($mapFile), true);
        $this->cmd->execute("systemctl reload postfix 2>/dev/null", true);
    }
}
