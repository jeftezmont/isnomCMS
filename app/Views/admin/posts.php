<form class="filters" method="get">
    <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Buscar título">
    <select name="status">
        <option value="">Estado</option>
        <option value="published" <?= ($_GET['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publicado</option>
        <option value="private" <?= ($_GET['status'] ?? '') === 'private' ? 'selected' : '' ?>>Privado</option>
        <option value="draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador</option>
    </select>
    <select name="category_id">
        <option value="">Categoría</option>
        <?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" <?= (string)($_GET['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
    </select>
    <button>Filtrar</button>
    <a class="button ghost-button" href="/admin/export/posts">Exportar</a>
    <a class="button" href="/admin/posts/create">+ Nuevo artículo</a>
</form>
<table class="admin-table">
    <thead><tr><th>Título</th><th>Categoría</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($posts as $post): ?>
        <?php
            $publicUrl = $config['app_url'] . '/blog/' . $post['slug'];
            $previewUrl = $publicUrl . (!empty($post['preview_token']) ? '?preview=' . $post['preview_token'] : '');
            $shareUrl = $post['status'] === 'published' ? $publicUrl : $previewUrl;
        ?>
        <tr>
            <td><?= e($post['title']) ?></td>
            <td><?= e($post['category_name'] ?: '—') ?></td>
            <td><?= e($post['status']) ?></td>
            <td><?= e(excerpt_date($post['published_at'] ?: $post['created_at'])) ?></td>
            <td class="row-actions">
                <a href="/admin/posts/<?= e((string) $post['id']) ?>/edit">Editar</a>
                <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener">Ver</a>
                <button type="button" data-copy-url="<?= e($shareUrl) ?>">Copiar URL</button>
                <form method="post" action="/admin/posts/<?= e((string) $post['id']) ?>/delete" onsubmit="return confirm('Eliminar artículo?')"><?= \App\Core\Csrf::field() ?><button>Eliminar</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
