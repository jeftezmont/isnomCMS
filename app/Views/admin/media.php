<form class="media-upload" method="post" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" required>
    <button>Subir imagen</button>
</form>
<section class="media-grid">
    <?php foreach ($items as $item): ?>
        <article>
            <a class="media-thumb" href="<?= e($item['url']) ?>" target="_blank" rel="noopener">
                <img src="<?= e($item['url']) ?>" alt="<?= e($item['original_name'] ?? $item['filename']) ?>">
            </a>
            <div class="media-meta">
                <strong><?= e($item['original_name'] ?? $item['filename']) ?></strong>
                <span>ID <?= e((string) $item['id']) ?> · <?= e(date('d M Y', strtotime($item['created_at'] ?? 'now'))) ?></span>
            </div>
            <input readonly value="<?= e($item['url']) ?>" onclick="this.select();navigator.clipboard && navigator.clipboard.writeText(this.value)">
            <div class="media-actions">
                <a class="secondary-button" href="<?= e($item['url']) ?>" target="_blank" rel="noopener">Abrir</a>
                <button type="button" data-copy-url="<?= e($item['url']) ?>">Copiar URL</button>
                <form method="post" action="/admin/media/<?= e((string) $item['id']) ?>/delete"><?= \App\Core\Csrf::field() ?><button>Eliminar</button></form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <p class="empty-state">Todavía no hay medios subidos.</p>
    <?php endif; ?>
</section>
