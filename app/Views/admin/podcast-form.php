<?php $podcast = $podcast ?? []; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="editor-form">
    <?= \App\Core\Csrf::field() ?>
    <section class="editor-main">
        <div class="settings-panel">
            <strong>Apariencia de /podcast</strong>
            <p>Estos campos controlan directamente el encabezado público del podcast.</p>
        </div>
        <label>Título principal<input required name="name" value="<?= e($podcast['name'] ?? '') ?>" data-slug-source><small>Se muestra como el título grande del hero y como nombre del podcast en el feed RSS.</small></label>
        <label>Slug<input required name="slug" value="<?= e($podcast['slug'] ?? '') ?>" data-slug-target></label>
        <label>Texto introductorio<textarea required name="short_description" rows="3"><?= e($podcast['short_description'] ?? '') ?></textarea><small>Aparece debajo del título en /podcast y se utiliza como resumen del feed RSS.</small></label>
        <label>Descripción completa<textarea required name="description" rows="10"><?= e($podcast['description'] ?? '') ?></textarea></label>
        <div class="two-columns"><label>Autor<input required name="author" value="<?= e($podcast['author'] ?? '') ?>"></label><label>Propietario<input required name="owner_name" value="<?= e($podcast['owner_name'] ?? '') ?>"></label></div>
        <label>Correo de contacto RSS<input required type="email" name="owner_email" value="<?= e($podcast['owner_email'] ?? '') ?>"><small>Este correo puede quedar visible públicamente en el feed RSS para verificación.</small></label>
        <div class="two-columns"><label>Idioma<input name="language" value="<?= e($podcast['language'] ?? 'es-MX') ?>"></label><label>Copyright<input name="copyright" value="<?= e($podcast['copyright'] ?? '') ?>"></label></div>
        <div class="two-columns"><label>Categoría principal<input required name="category_primary" value="<?= e($podcast['category_primary'] ?? '') ?>"></label><label>Categoría secundaria<input name="category_secondary" value="<?= e($podcast['category_secondary'] ?? '') ?>"></label></div>
        <label>Sitio web<input type="url" name="website_url" value="<?= e($podcast['website_url'] ?? '') ?>"></label>
        <label>Apple Podcasts<input type="url" name="apple_podcasts_url" value="<?= e($podcast['apple_podcasts_url'] ?? '') ?>"></label>
        <label>Spotify<input type="url" name="spotify_url" value="<?= e($podcast['spotify_url'] ?? '') ?>"></label>
    </section>
    <aside class="editor-side">
        <label>Imagen principal del hero<input name="cover_image" value="<?= e($podcast['cover_image'] ?? '') ?>" data-featured-image><small>Se muestra a la derecha del título y también funciona como portada del feed.</small></label>
        <label>Subir imagen nueva<input type="file" name="cover_upload" accept="image/jpeg,image/png,image/webp"></label>
        <img class="admin-cover-preview" data-media-preview src="<?= e($podcast['cover_image'] ?? '') ?>" alt="Previsualización del hero" <?= empty($podcast['cover_image']) ? 'hidden' : '' ?>>
        <?php if (!empty($mediaItems)): ?>
            <div class="media-picker">
                <strong>Elegir de Medios</strong>
                <?php foreach (array_slice($mediaItems, 0, 12) as $item): ?>
                    <div class="media-picker__item">
                        <img src="<?= e($item['url']) ?>" alt="">
                        <button type="button" data-select-media="<?= e($item['url']) ?>">Usar imagen</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <label>Episodios por página<input type="number" min="1" max="50" name="episodes_per_page" value="<?= e((string) ($podcast['episodes_per_page'] ?? 9)) ?>"></label>
        <label class="toggle-field"><input type="checkbox" name="explicit" value="1" <?= !empty($podcast['explicit']) ? 'checked' : '' ?>>Contenido explícito</label>
        <label class="toggle-field"><input type="checkbox" name="active" value="1" <?= !array_key_exists('active', $podcast) || !empty($podcast['active']) ? 'checked' : '' ?>>Podcast activo</label>
        <?php if (!empty($podcast['slug'])): ?>
            <div class="settings-panel"><strong>RSS Feed</strong><code><?= e($config['app_url'] . '/podcast/' . $podcast['slug'] . '/feed.xml') ?></code><button type="button" data-copy-url="<?= e($config['app_url'] . '/podcast/' . $podcast['slug'] . '/feed.xml') ?>">Copiar</button></div>
        <?php endif; ?>
        <button>Guardar podcast</button>
    </aside>
</form>
