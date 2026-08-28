<?php

namespace App\Core;

final class Logger
{
    private string $logDirectory;

    public function __construct(private array $config)
    {
        $this->logDirectory = $config['log_dir'] ?? ROOT_PATH . '/storage/logs';
    }

    public function error(string $module, string $message, array $context = [], ?string $errorId = null): string
    {
        return $this->write('ERROR', $module, $message, $context, $errorId);
    }

    public function warning(string $module, string $message, array $context = []): string
    {
        return $this->write('WARNING', $module, $message, $context);
    }

    public function info(string $module, string $message, array $context = []): string
    {
        return $this->write('INFO', $module, $message, $context);
    }

    public function exception(\Throwable $exception, string $module = 'application'): string
    {
        return $this->error($module, $exception->getMessage(), [
            'exception' => $exception::class,
            'file' => $this->relativePath($exception->getFile()),
            'line' => $exception->getLine(),
        ]);
    }

    private function write(string $level, string $module, string $message, array $context, ?string $errorId = null): string
    {
        $errorId ??= strtoupper(bin2hex(random_bytes(3)));
        $line = sprintf(
            "[%s] %s %s [%s] %s",
            date('Y-m-d H:i:s'),
            $level,
            $this->cleanLabel($module),
            $errorId,
            $this->redactString($message)
        );

        $safeContext = $this->redact($context);
        if ($safeContext !== []) {
            $encoded = json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($encoded)) {
                $line .= ' ' . $encoded;
            }
        }
        $line .= PHP_EOL;

        if (!$this->ensureDirectory() || @file_put_contents($this->logDirectory . '/app.log', $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }

        return $errorId;
    }

    private function ensureDirectory(): bool
    {
        if (is_dir($this->logDirectory)) {
            return is_writable($this->logDirectory);
        }
        return @mkdir($this->logDirectory, 0755, true) && is_writable($this->logDirectory);
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/pass|secret|token|cookie|authorization|credential|private.?key/i', $key)) {
            return '[REDACTED]';
        }
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $itemKey => $itemValue) {
                $safe[$itemKey] = $this->redact($itemValue, (string) $itemKey);
            }
            return $safe;
        }
        if (is_string($value)) {
            return $this->redactString($value);
        }
        return $value;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/(password|secret|token|cookie|authorization)(\s*[=:]\s*)[^\s,;]+/i', '$1$2[REDACTED]', $value) ?? $value;
        $value = preg_replace('#(mysql:[^\s]*?)(?:password|pwd)=[^;\s]+#i', '$1password=[REDACTED]', $value) ?? $value;
        return trim($value);
    }

    private function cleanLabel(string $value): string
    {
        $value = preg_replace('/[^a-z0-9._-]+/i', '-', trim($value)) ?? 'application';
        return $value !== '' ? $value : 'application';
    }

    private function relativePath(string $path): string
    {
        $root = rtrim((string) (defined('ROOT_PATH') ? ROOT_PATH : ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $root !== DIRECTORY_SEPARATOR && str_starts_with($path, $root)
            ? substr($path, strlen($root))
            : basename($path);
    }
}
