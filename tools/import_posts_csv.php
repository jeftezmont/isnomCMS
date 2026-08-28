<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Este importador solo debe ejecutarse por CLI.\n");
}

$csv = $argv[1] ?? null;
if (!$csv || !is_file($csv)) {
    exit("Uso: php tools/import_posts_csv.php posts.csv\n");
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';
load_env(ROOT_PATH . '/.env');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$config = require ROOT_PATH . '/config.php';
$pdo = App\Core\Database::connect($config);
$handle = fopen($csv, 'r');
$headers = fgetcsv($handle);
$required = ['title', 'slug', 'excerpt', 'content', 'status', 'published_at'];

if (!$headers || array_diff($required, $headers)) {
    exit("Columnas requeridas: " . implode(', ', $required) . "\n");
}

$stmt = $pdo->prepare('INSERT INTO posts (title, slug, excerpt, content, status, published_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE title = VALUES(title), excerpt = VALUES(excerpt), content = VALUES(content), status = VALUES(status), published_at = VALUES(published_at), updated_at = NOW()');
$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($headers, $row);
    $stmt->execute([$data['title'], $data['slug'], $data['excerpt'], $data['content'], $data['status'], $data['published_at']]);
    $count++;
}
fclose($handle);
echo "Importados o actualizados {$count} artículos.\n";

