<?php
declare(strict_types=1);

namespace App\Support;

final class Request
{
    public function __construct(
        private readonly array $server,
        private readonly array $get,
        private readonly array $post
    ) {
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function fullUri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function basePath(): string
    {
        $candidates = [
            (string) ($this->server['SCRIPT_NAME'] ?? ''),
            (string) ($this->server['PHP_SELF'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = str_replace('\\', '/', trim($candidate));
            if ($candidate === '') {
                continue;
            }

            if (preg_match('#^(.*?/public)(?:/.*)?$#', $candidate, $matches) === 1) {
                return rtrim((string) $matches[1], '/');
            }

            $dir = str_replace('\\', '/', dirname($candidate));
            if ($dir !== '' && $dir !== '.' && $dir !== '/') {
                return rtrim($dir, '/');
            }
        }

        return '';
    }

    public function path(): string
    {
        $uri = parse_url($this->fullUri(), PHP_URL_PATH) ?: '/';
        $uri = str_replace('\\', '/', $uri);
        $basePath = $this->basePath();

        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        if ($uri === '' || $uri === false) {
            return '/';
        }

        if (!str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        $uri = preg_replace('#/+#', '/', $uri) ?: '/';
        $uri = preg_replace('#/index\.php$#', '/', $uri) ?: '/';

        return $uri === '' ? '/' : $uri;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->get[$key] ?? $default;

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $this->get[$key] ?? $default;

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->get);
    }
}
