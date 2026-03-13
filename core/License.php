<?php
/**
 * Panelion - License Manager
 * Validates licenses against the FMZ License Manager WordPress plugin API.
 *
 * API endpoints used:
 *   POST /activate   — Bind license to this server's domain
 *   POST /deactivate — Release license from this domain
 *   POST /check      — Re-verify license status remotely
 *   GET  /version    — Fetch latest product version info
 */

namespace Panelion\Core;

class License
{
    private const CACHE_FILE = '/storage/cache/license.json';

    // Built-in RSA public key for verifying API response signatures.
    // Override via config/app.php  license.public_key  if you regenerate keys.
    private const DEFAULT_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvMoGW7YJDA6kiR95Nu7S
Vx3iUaTn7aCrI4mLsd+JpybAyab8Dm98NjSWVQofg5FqX2LJ9PRHYBvVfZHpn+tC
0DOn1RcUNxsXW0qW/3xhhgZTPq3l+SHsvVvwHeY8cc31A8ZFjHUv0q0mzQvZ03UB
LrVrQRQY07xxohpP3G4GxQSbOYcHfQHS43xTTxcffrQ/g4U0uJWAivr8T3muxEtN
OdebX/qoUOMe8AWcn0q3AvRXT+zDMzk2aQdOCbs0EpZeOC55FdkxEaO0UeOuocSl
JosmM1L684iZDk8gDi0GWPYElAIQzbtAoucZ/cO5EbI5XEbA/9mmEK+9YPPk2jk3
fwIDAQAB
-----END PUBLIC KEY-----";

    private Database $db;
    private string $rootPath;
    private array $config;

    public function __construct(Database $db, string $rootPath)
    {
        $this->db = $db;
        $this->rootPath = $rootPath;

        // Load config from app.php, falling back to sensible defaults
        $appConfig = [];
        $configFile = $rootPath . '/config/app.php';
        if (file_exists($configFile)) {
            $appConfig = require $configFile;
        }
        $lc = $appConfig['license'] ?? [];

        $this->config = [
            'api_base'       => rtrim($lc['api_base'] ?? 'https://tektove.com/wp-json/fmz-license/v1', '/'),
            'product_slug'   => $lc['product_slug'] ?? 'panelion',
            'product_url'    => $lc['product_url'] ?? 'https://tektove.com/shop/saas/panelion/',
            'check_interval' => (int) ($lc['check_interval'] ?? 86400),
            'grace_period'   => (int) ($lc['grace_period'] ?? 604800),
            'public_key'     => $lc['public_key'] ?? self::DEFAULT_PUBLIC_KEY,
        ];
    }

    // ── Public API ──

    /**
     * Check if the panel has a valid, active license.
     * Uses local cache to avoid hitting the API on every page load.
     */
    public function isValid(): bool
    {
        $cached = $this->getCachedLicense();
        if (!$cached) {
            return false;
        }

        // Locally-expired
        if ($cached['expiry'] !== 'lifetime' && strtotime($cached['expiry']) < time()) {
            return false;
        }

        // Suspended licenses are never valid
        if (($cached['status'] ?? '') === 'suspended') {
            return false;
        }

        // If we verified recently, trust the cache
        $lastCheck = $cached['last_checked'] ?? 0;
        if ((time() - $lastCheck) < $this->config['check_interval']) {
            return true;
        }

        // Re-validate against the FMZ API
        $apiResult = $this->checkWithApi($cached['license_key'], $cached['domain']);

        if ($apiResult !== null) {
            $status = $apiResult['status'] ?? '';

            if (in_array($status, ['active', 'valid', 'redistributable'])) {
                $cached['last_checked'] = time();
                $cached['status'] = $status;
                $cached['expiry'] = $apiResult['expiry'] ?? $cached['expiry'];
                $this->saveCachedLicense($cached);
                return true;
            }

            // License is no longer valid on the remote server
            $cached['status'] = $status;
            $this->saveCachedLicense($cached);
            $this->logLicense('warning', "Remote check returned status: {$status}");

            if (in_array($status, ['expired', 'revoked', 'suspended', 'invalid'])) {
                return false;
            }

            // domain_mismatch — license was moved to a different server
            if ($status === 'domain_mismatch') {
                $this->clearLicense();
                return false;
            }

            return false;
        }

        // API unreachable — trust cache within grace period
        if ((time() - $lastCheck) < $this->config['grace_period']) {
            return true;
        }

        return false;
    }

