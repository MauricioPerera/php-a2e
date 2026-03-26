<?php
declare(strict_types=1);
namespace PHPA2E\Http;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void { $this->routes['GET'][] = ['pattern' => self::toRegex($path), 'handler' => $handler]; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][] = ['pattern' => self::toRegex($path), 'handler' => $handler]; }

    public function dispatch(string $method, string $uri, string $body): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $data = $body !== '' ? (json_decode($body, true) ?? []) : [];
                try {
                    return ($route['handler'])($data, $params, $query);
                } catch (\Throwable $e) {
                    return [400, ['error' => $e->getMessage()]];
                }
            }
        }
        return [404, ['error' => 'Not found']];
    }

    private static function toRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
