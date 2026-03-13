<?php
/**
 * Panelion - API Handler
 */

namespace Panelion\Core;

class API
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handle(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');

        $method = $_SERVER['REQUEST_METHOD'];
        $path = trim($_GET['route'] ?? '', '/');

        // CORS headers for API
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Authenticate API request
        $user = $this->authenticateRequest();
        if (!$user) {
            $this->respond(['error' => 'Unauthorized'], 401);
            return;
        }

        // Rate limiting
        $ip = Security::getClientIP();
        $rateLimit = $this->app->config('security.api_rate_limit', 60);
        if (!Security::checkRateLimit($this->app->db(), 'api:' . $ip, $rateLimit, 60)) {
            $this->respond(['error' => 'Rate limit exceeded'], 429);
            return;
        }
        Security::recordRateLimitHit($this->app->db(), 'api:' . $ip);

        // Route API requests
        try {
            $this->routeAPI($method, $path, $user);
        } catch (\Exception $e) {
            $this->app->logger()->error('API error: ' . $e->getMessage());
            $this->respond(['error' => 'Internal server error'], 500);
        }
    }

    private function authenticateRequest(): ?array
    {
        // Check for API key in header
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (empty($apiKey)) {
            // Check Authorization header
            $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/^Bearer\s+(.+)$/i', $auth, $matches)) {
                $apiKey = $matches[1];
            }
        }

        if (empty($apiKey)) {
            return null;
        }

        $hashedKey = hash('sha256', $apiKey);
        $token = $this->app->db()->fetch(
            "SELECT * FROM api_tokens WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > NOW())",
            [$hashedKey]
        );

        if (!$token) {
            return null;
        }

        $user = $this->app->db()->fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [$token['user_id']]);

        if ($user) {
            // Update last used
            $this->app->db()->update('api_tokens', ['last_used_at' => date('Y-m-d H:i:s')], 'id = ?', [$token['id']]);
        }

        return $user;
    }

    private function routeAPI(string $method, string $path, array $user): void
    {
        $segments = explode('/', $path);
        $resource = $segments[0] ?? '';

        switch ($resource) {
            case 'system':
                $this->handleSystem($method, $segments, $user);
                break;
            case 'users':
                $this->handleUsers($method, $segments, $user);
                break;
            case 'domains':
                $this->handleDomains($method, $segments, $user);
                break;
            case 'databases':
                $this->handleDatabases($method, $segments, $user);
                break;
            case 'applications':
                $this->handleApplications($method, $segments, $user);
                break;
            case 'ssl':
                $this->handleSSL($method, $segments, $user);
                break;
            case 'dns':
                $this->handleDNS($method, $segments, $user);
                break;
            case 'email':
                $this->handleEmail($method, $segments, $user);
                break;
            case 'backup':
                $this->handleBackup($method, $segments, $user);
                break;
            default:
                $this->respond(['error' => 'Not found'], 404);
        }
    }

    private function handleSystem(string $method, array $segments, array $user): void
    {
        if ($method === 'GET') {
            $action = $segments[1] ?? 'info';
            switch ($action) {
                case 'info':
                    $this->respond([
                        'version' => PANELION_VERSION,
                        'os' => SystemCommand::getOSInfo(),
                        'stats' => SystemCommand::getSystemStats(),
                    ]);
                    break;
                case 'services':
                    $services = ['nginx', 'apache2', 'mysql', 'postgresql', 'redis', 'mongodb', 'postfix', 'dovecot', 'named'];
                    $status = [];
                    foreach ($services as $svc) {
                        $status[$svc] = SystemCommand::isServiceRunning($svc);
                    }
                    $this->respond(['services' => $status]);
                    break;
                default:
                    $this->respond(['error' => 'Not found'], 404);
            }
        }
    }

    private function handleUsers(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Users API endpoint', 'method' => $method]);
    }

    private function handleDomains(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Domains API endpoint', 'method' => $method]);
    }

    private function handleDatabases(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Databases API endpoint', 'method' => $method]);
    }

    private function handleApplications(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Applications API endpoint', 'method' => $method]);
    }

    private function handleSSL(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'SSL API endpoint', 'method' => $method]);
    }

    private function handleDNS(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'DNS API endpoint', 'method' => $method]);
    }

    private function handleEmail(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Email API endpoint', 'method' => $method]);
    }

    private function handleBackup(string $method, array $segments, array $user): void
    {
        $this->respond(['message' => 'Backup API endpoint', 'method' => $method]);
    }

    private function respond(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
