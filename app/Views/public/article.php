<?php require APP_PATH . '/Views/public/partials.php'; ?>
<main class="article-frame article-template">
    <header class="page-head article-head">
        <span>/blog</span>
        <a href="/" class="brand-lockup small">jefté montenegro<br>computer engineer</a>
    </header>
    <div class="rule"></div>
    <nav class="article-menu" aria-label="Navegación del artículo">
        <?php foreach ($blogNav as $item): ?>
            <?php $itemQuery = []; parse_str(parse_url($item['url'], PHP_URL_QUERY) ?: '', $itemQuery); ?>
            <a class="nav-link" href="<?= e($item['url']) ?>" <?= (($itemQuery['category'] ?? '') === ($post['category_slug'] ?? '') && !empty($itemQuery['category'])) ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <article class="article">
        <a class="back-link" href="/blog">← Volver al blog</a>
        <p class="meta"><?= e($post['category_name'] ?: 'Sin categoría') ?><span><?= e(excerpt_date($post['published_at'])) ?></span></p>
        <h1><?= e($post['title']) ?></h1>
        <div class="article-info">
            <?php foreach ($tags as $tag): ?><span><?= e($tag['name']) ?></span><?php endforeach; ?>
            <small>por <?= e($post['author_name'] ?: 'jefté montenegro') ?></small>
            <small>○ 0 Comments</small>
        </div>
        <img class="article-image" src="<?= e($post['featured_image'] ?: asset('img/hero-portrait.png')) ?>" alt="">
        <p class="lede"><?= e($post['excerpt']) ?></p>
        <div class="article-content"><?= markdownish($post['content']) ?></div>
    </article>
    <nav class="article-nav">
        <?php if ($adjacent['prev']): ?><a href="/blog/<?= e($adjacent['prev']['slug']) ?>"><span>← Anterior</span><?= e($adjacent['prev']['title']) ?></a><?php endif; ?>
        <?php if ($adjacent['next']): ?><a href="/blog/<?= e($adjacent['next']['slug']) ?>"><span>Siguiente →</span><?= e($adjacent['next']['title']) ?></a><?php endif; ?>
    </nav>
    <?php if ($related): ?>
    <section class="related"><h2>Relacionados</h2><?php foreach ($related as $item): ?><a href="/blog/<?= e($item['slug']) ?>"><?= e($item['title']) ?></a><?php endforeach; ?></section>
    <?php endif; ?>
    <?php site_footer($config); ?>
</main>