    /**
     * Get cached license data.
     */
    public function getLicenseData(): ?array
    {
        return $this->getCachedLicense();
    }

    /**
     * Activate a license key via the FMZ /activate endpoint.
     *
     * FMZ returns:
     *   200  status=valid|redistributable|reissued  — success
     *   409  status=already_active                  — key bound to another domain
     *   403  status=expired|suspended|invalid       — cannot activate
     *   404  status=invalid                         — key not found / revoked
     *   429  status=rate_limited                    — too many requests
     */
    public function activate(string $licenseKey, string $domain): array
    {
        $licenseKey = trim($licenseKey);
        $domain = trim($domain);

        $response = $this->apiRequest('activate', [
            'license_key' => $licenseKey,
            'domain'      => $domain,
            'product'     => $this->config['product_slug'],
        ]);

        if ($response === null) {
            return [
                'success' => false,
                'message' => 'Could not connect to the license server. Please check your internet connection and try again.',
            ];
        }

        $status   = $response['status'] ?? '';
        $httpCode = $response['_http_code'] ?? 0;

        // Rate-limited
        if ($httpCode === 429 || $status === 'rate_limited') {
            return ['success' => false, 'message' => 'Too many requests. Please wait a moment and try again.'];
        }

        // Already active on another server
        if ($httpCode === 409 || $status === 'already_active') {
            return [
                'success' => false,
                'message' => 'This license is already active on another server. Deactivate it there first, or contact support.',
            ];
        }

        // Expired
        if ($status === 'expired') {
            return ['success' => false, 'message' => 'This license has expired. Please renew it to continue.'];
        }

        // Suspended (subscription non-payment)
        if ($status === 'suspended') {
            return ['success' => false, 'message' => 'This license is suspended. Please resolve outstanding payments.'];
        }

        // Revoked / not found
        if ($httpCode === 404 || $status === 'revoked') {
            return ['success' => false, 'message' => 'License key not found or has been revoked.'];
        }

        // Successful activation
        $validStatuses = ['valid', 'redistributable', 'reissued'];
        if (in_array($status, $validStatuses)) {
            // Verify RSA signature when present
            if (!empty($response['signature']) && !empty($response['sig_payload'])) {
                if (!$this->verifySignature($response['signature'], $response['sig_payload'])) {
                    $this->logLicense('error', 'Signature verification failed during activation');
                    return ['success' => false, 'message' => 'License response signature verification failed. Possible tampering detected.'];
                }
            }

            $this->saveCachedLicense([
                'license_key'  => $licenseKey,
                'domain'       => $domain,
                'email'        => $response['email'] ?? '',
                'expiry'       => $response['expiry'] ?? 'lifetime',
                'status'       => $status,
                'activated_at' => date('Y-m-d H:i:s'),
                'last_checked' => time(),
            ]);

            $this->saveSetting('license_key', $licenseKey);
            $this->saveSetting('license_domain', $domain);
            $this->saveSetting('license_status', $status);
            $this->saveSetting('license_expiry', $response['expiry'] ?? 'lifetime');

            $this->logLicense('info', "License activated ({$status})", ['domain' => $domain]);

            return ['success' => true, 'message' => $response['message'] ?? 'License activated successfully.'];
        }

        // Catch-all for any unrecognised failure
        return ['success' => false, 'message' => $response['message'] ?? 'License activation failed.'];
    }

    /**
     * Deactivate the current license via the FMZ /deactivate endpoint.
     */
    public function deactivate(): array
    {
        $cached = $this->getCachedLicense();
        if (!$cached) {
            return ['success' => false, 'message' => 'No active license found.'];
        }

        $response = $this->apiRequest('deactivate', [
            'license_key' => $cached['license_key'],
            'domain'      => $cached['domain'],
            'product'     => $this->config['product_slug'],
        ]);

        $this->clearLicense();
        $this->logLicense('info', 'License deactivated', ['domain' => $cached['domain']]);

        if ($response !== null && ($response['status'] ?? '') === 'deactivated') {
            return ['success' => true, 'message' => 'License deactivated successfully.'];
        }

        return ['success' => true, 'message' => 'License removed from this server.'];
    }

