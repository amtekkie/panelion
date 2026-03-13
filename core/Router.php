<?php
/**
 * Panelion - Router
 */

namespace Panelion\Core;

class Router
{
    private App $app;
    private array $routes = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->buildPattern($path),
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        require PANELION_ROOT . '/views/errors/404.php';
    }

    private function callHandler(string $handler, array $params): void
    {
        // Handle Auth controller separately
        if (strpos($handler, 'Auth@') === 0) {
            $method = substr($handler, 5);
            $controller = $this->app->auth();
            if (method_exists($controller, $method)) {
                $controller->$method($params);
                return;
            }
        }

        // Module controllers
        $parts = explode('@', $handler);
        if (count($parts) !== 2) {
            throw new \RuntimeException("Invalid handler format: {$handler}");
        }

        [$controllerPath, $method] = $parts;
        $className = 'Panelion\\Modules\\' . $controllerPath;

        if (!class_exists($className)) {
            throw new \RuntimeException("Controller not found: {$className}");
        }

        $controller = new $className();
        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method not found: {$className}::{$method}");
        }

        // Intelligently call method: pass array if it expects array, spread if it expects scalars
        if (empty($params)) {
            $controller->$method();
        } else {
            $ref = new \ReflectionMethod($controller, $method);
            $refParams = $ref->getParameters();
            $firstType = !empty($refParams) ? ($refParams[0]->getType()?->getName() ?? null) : null;
            if ($firstType === 'array') {
                $controller->$method($params);
            } else {
                $controller->$method(...array_values($params));
            }
        }
    }
}
