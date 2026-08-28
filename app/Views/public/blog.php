<?php require APP_PATH . '/Views/public/partials.php'; ?>
<main class="page-frame blog-page">
    <header class="page-head">
        <span>/blog</span>
        <a href="/" class="brand-lockup small">jefté montenegro<br>computer engineer</a>
    </header>
    <div class="rule"></div>
    <nav class="blog-primary-nav" aria-label="Navegación principal del blog">
        <?php foreach ($blogNav as $item): ?>
            <?php $path = parse_url($item['url'], PHP_URL_PATH) ?: ''; $itemQuery = []; parse_str(parse_url($item['url'], PHP_URL_QUERY) ?: '', $itemQuery); ?>
            <?php $isActive = ($path === '/blog' && empty($itemQuery['category']) && $activeCategory === '') || (($itemQuery['category'] ?? '') === $activeCategory && $activeCategory !== ''); ?>
            <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="blog-tools" method="get" action="/blog">
        <div></div>
        <label><input name="q" value="<?= e($query) ?>" placeholder="Buscar..."><button>⌕</button></label>
    </form>
    <section class="post-list">
        <?php foreach ($posts as $post): ?>
            <article class="post-row">
                <a href="/blog/<?= e($post['slug']) ?>" class="thumb"><img loading="lazy" src="<?= e($post['featured_image'] ?: asset('img/hero-portrait.png')) ?>" alt=""></a>
                <div>
                    <p class="meta"><?= e($post['category_name'] ?: 'Sin categoría') ?> · <?= e(excerpt_date($post['published_at'])) ?></p>
                    <h2><a href="/blog/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                    <p><?= e($post['excerpt']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$posts): ?><p class="empty-state">No hay artículos que coincidan con esta búsqueda.</p><?php endif; ?>
    </section>
    <?php site_footer($config); ?>
</main>