    /**
     * Check license status via the FMZ /check endpoint.
     *
     * FMZ returns:
     *   status = active|inactive|expired|revoked|domain_mismatch|invalid
     *   plus  signature + sig_payload for verification.
     */
    public function checkWithApi(string $licenseKey, string $domain): ?array
    {
        $response = $this->apiRequest('check', [
            'license_key' => $licenseKey,
            'domain'      => $domain,
            'product'     => $this->config['product_slug'],
        ]);

        if ($response === null) {
            return null;
        }

        // Rate-limited — treat as unreachable to preserve grace period
        if (($response['_http_code'] ?? 0) === 429) {
            return null;
        }

        // Verify RSA signature when present
        if (!empty($response['signature']) && !empty($response['sig_payload'])) {
            if (!$this->verifySignature($response['signature'], $response['sig_payload'])) {
                $this->logLicense('error', 'Signature verification failed during status check');
                return null; // Treat tampered response as unreachable
            }
        }

        return $response;
    }

    /**
     * Fetch the latest product version info from FMZ /version endpoint.
     */
    public function checkVersion(): ?array
    {
        $cached = $this->getCachedLicense();
        $licenseKey = $cached['license_key'] ?? '';

        $params = ['product' => $this->config['product_slug']];
        if ($licenseKey !== '') {
            $params['license_key'] = $licenseKey;
        }

        $url = $this->config['api_base'] . '/version?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'X-FMZ-Client: Panelion/' . $this->getPanelionVersion(),
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode === 0) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'ok') {
            return null;
        }

        return $decoded;
    }

    /**
     * Get the product purchase URL.
     */
    public function getProductUrl(): string
    {
        return $this->config['product_url'];
    }

    /**
     * Get the server domain for license binding.
     */
    public static function getServerDomain(): string
    {
        $domain = gethostname();
        if (!$domain || $domain === 'localhost') {
            $domain = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        }
        // Strip port
        $domain = preg_replace('/:\d+$/', '', $domain);
        return $domain;
    }

    // ── Private helpers ──

    /**
     * Verify RSA-SHA256 signature from the FMZ API.
     * Payload format: {license_key}|{domain}|{status}|{expiry}
     */
    private function verifySignature(string $signature, string $sigPayload): bool
    {
        $pubKey = openssl_pkey_get_public($this->config['public_key']);
        if (!$pubKey) {
            return false;
        }
        $result = openssl_verify($sigPayload, base64_decode($signature), $pubKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Send a POST request to the FMZ License Manager REST API.
     * Returns decoded JSON with an added '_http_code' key, or null on network failure.
     */
    private function apiRequest(string $endpoint, array $data): ?array
    {
        $url = $this->config['api_base'] . '/' . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'X-FMZ-Client: Panelion/' . $this->getPanelionVersion(),
            ],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode === 0) {
            $this->logLicense('warning', "API request failed: {$endpoint}", ['error' => $error]);
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->logLicense('warning', "API returned non-JSON for {$endpoint}", ['http_code' => $httpCode]);
            return null;
        }

        // Attach HTTP status code so callers can inspect it
        $decoded['_http_code'] = $httpCode;

        return $decoded;
    }

    private function getCachedLicense(): ?array
    {
        $file = $this->rootPath . self::CACHE_FILE;
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data) || empty($data['license_key'])) {
            return null;
        }

        return $data;
    }

    private function saveCachedLicense(array $data): void
    {
        $file = $this->rootPath . self::CACHE_FILE;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function clearLicense(): void
    {
        $file = $this->rootPath . self::CACHE_FILE;
        if (file_exists($file)) {
            unlink($file);
        }

        try {
            $this->db->deleteFrom('settings', '`key` IN (?, ?, ?, ?)', [
                'license_key', 'license_domain', 'license_status', 'license_expiry'
            ]);
        } catch (\Exception $e) {
            // Ignore DB errors during cleanup
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            $existing = $this->db->fetch("SELECT id FROM settings WHERE `key` = ?", [$key]);
            if ($existing) {
                $this->db->update('settings', ['value' => $value], '`key` = ?', [$key]);
            } else {
                $this->db->insert('settings', ['key' => $key, 'value' => $value, 'type' => 'string']);
            }
        } catch (\Exception $e) {
            // Fail silently — cache file is the primary store
        }
    }

    private function logLicense(string $level, string $message, array $context = []): void
    {
        $logDir = $this->rootPath . '/storage/logs';
        if (!is_dir($logDir)) {
            return;
        }
        $logFile = $logDir . '/license-' . date('Y-m-d') . '.log';
        $ctx = !empty($context) ? ' ' . json_encode($context) : '';
        $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . $ctx . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function getPanelionVersion(): string
    {
        return defined('PANELION_VERSION') ? PANELION_VERSION : '1.0.0';
    }
}
