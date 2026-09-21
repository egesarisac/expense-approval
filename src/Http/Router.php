<?php
declare(strict_types=1);

namespace App\Http;

use Closure;
use InvalidArgumentException;

final class Router
{
    private array $routes = [];
    private string $routeName = 'unknown';

    public function add(string $method, string $path, callable $handler): void
    {
        if (!preg_match('/\A[A-Z]+\z/', $method) || !str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Routes require an uppercase method and an absolute path.');
        }
        $pattern = '';
        foreach (preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
            $pattern .= preg_match('/\A\{[a-zA-Z_][a-zA-Z0-9_]*\}\z/', $part)
                ? '([^/]+)' : preg_quote($part, '~');
        }
        $this->routes[$path]['pattern'] = '~\A' . $pattern . '\z~';
        $this->routes[$path]['handlers'][$method] = Closure::fromCallable($handler);
    }

    public function routeName(): string
    {
        return $this->routeName;
    }

    public function dispatch(string $method, string $path): JsonResponse
    {
        $this->routeName = 'unknown';
        // Prefer an exact registered path over a matching parameterized path.
        $candidates = isset($this->routes[$path]) ? [$path => $this->routes[$path]] : $this->routes;
        $allowed = [];
        foreach ($candidates as $template => $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            $this->routeName = $template;
            if (isset($route['handlers'][$method])) {
                return $route['handlers'][$method](...array_slice($matches, 1));
            }
            $allowed = array_merge($allowed, array_keys($route['handlers']));
        }
        if ($allowed !== []) {
            throw new ApiException(405, 'method_not_allowed', 'Method not allowed.',
                headers: ['Allow' => implode(', ', array_unique($allowed))]);
        }
        throw new ApiException(404, 'route_not_found', 'Route not found.');
    }
}
