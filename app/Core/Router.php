<?php

declare(strict_types=1);

namespace App\Core;

use LogicException;

/**
 * Router sederhana: mencocokkan method + path ke [Controller, method].
 *
 * Contoh:
 *   $router->get('/alat/{slug}', [CatalogController::class, 'show']);
 *   $router->post('/staff/alat', [EquipmentController::class, 'store'], ['role:staff,admin']);
 *
 * Middleware yang tersedia:
 *   'auth'              harus login
 *   'guest'             hanya untuk yang belum login
 *   'role:staff,admin'  harus login dengan salah satu role tersebut
 *
 * Semua request selain GET/HEAD otomatis diperiksa token CSRF-nya.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function dispatch(string $method): void
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        // Form HTML hanya mendukung GET/POST, jadi PUT/PATCH/DELETE dikirim
        // sebagai POST dengan field tersembunyi _method (method spoofing).
        if ($method === 'POST' && is_string($_POST['_method'] ?? null)) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $path = Request::path();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $methodMismatch = true;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            $this->runMiddleware($route['middleware']);
            if ($method !== 'GET') {
                Csrf::verify();
            }

            [$class, $action] = $route['handler'];
            (new $class())->$action(...array_values($params));
            return;
        }

        throw new HttpException($methodMismatch ? 405 : 404);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        // {slug} -> named group yang cocok dengan satu segmen URL
        $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/');
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $item) {
            [$name, $argument] = array_pad(explode(':', $item, 2), 2, '');
            match ($name) {
                'auth'  => Auth::requireLogin(),
                'guest' => Auth::requireGuest(),
                'role'  => Auth::requireRole(...explode(',', $argument)),
                default => throw new LogicException("Middleware tidak dikenal: {$name}"),
            };
        }
    }
}
