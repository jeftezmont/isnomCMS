<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';
load_env(ROOT_PATH . '/.env');
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require $path;
});

function expect_release(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fetch_release(string $url, array $headers = []): array
{
    $lines = [];
    foreach ($headers as $name => $value) $lines[] = $name . ': ' . $value;
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'header' => implode("\r\n", $lines)]]);
    $body = file_get_contents($url, false, $context);
    return ['body' => (string) $body, 'headers' => $http_response_header ?? []];
}

$config = require ROOT_PATH . '/config.php';
$pdo = App\Core\Database::connect($config);
(new App\Services\RoleSeeder())->seed($pdo);
$roles = $pdo->query('SELECT slug FROM roles ORDER BY slug')->fetchAll(PDO::FETCH_COLUMN);
expect_release($roles === ['admin', 'author', 'editor', 'super_admin'], 'Faltan roles iniciales.');
expect_release((int) $pdo->query('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "super_admin"')->fetchColumn() >= 1, 'No existe un Super Admin.');
$adminCanManageRoles = (int) $pdo->query('SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = "admin" AND p.slug = "roles.manage"')->fetchColumn();
expect_release($adminCanManageRoles === 0, 'Admin no debe administrar roles reservados.');
$authorCanPublish = (int) $pdo->query('SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = "author" AND p.slug = "posts.publish"')->fetchColumn();
expect_release($authorCanPublish === 0, 'Autor no debe publicar automáticamente.');
$lastSuperAdmin = (int) $pdo->query('SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "super_admin" ORDER BY ur.user_id LIMIT 1')->fetchColumn();
if ((int) $pdo->query('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "super_admin"')->fetchColumn() === 1) {
    try {
        (new App\Models\User($pdo))->assignRole($lastSuperAdmin, 'admin', null);
        throw new RuntimeException('Fue posible degradar al último Super Admin.');
    } catch (InvalidArgumentException) {
    }
}

$cache = App\Core\FileCache::fromConfig($config);
$cache->set('release.acceptance', ['ok' => true], 30);
expect_release(($cache->get('release.acceptance')['ok'] ?? false) === true, 'La caché no conserva valores.');
$cache->delete('release.acceptance');

$base = rtrim((string) ($config['app_url'] ?? 'http://127.0.0.1:8000'), '/');
if (!str_contains($base, '127.0.0.1') && !str_contains($base, 'localhost')) $base = 'http://127.0.0.1:8000';
foreach (['/sitemap.xml', '/blog/feed.xml', '/podcast/feed.xml'] as $path) {
    $response = fetch_release($base . $path);
    expect_release(str_starts_with(ltrim($response['body']), '<?xml'), $path . ' no inicia con XML válido.');
    $xml = simplexml_load_string($response['body']);
    expect_release($xml !== false, $path . ' contiene XML inválido.');
    $etagLine = current(array_values(array_filter($response['headers'], fn(string $line): bool => stripos($line, 'ETag:') === 0))) ?: '';
    expect_release($etagLine !== '', $path . ' no incluye ETag.');
    $etag = trim(substr($etagLine, 5));
    $conditional = fetch_release($base . $path, ['If-None-Match' => $etag]);
    expect_release(str_contains($conditional['headers'][0] ?? '', '304'), $path . ' no responde 304.');
}

echo "Release acceptance checks: OK\n";
