<?php
namespace Panelion\Modules\SSL;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\SystemCommand;

class SSLController extends Controller
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
        $isAdmin = $user['role'] === 'admin';

        if ($isAdmin) {
            $certificates = $this->db->fetchAll(
                "SELECT s.*, d.domain, u.username FROM ssl_certificates s
                 LEFT JOIN domains d ON s.domain_id = d.id
                 LEFT JOIN users u ON s.user_id = u.id
                 ORDER BY s.expiry_date"
            );
            $domains = $this->db->fetchAll("SELECT id, domain FROM domains ORDER BY domain");
        } else {
            $certificates = $this->db->fetchAll(
                "SELECT s.*, d.domain FROM ssl_certificates s
                 LEFT JOIN domains d ON s.domain_id = d.id
                 WHERE s.user_id = ? ORDER BY s.expiry_date",
                [$user['id']]
            );
            $domains = $this->db->fetchAll(
                "SELECT id, domain FROM domains WHERE user_id = ? ORDER BY domain",
                [$user['id']]
            );
        }

        foreach ($certificates as &$cert) {
            if ($cert['expiry_date']) {
                $expiry = strtotime($cert['expiry_date']);
                $cert['days_left'] = max(0, (int)round(($expiry - time()) / 86400));
                if ($cert['days_left'] <= 0) {
                    $cert['expiry_class'] = 'danger';
                    $cert['status'] = 'expired';
                } elseif ($cert['days_left'] <= 30) {
                    $cert['expiry_class'] = 'warning';
                } else {
                    $cert['expiry_class'] = 'success';
                }
            } else {
                $cert['days_left'] = null;
                $cert['expiry_class'] = 'secondary';
            }
        }
        unset($cert);

        $this->view('SSL.views.index', [
            'pageTitle' => 'SSL/TLS Certificates',
            'certificates' => $certificates,
            'domains' => $domains,
        ]);
    }

    public function letsencrypt()
    {
        if (!$this->validateCSRF()) {
            $this->app->session()->flash('error', 'Invalid CSRF token.');
            $this->redirect('/ssl');
            return;
        }

        $domainId = (int)$this->input('domain_id');
        $includeWww = (bool)$this->input('include_www');
        $user = $this->app->auth()->user();

        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ?", [$domainId]);
        if (!$domain) {
            $this->app->session()->flash('error', 'Domain not found.');
            $this->redirect('/ssl');
            return;
        }

        if ($user['role'] !== 'admin' && $domain['user_id'] !== $user['id']) {
            $this->app->session()->flash('error', 'Access denied.');
            $this->redirect('/ssl');
            return;
        }

        $domainName = $domain['domain'];
        $args = "-d " . escapeshellarg($domainName);
        if ($includeWww) {
            $args .= " -d " . escapeshellarg("www.{$domainName}");
        }

        $this->cmd->execute(
            "certbot certonly --webroot -w /var/www/html {$args} --non-interactive --agree-tos --email admin@{$domainName}",
            true
        );

        $certPath = "/etc/letsencrypt/live/{$domainName}/fullchain.pem";
        $keyPath = "/etc/letsencrypt/live/{$domainName}/privkey.pem";

        $expiryDate = date('Y-m-d H:i:s', strtotime('+90 days'));
        $this->db->insert('ssl_certificates', [
            'user_id' => $user['id'],
            'domain_id' => $domainId,
            'type' => 'letsencrypt',
            'certificate_path' => $certPath,
            'key_path' => $keyPath,
            'expiry_date' => $expiryDate,
            'status' => 'active',
            'auto_renew' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->app->session()->flash('success', "Let's Encrypt SSL installed for {$domainName}.");
        $this->redirect('/ssl');
    }

    public function uploadCustom()
    {
        $user = $this->app->auth()->user();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($user['role'] === 'admin') {
                $domains = $this->db->fetchAll("SELECT id, domain FROM domains ORDER BY domain");
            } else {
                $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE user_id = ? ORDER BY domain", [$user['id']]);
            }
            $this->view('SSL.views.upload', [
                'pageTitle' => 'Upload SSL Certificate',
                'domains' => $domains,
            ]);
            return;
        }

        if (!$this->validateCSRF()) {
            $this->app->session()->flash('error', 'Invalid CSRF token.');
            $this->redirect('/ssl');
            return;
        }

        $domainId = (int)$this->input('domain_id');
        $certificate = $this->input('certificate', '');
        $privateKey = $this->input('private_key', '');
        $caBundle = $this->input('ca_bundle', '');

        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ?", [$domainId]);
        if (!$domain || ($user['role'] !== 'admin' && $domain['user_id'] !== $user['id'])) {
            $this->app->session()->flash('error', 'Domain not found or access denied.');
            $this->redirect('/ssl');
            return;
        }

        if (empty($certificate) || empty($privateKey)) {
            $this->app->session()->flash('error', 'Certificate and private key are required.');
            $this->redirect('/ssl/upload');
            return;
        }

        $sslDir = $this->app->config('paths.ssl_certs') . '/' . $domain['domain'];
        if (!is_dir($sslDir)) {
            mkdir($sslDir, 0700, true);
        }

        $certPath = $sslDir . '/certificate.pem';
        $keyPath = $sslDir . '/private.key';

        file_put_contents($certPath, $certificate);
        file_put_contents($keyPath, $privateKey);
        chmod($keyPath, 0600);

        if (!empty($caBundle)) {
            file_put_contents($sslDir . '/ca-bundle.pem', $caBundle);
        }

        $certInfo = openssl_x509_parse($certificate);
        $expiryDate = $certInfo ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : null;

        $this->db->insert('ssl_certificates', [
            'user_id' => $user['id'],
            'domain_id' => $domainId,
            'type' => 'custom',
            'certificate_path' => $certPath,
            'key_path' => $keyPath,
            'expiry_date' => $expiryDate,
            'status' => 'active',
            'auto_renew' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->app->session()->flash('success', "SSL certificate uploaded for {$domain['domain']}.");
        $this->redirect('/ssl');
    }

    public function renew($id)
    {
        if (!$this->validateCSRF()) {
            $this->app->session()->flash('error', 'Invalid CSRF token.');
            $this->redirect('/ssl');
            return;
        }

        $cert = $this->getCertForUser($id);
        if (!$cert) return;

        $this->cmd->execute(
            "certbot renew --cert-name " . escapeshellarg($cert['domain']),
            true
        );

        $this->db->update('ssl_certificates', [
            'expiry_date' => date('Y-m-d H:i:s', strtotime('+90 days')),
            'status' => 'active',
        ], 'id = ?', [$id]);

        $this->app->session()->flash('success', "Certificate renewed for {$cert['domain']}.");
        $this->redirect('/ssl');
    }

    public function delete($id)
    {
        if (!$this->validateCSRF()) {
            $this->app->session()->flash('error', 'Invalid CSRF token.');
            $this->redirect('/ssl');
            return;
        }

        $cert = $this->getCertForUser($id);
        if (!$cert) return;

        $this->db->deleteFrom('ssl_certificates', 'id = ?', [$id]);
        $this->app->session()->flash('success', "Certificate deleted for {$cert['domain']}.");
        $this->redirect('/ssl');
    }

    public function generateCSR()
    {
        $user = $this->app->auth()->user();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($user['role'] === 'admin') {
                $domains = $this->db->fetchAll("SELECT id, domain FROM domains ORDER BY domain");
            } else {
                $domains = $this->db->fetchAll("SELECT id, domain FROM domains WHERE user_id = ? ORDER BY domain", [$user['id']]);
            }
            $this->view('SSL.views.csr', [
                'pageTitle' => 'Generate CSR',
                'domains' => $domains,
            ]);
            return;
        }

        if (!$this->validateCSRF()) {
            $this->app->session()->flash('error', 'Invalid CSRF token.');
            $this->redirect('/ssl/csr');
            return;
        }

        $domain = $this->input('domain', '');
        $country = $this->input('country', 'US');
        $state = $this->input('state', '');
        $city = $this->input('city', '');
        $org = $this->input('organization', '');
        $email = $this->input('email', '');

        if (empty($domain)) {
            $this->app->session()->flash('error', 'Domain is required.');
            $this->redirect('/ssl/csr');
            return;
        }

        $subject = "/C=" . substr(preg_replace('/[^A-Z]/', '', strtoupper($country)), 0, 2);
        if ($state) $subject .= "/ST=" . addslashes($state);
        if ($city) $subject .= "/L=" . addslashes($city);
        if ($org) $subject .= "/O=" . addslashes($org);
        $subject .= "/CN=" . addslashes($domain);
        if ($email) $subject .= "/emailAddress=" . addslashes($email);

        try {
            $tmpDir = sys_get_temp_dir() . '/panelion_csr_' . uniqid();
            mkdir($tmpDir, 0700, true);
            $keyFile = $tmpDir . '/private.key';
            $csrFile = $tmpDir . '/request.csr';

            $this->cmd->execute(
                "openssl req -new -newkey rsa:2048 -nodes"
                . " -keyout " . escapeshellarg($keyFile)
                . " -out " . escapeshellarg($csrFile)
                . " -subj " . escapeshellarg($subject) . " 2>&1"
            );

            $csr = file_exists($csrFile) ? file_get_contents($csrFile) : '';
            $key = file_exists($keyFile) ? file_get_contents($keyFile) : '';

            if (file_exists($keyFile)) unlink($keyFile);
            if (file_exists($csrFile)) unlink($csrFile);
            rmdir($tmpDir);

            $this->view('SSL.views.csr_result', [
                'pageTitle' => 'CSR Generated',
                'domain' => $domain,
                'csr' => $csr,
                'private_key' => $key,
            ]);
        } catch (\Exception $e) {
            $this->app->logger()->error("CSR generation failed: " . $e->getMessage());
            $this->app->session()->flash('error', 'Failed to generate CSR.');
            $this->redirect('/ssl/csr');
        }
    }

    private function getCertForUser($id)
    {
        $user = $this->app->auth()->user();

        if ($user['role'] === 'admin') {
            $cert = $this->db->fetch(
                "SELECT s.*, d.domain FROM ssl_certificates s LEFT JOIN domains d ON s.domain_id = d.id WHERE s.id = ?",
                [$id]
            );
        } else {
            $cert = $this->db->fetch(
                "SELECT s.*, d.domain FROM ssl_certificates s LEFT JOIN domains d ON s.domain_id = d.id WHERE s.id = ? AND s.user_id = ?",
                [$id, $user['id']]
            );
        }

        if (!$cert) {
            $this->app->session()->flash('error', 'Certificate not found.');
            $this->redirect('/ssl');
            return null;
        }

        return $cert;
    }
}
