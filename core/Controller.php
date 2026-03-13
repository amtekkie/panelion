<?php
/**
 * Panelion - Base Controller
 */

namespace Panelion\Core;

abstract class Controller
{
    protected App $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    protected function view(string $view, array $data = []): void
    {
        $data['app'] = $this->app;
        $data['auth'] = $this->app->auth();
        $data['user'] = $this->app->auth()->user();
        $data['csrf_token'] = $this->app->session()->get('csrf_token');

        extract($data);

        $viewFile = PANELION_ROOT . '/modules/' . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewFile)) {
            $viewFile = PANELION_ROOT . '/views/' . str_replace('.', '/', $view) . '.php';
        }

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Wrap in layout unless it's an AJAX request
        if (!$this->isAjax()) {
            $layout = $data['layout'] ?? 'layouts/main';
            $layoutFile = PANELION_ROOT . '/views/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                ob_start();
                require $layoutFile;
                $html = ob_get_clean();
            } else {
                $html = $content;
            }
        } else {
            $html = $content;
        }

        // Rewrite URLs for subdirectory installations
        $basePath = $this->app->basePath();
        if ($basePath !== '') {
            $html = preg_replace_callback(
                '/(href|action|src)="\/(?!\/|https?:)([^"]*)"/',
                function ($m) use ($basePath) {
                    return $m[1] . '="' . $basePath . '/' . $m[2] . '"';
                },
                $html
            );
            $html = preg_replace_callback(
                '/fetch\(\'\/(?!\/)/',
                function ($m) use ($basePath) {
                    return "fetch('" . $basePath . "/";
                },
                $html
            );
        }

        echo $html;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $url): void
    {
        // Prepend base path for relative URLs
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = $this->app->url($url);
        }
        header('Location: ' . $url);
        exit;
    }

    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function validateCSRF(): bool
    {
        $token = $this->input('csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return Security::verifyCSRFToken($token, $this->app->session());
    }

    protected function requireAdmin(): void
    {
        if (!$this->app->auth()->user() || $this->app->auth()->user()['role'] !== 'admin') {
            $this->redirect('/dashboard');
        }
    }

    protected function requirePermission(string $permission): void
    {
        if (!$this->app->auth()->hasPermission($permission)) {
            http_response_code(403);
            $this->view('errors/403', ['message' => 'Insufficient permissions']);
        }
    }
}
