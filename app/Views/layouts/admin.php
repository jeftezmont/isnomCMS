<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> / jeftezmont</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon/favicon-16x16.png') ?>">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('favicon/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=35">
    <?php
    try {
        $themeSettings = (new \App\Models\Setting(\App\Core\Database::connect($config)))->all();
    } catch (\Throwable) {
        $themeSettings = [];
    }
    ?>
    <?php if (!empty($themeSettings['accent']) || !empty($themeSettings['accent_soft'])): ?>
    <style>
        :root {
            <?php if (!empty($themeSettings['accent'])): ?>--accent: <?= e($themeSettings['accent']) ?>;<?php endif; ?>
            <?php if (!empty($themeSettings['accent_soft'])): ?>--accent-soft: <?= e($themeSettings['accent_soft']) ?>;<?php endif; ?>
        }
    </style>
    <?php endif; ?>
    <script defer src="<?= asset('vendor/qrcode-generator/qrcode.js') ?>"></script>
    <script defer src="<?= asset('js/admin.js') ?>?v=33"></script>
</head>
<body class="admin-shell">
    <aside class="admin-nav">
        <a class="admin-mark" href="/admin">jefté montenegro<br><span>computer engineer</span></a>
        <nav>
            <?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; $can = fn(string $permission): bool => \App\Core\Gate::allows($config, $permission); ?>
            <?php if ($can('dashboard.view')): ?>
            <a class="nav-link <?= $path === '/admin' ? 'active' : '' ?>" href="/admin"><?= admin_icon('house') ?>Dashboard</a>
            <?php endif; ?>
            <?php if ($can('posts.view')): ?>
            <a class="nav-link <?= str_starts_with($path, '/admin/posts') && $path !== '/admin/posts/create' ? 'active' : '' ?>" href="/admin/posts"><?= admin_icon('file-text') ?>Artículos</a>
            <?php endif; ?>
            <?php if ($can('posts.create')): ?>
            <a class="nav-link <?= $path === '/admin/posts/create' ? 'active' : '' ?>" href="/admin/posts/create"><?= admin_icon('plus') ?>Nuevo artículo</a>
            <?php endif; ?>
            <?php if ($can('podcast.view')): ?>
            <a class="nav-link <?= str_starts_with($path, '/admin/podcasts') ? 'active' : '' ?>" href="/admin/podcasts"><?= admin_icon('mic') ?>Podcast</a>
            <a class="nav-link <?= str_starts_with($path, '/admin/podcast-episodes') ? 'active' : '' ?>" href="/admin/podcast-episodes"><?= admin_icon('list-audio') ?>Episodios</a>
            <?php endif; ?>
            <?php if ($can('taxonomy.manage')): ?>
            <a class="nav-link <?= $path === '/admin/categories' ? 'active' : '' ?>" href="/admin/categories"><?= admin_icon('folder') ?>Categorías</a>
            <a class="nav-link <?= $path === '/admin/tags' ? 'active' : '' ?>" href="/admin/tags"><?= admin_icon('tag') ?>Etiquetas</a>
            <?php endif; ?>
            <?php if ($can('media.view')): ?>
            <a class="nav-link <?= $path === '/admin/media' ? 'active' : '' ?>" href="/admin/media"><?= admin_icon('image') ?>Medios</a>
            <?php endif; ?>
            <?php if ($can('users.view')): ?>
            <a class="nav-link <?= $path === '/admin/users' ? 'active' : '' ?>" href="/admin/users"><?= admin_icon('user') ?>Usuarios</a>
            <?php endif; ?>
            <?php if ($can('roles.manage')): ?>
            <a class="nav-link <?= $path === '/admin/roles' ? 'active' : '' ?>" href="/admin/roles"><?= admin_icon('key-round') ?>Roles y permisos</a>
            <?php endif; ?>
            <?php if ($can('security.view')): ?>
            <a class="nav-link <?= str_starts_with($path, '/admin/security') || $path === '/admin/passkeys' ? 'active' : '' ?>" href="/admin/security"><?= admin_icon('shield') ?>Seguridad</a>
            <?php endif; ?>
            <?php if ($can('tools.health')): ?>
            <a class="nav-link <?= $path === '/admin/health' ? 'active' : '' ?>" href="/admin/health"><?= admin_icon('activity') ?>Salud del sitio</a>
            <?php endif; ?>
            <?php if ($can('tools.backups')): ?>
            <a class="nav-link <?= $path === '/admin/backups' ? 'active' : '' ?>" href="/admin/backups"><?= admin_icon('archive') ?>Backups</a>
            <?php endif; ?>
            <?php if ($can('tools.deploy')): ?>
            <a class="nav-link <?= $path === '/admin/deploy' ? 'active' : '' ?>" href="/admin/deploy"><?= admin_icon('rocket') ?>Deploy</a>
            <?php endif; ?>
            <?php if ($can('tools.setup')): ?>
            <a class="nav-link <?= $path === '/admin/setup' ? 'active' : '' ?>" href="/admin/setup"><?= admin_icon('wrench') ?>Configuración</a>
            <?php endif; ?>
            <?php if ($can('settings.view')): ?>
            <a class="nav-link <?= $path === '/admin/settings' ? 'active' : '' ?>" href="/admin/settings"><?= admin_icon('settings') ?>Ajustes</a>
            <?php endif; ?>
        </nav>
        <form class="admin-logout" method="post" action="/admin/logout"><?= \App\Core\Csrf::field() ?><button><?= admin_icon('log-out') ?>Salir</button></form>
    </aside>
    <main class="admin-main">
        <header class="admin-top"><span>/admin</span><strong><?= e($title ?? '') ?></strong></header>
        <div class="admin-content">
            <?= $content ?>
        </div>
        <footer class="admin-footer">
            <span>isnomCMS</span><span aria-hidden="true">•</span><span>Versión <?= e((string) ($config['app_version'] ?? '1.0')) ?></span><span aria-hidden="true">•</span><span>Josué 1:9</span>
        </footer>
    </main>
</body>
</html>
