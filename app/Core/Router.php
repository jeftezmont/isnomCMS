<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function __construct(private array $config)
    {
    }

    public function get(string $path, array $handler): void
    {
        $this->match(['GET'], $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->match(['POST'], $path, $handler);
    }

    public function match(array $methods, string $path, array $handler): void
    {
        $pattern = '#^' . preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#';
        $this->routes[] = compact('methods', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = rtrim($uri, '/') ?: '/';
        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true) || !preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }
            [$class, $action] = $route['handler'];
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            (new $class($this->config))->$action($params);
            return;
        }
        http_response_code(404);
        (new \App\Controllers\SiteController($this->config))->notFound();
    }
}

