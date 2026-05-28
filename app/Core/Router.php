<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array, middleware:array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function patch(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => rtrim($pattern, '/') ?: '/',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        try {
            foreach ($this->routes as $route) {
                $params = $this->match($route['method'], $route['pattern'], $request);
                if ($params === null) {
                    continue;
                }

                $request->params = $params;
                $handler = $this->resolve($route['handler']);
                $pipeline = array_reduce(
                    array_reverse($route['middleware']),
                    static fn (callable $next, callable $middleware): callable => static fn (Request $req) => $middleware($req, $next),
                    $handler
                );
                $pipeline($request);
                return;
            }

            Response::json(['error' => 'Route not found'], 404);
        } catch (Throwable $exception) {
            Logger::error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $payload = ['error' => 'Server error'];
            if (Config::bool('APP_DEBUG')) {
                $payload['debug'] = $exception->getMessage();
            }
            Response::json($payload, 500);
        }
    }

    private function match(string $method, string $pattern, Request $request): ?array
    {
        if ($method !== $request->method) {
            return null;
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        if ($regex === null || !preg_match('#^' . $regex . '$#', $request->path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function resolve(callable|array $handler): callable
    {
        if (is_array($handler) && is_string($handler[0])) {
            return [new $handler[0](), $handler[1]];
        }

        return $handler;
    }
}
