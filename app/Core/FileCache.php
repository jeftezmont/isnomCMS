<?php

namespace App\Core;

final class FileCache
{
    public function __construct(private string $directory, private int $defaultTtl = 900)
    {
    }

    public static function fromConfig(array $config): self
    {
        return new self((string) ($config['cache_dir'] ?? ROOT_PATH . '/storage/cache'), (int) ($config['cache_ttl'] ?? 900));
    }

    public function get(string $key): mixed
    {
        $entry = $this->read($key);
        return $entry === null ? null : $entry['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->ensureDirectory();
        $payload = serialize(['expires_at' => time() + max(1, $ttl ?? $this->defaultTtl), 'value' => $value]);
        $path = $this->path($key);
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $payload, LOCK_EX) === false || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('No fue posible escribir la caché.');
        }
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    public function remember(string $key, callable $resolver, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) return $cached;
        $this->ensureDirectory();
        $lock = fopen($this->path($key) . '.lock', 'c');
        if ($lock === false) return $resolver();
        try {
            flock($lock, LOCK_EX);
            $cached = $this->get($key);
            if ($cached !== null) return $cached;
            $value = $resolver();
            $this->set($key, $value, $ttl);
            return $value;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function clear(): int
    {
        if (!is_dir($this->directory)) return 0;
        $removed = 0;
        foreach (glob($this->directory . '/*.{cache,lock,tmp}', GLOB_BRACE) ?: [] as $path) {
            if (is_file($path) && @unlink($path)) $removed++;
        }
        return $removed;
    }

    public function prune(): int
    {
        if (!is_dir($this->directory)) return 0;
        $removed = 0;
        foreach (glob($this->directory . '/*.cache') ?: [] as $path) {
            $raw = @file_get_contents($path);
            $entry = is_string($raw) ? @unserialize($raw, ['allowed_classes' => false]) : null;
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) <= time()) {
                if (@unlink($path)) $removed++;
            }
        }
        return $removed;
    }

    private function read(string $key): ?array
    {
        $path = $this->path($key);
        $raw = is_file($path) ? @file_get_contents($path) : false;
        $entry = is_string($raw) ? @unserialize($raw, ['allowed_classes' => false]) : null;
        if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) <= time()) {
            if (is_file($path)) @unlink($path);
            return null;
        }
        return $entry;
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.cache';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No fue posible crear el directorio de caché.');
        }
    }
}
