<?php
declare(strict_types=1);

require_once __DIR__ . '/http.php';

/** Minimal REST router: $r->get('/accounts/{id}', fn(int $id) => ...). */
final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): void   { $this->add('POST', $path, $handler); }
    public function put(string $path, callable $handler): void    { $this->add('PUT', $path, $handler); }
    public function delete(string $path, callable $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $path) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        $allowed = [];
        foreach ($this->routes as [$m, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($m !== $method) {
                $allowed[] = $m;
                continue;
            }
            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            $result = $handler(...$params);
            json_out($result ?? ['ok' => true], $method === 'POST' ? 201 : 200);
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            fail(405, 'Method not allowed.');
        }
        fail(404, 'Endpoint not found.');
    }
}
