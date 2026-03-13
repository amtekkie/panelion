<?php
namespace Panelion\Modules\License;

use Panelion\Core\Controller;
use Panelion\Core\Database;
use Panelion\Core\License;

class LicenseController extends Controller
{
    private License $license;

    public function __construct()
    {
        parent::__construct();
        $this->license = new License(Database::getInstance(), PANELION_ROOT);
    }

    /**
     * Show license activation page.
     */
    public function index()
    {
        $data = $this->getLicenseViewData();
        $data['layout'] = 'layouts/auth';
        $data['pageTitle'] = 'License Activation';

        $this->view('license.activate', $data);
    }

    /**
     * Process license activation.
     */
    public function activate()
    {
        $this->validateCSRF();

        $licenseKey = trim($_POST['license_key'] ?? '');
        $domain = License::getServerDomain();

        if (empty($licenseKey)) {
            $data = $this->getLicenseViewData();
            $data['error'] = 'Please enter a license key.';
            $data['layout'] = 'layouts/auth';
            $data['pageTitle'] = 'License Activation';
            $this->view('license.activate', $data);
            return;
        }

        // Validate format: XXXX-XXXX-XXXX-XXXX
        if (!preg_match('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/', $licenseKey)) {
            $data = $this->getLicenseViewData();
            $data['error'] = 'Invalid license key format. Expected: XXXX-XXXX-XXXX-XXXX';
            $data['layout'] = 'layouts/auth';
            $data['pageTitle'] = 'License Activation';
            $this->view('license.activate', $data);
            return;
        }

        $result = $this->license->activate($licenseKey, $domain);

        if ($result['success']) {
            header('Location: ' . $this->app->url('/'));
            exit;
        }

        $data = $this->getLicenseViewData();
        $data['error'] = $result['message'];
        $data['layout'] = 'layouts/auth';
        $data['pageTitle'] = 'License Activation';
        $this->view('license.activate', $data);
    }

    /**
     * Deactivate license.
     */
    public function deactivate()
    {
        $this->validateCSRF();
        $this->license->deactivate();
        header('Location: ' . $this->app->url('/license'));
        exit;
    }

    /**
     * API endpoint: check license status (JSON).
     */
    public function status()
    {
        $licenseData = $this->license->getLicenseData();
        $valid = $this->license->isValid();

        $this->json([
            'valid' => $valid,
            'status' => $licenseData['status'] ?? 'none',
            'expiry' => $licenseData['expiry'] ?? null,
            'domain' => $licenseData['domain'] ?? null,
        ]);
    }

    /**
     * API endpoint: check for updates (JSON).
     */
    public function versionCheck()
    {
        $version = $this->license->checkVersion();
        $this->json($version ?? ['status' => 'unavailable']);
    }

    private function getLicenseViewData(): array
    {
        $licenseData = $this->license->getLicenseData();
        return [
            'domain' => License::getServerDomain(),
            'licenseData' => $licenseData,
            'loggedIn' => $this->app->auth()->check(),
            'productUrl' => $this->license->getProductUrl(),
        ];
    }
}
