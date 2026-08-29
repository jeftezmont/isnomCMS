<?php $pageTitle = isset($title) ? $title . ' / ' . $config['site']['name'] : $config['site']['name'] . ' / ' . $config['site']['role']; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description ?? $config['site']['description']) ?>">
    <link rel="canonical" href="<?= e($canonical ?? ($config['app_url'] . ($_SERVER['REQUEST_URI'] ?? '/'))) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description ?? $config['site']['description']) ?>">
    <meta property="og:type" content="<?= e($ogType ?? (!empty($schemaArticle) ? 'article' : 'website')) ?>">
    <meta property="og:image" content="<?= e($socialImage ?? ($config['app_url'] . ($post['og_image'] ?? $post['featured_image'] ?? asset('img/hero-portrait.png')))) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if (!empty($podcastFeedUrl)): ?><link rel="alternate" type="application/rss+xml" title="Podcast RSS" href="<?= e($podcastFeedUrl) ?>"><?php endif; ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('favicon/favicon-16x16.png') ?>">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('favicon/apple-touch-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=32">
    <script defer src="<?= asset('js/site.js') ?>?v=4"></script>
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
    <?php if (!empty($schemaArticle) && !empty($post)): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $post['excerpt'],
        'image' => $config['app_url'] . $post['featured_image'],
        'datePublished' => $post['published_at'],
        'dateModified' => $post['updated_at'],
        'author' => ['@type' => 'Person', 'name' => $post['author_name'] ?: $config['site']['name']],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <?php if (!empty($schemaJson)): ?><script type="application/ld+json"><?= json_encode($schemaJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?>
</head>
<body>
    <?= $content ?>
    <button class="back-to-top" type="button" aria-label="Volver arriba" data-back-to-top hidden>↑</button>
</body>
</html>
