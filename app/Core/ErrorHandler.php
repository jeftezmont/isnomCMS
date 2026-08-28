<?php

namespace App\Core;

use App\Controllers\SiteController;

final class ErrorHandler
{
    private static array $config = [];
    private static ?Logger $logger = null;
    private static bool $handling = false;

    public static function register(array $config): void
    {
        self::$config = $config;
        self::$logger = new Logger($config);
        $development = self::isDevelopment();

        ini_set('display_errors', $development ? '1' : '0');
        ini_set('display_startup_errors', $development ? '1' : '0');

        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function report(\Throwable $exception, string $module = 'application'): string
    {
        return (self::$logger ?? new Logger(self::$config))->exception($exception, $module);
    }

    public static function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        (self::$logger ?? new Logger(self::$config))->warning('php', $message, [
            'severity' => $severity,
            'file' => self::relativePath($file),
            'line' => $line,
        ]);

        return false;
    }

    public static function handleException(\Throwable $exception): void
    {
        if (self::$handling) {
            self::plainFallback();
            return;
        }
        self::$handling = true;
        $errorId = self::report($exception);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Error ID: ' . $errorId . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        try {
            (new SiteController(self::$config))->serverError(
                $errorId,
                self::isDevelopment() ? $exception->getMessage() : null
            );
        } catch (\Throwable) {
            self::plainFallback($errorId);
        }
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        $errorId = (self::$logger ?? new Logger(self::$config))->error('php-fatal', $error['message'], [
            'type' => $error['type'],
            'file' => self::relativePath($error['file']),
            'line' => $error['line'],
        ]);

        if (PHP_SAPI !== 'cli' && !headers_sent() && !self::$handling) {
            http_response_code(500);
            self::plainFallback($errorId);
        }
    }

    private static function isDevelopment(): bool
    {
        return in_array(strtolower((string) (self::$config['app_env'] ?? 'production')), ['local', 'development', 'dev'], true);
    }

    private static function relativePath(string $path): string
    {
        $root = rtrim((string) (defined('ROOT_PATH') ? ROOT_PATH : ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return $root !== DIRECTORY_SEPARATOR && str_starts_with($path, $root)
            ? substr($path, strlen($root))
            : basename($path);
    }

    private static function plainFallback(?string $errorId = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Algo salió mal.\n";
        if ($errorId) {
            echo 'Error ID: ' . $errorId;
        }
    }
}
