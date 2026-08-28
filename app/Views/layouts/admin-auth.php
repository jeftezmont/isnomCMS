<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Login') ?> / jeftezmont</title>
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
    <?php if (!empty($turnstileSiteKey)): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <script defer src="<?= asset('js/admin.js') ?>?v=30"></script>
</head>
<body class="auth-page">
    <?= $content ?>
</body>
</html>
