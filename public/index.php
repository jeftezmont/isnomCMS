<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

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
\App\Core\ErrorHandler::register($config);
(new \App\Services\SecurityHeaders($config))->send();
\App\Core\Session::start($config);

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$isSystemPath = str_starts_with($requestPath, '/admin')
    || str_starts_with($requestPath, '/assets')
    || in_array($requestPath, ['/sitemap.xml', '/robots.txt', '/favicon.ico'], true);

try {
    if (!$isSystemPath) {
        $settings = (new \App\Models\Setting(\App\Core\Database::connect($config)))->all();
        if (($settings['coming_soon_mode'] ?? '0') === '1') {
            http_response_code(503);
            header('Retry-After: 3600');
            (new \App\Controllers\SiteController($config))->comingSoon();
            exit;
        }
    }
} catch (\Throwable) {
}

$router = new \App\Core\Router($config);
$router->get('/', [\App\Controllers\SiteController::class, 'home']);
$router->get('/blog', [\App\Controllers\BlogController::class, 'index']);
$router->get('/blog/{slug}', [\App\Controllers\BlogController::class, 'show']);
$router->get('/podcast', [\App\Controllers\PodcastController::class, 'index']);
$router->get('/podcast/feed.xml', [\App\Controllers\PodcastController::class, 'feed']);
$router->get('/podcast/{slug}/feed.xml', [\App\Controllers\PodcastController::class, 'feed']);
$router->get('/podcast/{slug}/{episode}', [\App\Controllers\PodcastController::class, 'episode']);
$router->get('/podcast/{slug}', [\App\Controllers\PodcastController::class, 'show']);
$router->get('/posts/{slug}', [\App\Controllers\BlogController::class, 'legacyRedirect']);
$router->get('/403', [\App\Controllers\SiteController::class, 'forbidden']);
$router->get('/500', [\App\Controllers\SiteController::class, 'serverError']);
$router->get('/coming-soon', [\App\Controllers\SiteController::class, 'comingSoon']);
$router->get('/sitemap.xml', [\App\Controllers\SeoController::class, 'sitemap']);
$router->get('/robots.txt', [\App\Controllers\SeoController::class, 'robots']);

$router->match(['GET', 'POST'], '/admin/login', [\App\Controllers\AuthController::class, 'login']);
$router->post('/admin/passkeys/login/options', [\App\Controllers\AuthController::class, 'passkeyLoginOptions']);
$router->post('/admin/passkeys/login/verify', [\App\Controllers\AuthController::class, 'passkeyLoginVerify']);
$router->post('/admin/passkeys/register/options', [\App\Controllers\AuthController::class, 'passkeyRegisterOptions']);
$router->post('/admin/passkeys/register/verify', [\App\Controllers\AuthController::class, 'passkeyRegisterVerify']);
$router->post('/admin/logout', [\App\Controllers\AuthController::class, 'logout']);
$router->get('/admin', [\App\Controllers\AdminController::class, 'dashboard']);
$router->get('/admin/health', [\App\Controllers\AdminController::class, 'health']);
$router->get('/admin/deploy', [\App\Controllers\AdminController::class, 'deploy']);
$router->match(['GET', 'POST'], '/admin/setup', [\App\Controllers\AdminController::class, 'setup']);
$router->match(['GET', 'POST'], '/admin/settings', [\App\Controllers\AdminController::class, 'settings']);
$router->get('/admin/export/posts', [\App\Controllers\AdminController::class, 'exportPosts']);
$router->get('/admin/backups', [\App\Controllers\AdminController::class, 'backups']);
$router->post('/admin/backups/download', [\App\Controllers\AdminController::class, 'downloadBackup']);
$router->post('/admin/posts/preview', [\App\Controllers\AdminController::class, 'previewPostMarkdown']);
$router->match(['GET', 'POST'], '/admin/posts', [\App\Controllers\AdminController::class, 'posts']);
$router->match(['GET', 'POST'], '/admin/posts/create', [\App\Controllers\AdminController::class, 'postForm']);
$router->match(['GET', 'POST'], '/admin/posts/{id}/edit', [\App\Controllers\AdminController::class, 'postForm']);
$router->post('/admin/posts/{id}/delete', [\App\Controllers\AdminController::class, 'deletePost']);
$router->get('/admin/podcasts', [\App\Controllers\PodcastAdminController::class, 'podcasts']);
$router->match(['GET', 'POST'], '/admin/podcasts/create', [\App\Controllers\PodcastAdminController::class, 'podcastForm']);
$router->match(['GET', 'POST'], '/admin/podcasts/{id}/edit', [\App\Controllers\PodcastAdminController::class, 'podcastForm']);
$router->post('/admin/podcasts/{id}/delete', [\App\Controllers\PodcastAdminController::class, 'deletePodcast']);
$router->get('/admin/podcast-episodes', [\App\Controllers\PodcastAdminController::class, 'episodes']);
$router->match(['GET', 'POST'], '/admin/podcast-episodes/create', [\App\Controllers\PodcastAdminController::class, 'episodeForm']);
$router->match(['GET', 'POST'], '/admin/podcast-episodes/{id}/edit', [\App\Controllers\PodcastAdminController::class, 'episodeForm']);
$router->post('/admin/podcast-episodes/{id}/delete', [\App\Controllers\PodcastAdminController::class, 'deleteEpisode']);
$router->post('/admin/podcast/audio/validate', [\App\Controllers\PodcastAdminController::class, 'validateDropbox']);
$router->match(['GET', 'POST'], '/admin/media', [\App\Controllers\AdminController::class, 'media']);
$router->post('/admin/media/{id}/delete', [\App\Controllers\AdminController::class, 'deleteMedia']);
$router->match(['GET', 'POST'], '/admin/users', [\App\Controllers\AdminController::class, 'users']);
$router->post('/admin/users/{id}/delete', [\App\Controllers\AdminController::class, 'deleteUser']);
$router->get('/admin/passkeys', [\App\Controllers\AdminController::class, 'passkeys']);
$router->post('/admin/passkeys/{id}/delete', [\App\Controllers\AdminController::class, 'deletePasskey']);
$router->match(['GET', 'POST'], '/admin/categories', [\App\Controllers\AdminController::class, 'categories']);
$router->post('/admin/categories/{id}/delete', [\App\Controllers\AdminController::class, 'deleteCategory']);
$router->match(['GET', 'POST'], '/admin/tags', [\App\Controllers\AdminController::class, 'tags']);
$router->post('/admin/tags/{id}/delete', [\App\Controllers\AdminController::class, 'deleteTag']);

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $requestPath);
} catch (\Throwable $exception) {
    $errorId = \App\Core\ErrorHandler::report($exception, 'router');
    $development = in_array(strtolower((string) ($config['app_env'] ?? 'production')), ['local', 'development', 'dev'], true);
    (new \App\Controllers\SiteController($config))->serverError($errorId, $development ? $exception->getMessage() : null);
}
