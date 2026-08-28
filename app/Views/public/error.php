<?php require APP_PATH . '/Views/public/partials.php'; ?>
<main class="page-frame blog-page error-page">
    <header class="page-head">
        <span>/blog</span>
        <a href="/" class="brand-lockup small">jefté montenegro<br>computer engineer</a>
    </header>
    <div class="rule"></div>
    <nav class="blog-primary-nav" aria-label="Navegación principal del blog">
        <?php foreach ($blogNav as $item): ?>
            <a class="nav-link" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <section class="error-content">
        <?php if ($eyebrow): ?>
            <p class="error-eyebrow"><?= e($eyebrow) ?></p>
        <?php else: ?>
            <p class="error-code"><?= e((string) $code) ?></p>
        <?php endif; ?>
        <h1><?= e($heading) ?></h1>
        <div class="error-mark"></div>
        <p><?= e($body) ?></p>
        <p><?= e($detail) ?></p>
        <?php if (!empty($errorId)): ?><p class="error-id">Error ID: <?= e($errorId) ?></p><?php endif; ?>
        <a class="error-link" href="/">← Volver al inicio</a>
    </section>
    <?php site_footer($config); ?>
</main>
