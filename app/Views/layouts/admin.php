<?php
function admin_icon(string $name): string
{
    $icons = [
        'house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10.5 12 3l9 7.5"/><path d="M5 8.8V21h14V8.8"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6"/><path d="M8 13h8"/><path d="M8 17h8"/><path d="M8 9h2"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'folder' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.8a2 2 0 0 1-1.6-.8L9.4 3.8A2 2 0 0 0 7.8 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'tag' => '<path d="M12 2H2v10l9.3 9.3a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4Z"/><path d="M7 7h.01"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'user' => '<path d="M19 21a7 7 0 0 0-14 0"/><circle cx="12" cy="7" r="4"/>',
        'key-round' => '<path d="M2 18v3h3l8.7-8.7"/><circle cx="16" cy="8" r="6"/><path d="M19.5 4.5 18 6"/><path d="m15 9 3-3"/>',
        'settings' => '<path d="M12.2 2h-.4a2 2 0 0 0-2 1.7l-.2 1.2a7.8 7.8 0 0 0-1.6.9L6.9 5.3a2 2 0 0 0-2.6.8l-.2.4a2 2 0 0 0 .4 2.7l.9.8a8 8 0 0 0 0 1.9l-.9.8a2 2 0 0 0-.4 2.7l.2.4a2 2 0 0 0 2.6.8l1.1-.5a7.8 7.8 0 0 0 1.6.9l.2 1.2a2 2 0 0 0 2 1.7h.4a2 2 0 0 0 2-1.7l.2-1.2a7.8 7.8 0 0 0 1.6-.9l1.1.5a2 2 0 0 0 2.6-.8l.2-.4a2 2 0 0 0-.4-2.7l-.9-.8a8 8 0 0 0 0-1.9l.9-.8a2 2 0 0 0 .4-2.7l-.2-.4a2 2 0 0 0-2.6-.8l-1.1.5a7.8 7.8 0 0 0-1.6-.9l-.2-1.2a2 2 0 0 0-2-1.7Z"/><circle cx="12" cy="12" r="3"/>',
        'archive' => '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>',
        'rocket' => '<path d="M4.5 16.5c-1.2 1-1.7 2.6-1.5 4.5 1.9.2 3.5-.3 4.5-1.5"/><path d="M9 15 4 20"/><path d="M15 9l-6 6"/><path d="M14 3c3.5.2 5.8 1.2 7 3-1.8 4.8-4.8 8.1-9 10l-5-5c1.9-4.2 5.2-7.2 10-9Z"/><path d="M14 3c-.4 2 .1 3.8 1.5 5.2S18.7 10.1 21 9"/><circle cx="15" cy="9" r="1"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5-5L7 4l3 3 2.7-2.7a4 4 0 0 0 2 5L6.3 17.7a2 2 0 0 0 0 2.8l.2.2a2 2 0 0 0 2.8 0l8.4-8.4a4 4 0 0 0 5-2l-3 3-3-3 3-3a4 4 0 0 0-5-1Z"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    ];
    $paths = $icons[$name] ?? '';
    return '<span class="admin-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg></span>';
}
?>
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
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=30">
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
    <script defer src="<?= asset('js/admin.js') ?>?v=30"></script>
</head>
<body class="admin-shell">
    <aside class="admin-nav">
        <a class="admin-mark" href="/admin">jefté montenegro<br><span>computer engineer</span></a>
        <nav>
            <?php $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
            <a class="nav-link <?= $path === '/admin' ? 'active' : '' ?>" href="/admin"><?= admin_icon('house') ?>Dashboard</a>
            <a class="nav-link <?= str_starts_with($path, '/admin/posts') && $path !== '/admin/posts/create' ? 'active' : '' ?>" href="/admin/posts"><?= admin_icon('file-text') ?>Artículos</a>
            <a class="nav-link <?= $path === '/admin/posts/create' ? 'active' : '' ?>" href="/admin/posts/create"><?= admin_icon('plus') ?>Nuevo artículo</a>
            <a class="nav-link <?= $path === '/admin/categories' ? 'active' : '' ?>" href="/admin/categories"><?= admin_icon('folder') ?>Categorías</a>
            <a class="nav-link <?= $path === '/admin/tags' ? 'active' : '' ?>" href="/admin/tags"><?= admin_icon('tag') ?>Etiquetas</a>
            <a class="nav-link <?= $path === '/admin/media' ? 'active' : '' ?>" href="/admin/media"><?= admin_icon('image') ?>Medios</a>
            <a class="nav-link <?= $path === '/admin/users' ? 'active' : '' ?>" href="/admin/users"><?= admin_icon('user') ?>Usuarios</a>
            <a class="nav-link <?= $path === '/admin/passkeys' ? 'active' : '' ?>" href="/admin/passkeys"><?= admin_icon('key-round') ?>Passkeys</a>
            <a class="nav-link <?= $path === '/admin/health' ? 'active' : '' ?>" href="/admin/health"><?= admin_icon('activity') ?>Salud del sitio</a>
            <a class="nav-link <?= $path === '/admin/backups' ? 'active' : '' ?>" href="/admin/backups"><?= admin_icon('archive') ?>Backups</a>
            <a class="nav-link <?= $path === '/admin/deploy' ? 'active' : '' ?>" href="/admin/deploy"><?= admin_icon('rocket') ?>Deploy</a>
            <a class="nav-link <?= $path === '/admin/setup' ? 'active' : '' ?>" href="/admin/setup"><?= admin_icon('wrench') ?>Configuración</a>
            <a class="nav-link <?= $path === '/admin/settings' ? 'active' : '' ?>" href="/admin/settings"><?= admin_icon('settings') ?>Ajustes</a>
        </nav>
        <form class="admin-logout" method="post" action="/admin/logout"><?= \App\Core\Csrf::field() ?><button><?= admin_icon('log-out') ?>Salir</button></form>
    </aside>
    <main class="admin-main">
        <header class="admin-top"><span>/admin</span><strong><?= e($title ?? '') ?></strong></header>
        <?= $content ?>
    </main>
</body>
</html>
