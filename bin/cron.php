<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';
load_env(ROOT_PATH . '/.env');
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

$config = require ROOT_PATH . '/config.php';
date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));
$logger = new \App\Core\Logger($config);
$storage = (string) ($config['storage_dir'] ?? ROOT_PATH . '/storage');
if (!is_dir($storage) && !@mkdir($storage, 0755, true) && !is_dir($storage)) {
    $logger->error('cron', 'No fue posible crear storage.');
    exit(1);
}
$lock = fopen($storage . '/cron.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $logger->warning('cron', 'Se omitió la ejecución porque otra instancia continúa activa.');
    exit(0);
}

$started = microtime(true);
$logger->info('cron', 'Inicio de tareas programadas.');
$tasks = [
    'scheduled_episodes' => function () use ($config): int {
        $pdo = \App\Core\Database::connect($config);
        $count = $pdo->exec("UPDATE podcast_episodes SET status = 'published', updated_at = NOW() WHERE status = 'scheduled' AND published_at <= NOW()");
        if ($count > 0) \App\Services\PublicCache::invalidate($config);
        return $count;
    },
    'temporary_tokens' => function () use ($config): int {
        $pdo = \App\Core\Database::connect($config);
        $queries = [
            'DELETE FROM remember_tokens WHERE expires_at < NOW() OR revoked_at IS NOT NULL',
            'DELETE FROM webauthn_challenges WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR consumed_at < DATE_SUB(NOW(), INTERVAL 1 DAY)',
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)',
            'DELETE FROM two_factor_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)',
        ];
        $count = 0;
        foreach ($queries as $sql) $count += $pdo->exec($sql);
        return $count;
    },
    'file_cache' => fn(): int => \App\Core\FileCache::fromConfig($config)->prune(),
];

$failed = false;
foreach ($tasks as $name => $task) {
    $taskStarted = microtime(true);
    try {
        $affected = $task();
        $logger->info('cron.' . $name, 'Tarea completada.', ['affected' => $affected, 'duration_ms' => (int) ((microtime(true) - $taskStarted) * 1000)]);
    } catch (\Throwable $exception) {
        $failed = true;
        $logger->exception($exception, 'cron.' . $name);
    }
}

$logger->info('cron', 'Fin de tareas programadas.', ['duration_ms' => (int) ((microtime(true) - $started) * 1000), 'status' => $failed ? 'partial' : 'ok']);
flock($lock, LOCK_UN);
fclose($lock);
exit($failed ? 1 : 0);
