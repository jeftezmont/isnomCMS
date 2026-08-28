<?php $isEdit = !empty($post); ?>
<form class="editor-form" method="post" enctype="multipart/form-data" data-editor-form data-preview-endpoint="/admin/posts/preview">
    <?= \App\Core\Csrf::field() ?>
    <section class="editor-main">
        <label>Título <input data-slug-source name="title" value="<?= e($post['title'] ?? '') ?>" required></label>
        <label>Slug <input data-slug-target name="slug" value="<?= e($post['slug'] ?? '') ?>" required></label>
        <label>Extracto <textarea name="excerpt" rows="3" required><?= e($post['excerpt'] ?? '') ?></textarea></label>
        <div class="markdown-editor">
            <div class="editor-toolbar" role="toolbar" aria-label="Herramientas de Markdown">
                <button type="button" data-md-wrap="**|**" title="Negrita"><strong>B</strong></button>
                <button type="button" data-md-wrap="*|*" title="Cursiva"><em>I</em></button>
                <button type="button" data-md-prefix="## " title="Título">H2</button>
                <button type="button" data-md-prefix="> " title="Cita">"</button>
                <button type="button" data-md-prefix="- " title="Lista">-</button>
                <button type="button" data-md-wrap="`|`" title="Código">`</button>
                <button type="button" data-md-link title="Enlace">Link</button>
                <button type="button" data-md-image title="Imagen">Img</button>
                <button type="button" data-md-embed="spotify" title="Spotify">Spotify</button>
                <button type="button" data-md-embed="applemusic" title="Apple Music">Apple</button>
                <button type="button" data-md-preview title="Actualizar preview">Preview</button>
            </div>
            <label>Contenido <textarea class="content-editor" name="content" rows="18" required data-content-editor><?= e($post['content'] ?? '') ?></textarea></label>
        </div>
        <section class="editor-preview" data-editor-preview>
            <div class="editor-preview__head">
                <h2>Preview</h2>
                <span data-preview-status>Sin actualizar</span>
            </div>
            <article class="article-content editor-preview__body" data-preview-body></article>
        </section>
        <div class="seo-box">
            <h2>SEO</h2>
            <label>SEO title <input name="seo_title" value="<?= e($post['seo_title'] ?? '') ?>"></label>
            <label>SEO description <textarea name="seo_description" rows="2"><?= e($post['seo_description'] ?? '') ?></textarea></label>
            <label>Open Graph image <input name="og_image" value="<?= e($post['og_image'] ?? '') ?>"></label>
        </div>
    </section>
    <aside class="editor-side">
        <?php if ($isEdit && !empty($post['preview_token'])): ?>
            <a class="button ghost-button" href="/blog/<?= e($post['slug']) ?>?preview=<?= e($post['preview_token']) ?>" target="_blank" rel="noopener">Previsualizar</a>
        <?php endif; ?>
        <label>Estado
            <select name="status">
                <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Borrador</option>
                <option value="private" <?= ($post['status'] ?? '') === 'private' ? 'selected' : '' ?>>Privado</option>
                <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publicado</option>
            </select>
        </label>
        <label>Fecha de publicación <input type="datetime-local" name="published_at" value="<?= e(isset($post['published_at']) ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : date('Y-m-d\TH:i')) ?>"></label>
        <label>Categoría
            <select name="category_id"><option value="">Sin categoría</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" <?= (string)($post['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select>
        </label>
        <label>Etiquetas <input name="tags" value="<?= e($tagNames) ?>" placeholder="arte, diseño, música"></label>
        <label>Imagen destacada URL <input data-featured-image name="featured_image" value="<?= e($post['featured_image'] ?? '') ?>"></label>
        <label>Subir imagen <input type="file" name="featured_upload" accept="image/png,image/jpeg,image/webp,image/gif"></label>
        <?php if (!empty($mediaItems)): ?>
            <div class="media-picker">
                <h2>Medios</h2>
                <?php foreach (array_slice($mediaItems, 0, 12) as $item): ?>
                    <div class="media-picker__item">
                        <img src="<?= e($item['url']) ?>" alt="">
                        <div>
                            <button type="button" data-select-media="<?= e($item['url']) ?>">Portada</button>
                            <button type="button" data-insert-media="<?= e($item['url']) ?>" data-media-name="<?= e($item['original_name'] ?? 'Imagen') ?>">Insertar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button><?= $isEdit ? 'Guardar cambios' : 'Crear artículo' ?></button>
    </aside>
</form>
