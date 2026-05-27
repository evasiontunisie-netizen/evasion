<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public array $params = [],
        public ?array $user = null,
    ) {
    }

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $headers['Content-Type'] ?? '';
        $raw = file_get_contents('php://input') ?: '';
        $body = $_POST;

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        return new self($method, rtrim($path, '/') ?: '/', $_GET, $body, $headers);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function wantsJson(): bool
    {
        $accept = $this->headers['Accept'] ?? $this->headers['accept'] ?? '';
        return str_contains($this->path, '/api/') || str_contains($accept, 'application/json');
    }
}
