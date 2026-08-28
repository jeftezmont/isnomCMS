<?php
$defaults = [
    'accent' => '#ff4f9a',
    'accent_soft' => '#ffe5f0',
    'coming_soon_mode' => '0',
    'home_bio_1' => 'Ingeniero en Sistemas, desarrollador web y creador digital con base en la Ciudad de México. Trabajo de manera independiente diseñando y construyendo proyectos donde convergen **tecnología, diseño y creatividad**. Me interesa crear experiencias digitales que no solo funcionen, sino que tengan identidad, intención y una razón de ser.',
    'home_bio_2' => 'Mi trabajo explora el encuentro entre **código y diseño, ingeniería y creatividad, música e imagen, tecnología y significado**. Fuera de lo digital, encuentro en la fotografía, el podcasting, la lectura y la escritura creativa otras formas de explorar y comunicar ideas. También escribo y reflexiono sobre tecnología, música, cultura, diseño y teología.',
    'home_bio_3' => 'Creo en las ideas que nacen de la intuición, pero se sostienen con estructura, estrategia y atención al detalle. Ya sea una interfaz, una identidad, una fotografía, un texto o una experiencia completa, busco convertir ideas en algo que **funcione, comunique y signifique**.',
    'discord_url' => 'https://discord.gg/nCRrSAwVph',
    'instagram_url' => 'https://instagram.com/jeftezmont',
    'soundcloud_url' => 'https://soundcloud.com/jeftezmont',
    'threads_url' => 'https://www.threads.com/@jeftezmont',
];
$values = array_merge($defaults, $settings);
$socialLinks = json_decode($values['social_links'] ?? '', true);
if (!is_array($socialLinks)) {
    $socialLinks = [
        ['label' => 'Instagram', 'url' => $values['instagram_url']],
        ['label' => 'SoundCloud', 'url' => $values['soundcloud_url']],
        ['label' => 'Threads', 'url' => $values['threads_url']],
        ['label' => 'Discord', 'url' => $values['discord_url']],
        ['label' => 'Blog', 'url' => '/blog'],
    ];
}
if ($socialLinks === []) {
    $socialLinks = [['label' => '', 'url' => '']];
}
$nav = $blogNav ?: [
    ['label' => 'Inicio', 'url' => '/blog'],
    ['label' => 'Tecnología', 'url' => '/blog?category=tecnologia'],
    ['label' => 'Teología', 'url' => '/blog?category=teologia'],
    ['label' => 'Música', 'url' => '/blog?category=musica'],
];
?>

<?php if (!empty($error)): ?>
    <p class="notice"><?= e($error) ?></p>
<?php endif; ?>

<form class="settings-form" method="post">
    <?= \App\Core\Csrf::field() ?>
    <section class="settings-panel">
        <h2>Acento global</h2>
        <div class="color-fields">
            <label>Acento <input data-color-source type="color" name="accent" value="<?= e($values['accent']) ?>"><input data-color-target name="accent" value="<?= e($values['accent']) ?>" pattern="#[0-9a-fA-F]{6}"></label>
            <label>Acento suave <input data-color-source type="color" name="accent_soft" value="<?= e($values['accent_soft']) ?>"><input data-color-target name="accent_soft" value="<?= e($values['accent_soft']) ?>" pattern="#[0-9a-fA-F]{6}"></label>
        </div>
        <label class="toggle-field"><input type="checkbox" name="coming_soon_mode" value="1" <?= $values['coming_soon_mode'] === '1' ? 'checked' : '' ?>> <span>Modo coming soon</span></label>
    </section>

    <section class="settings-panel">
        <h2>Textos del inicio</h2>
        <label>Párrafo 1 <textarea name="home_bio_1" rows="4"><?= e($values['home_bio_1']) ?></textarea></label>
        <label>Párrafo 2 <textarea name="home_bio_2" rows="4"><?= e($values['home_bio_2']) ?></textarea></label>
        <label>Párrafo 3 <textarea name="home_bio_3" rows="4"><?= e($values['home_bio_3']) ?></textarea></label>
    </section>

    <section class="settings-panel">
        <h2>Redes</h2>
        <div class="link-editor" data-list-editor>
            <div class="link-editor__head"><span>Etiqueta</span><span>URL</span></div>
            <div data-list-body>
                <?php foreach ($socialLinks as $link): ?>
                    <div class="link-row" data-list-row>
                        <input name="social_label[]" value="<?= e($link['label'] ?? '') ?>" placeholder="Instagram">
                        <input name="social_url[]" value="<?= e($link['url'] ?? '') ?>" placeholder="https://...">
                        <button type="button" data-remove-row>Quitar</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="secondary-button" type="button" data-add-row data-label-name="social_label[]" data-url-name="social_url[]" data-label-placeholder="Nueva red" data-url-placeholder="https://...">+ Añadir red</button>
        </div>
    </section>

    <section class="settings-panel">
        <h2>Menú de blog y artículos</h2>
        <div class="link-editor" data-list-editor>
            <div class="link-editor__head"><span>Etiqueta</span><span>URL</span></div>
            <div data-list-body>
                <?php foreach ($nav as $link): ?>
                    <div class="link-row" data-list-row>
                        <input name="nav_label[]" value="<?= e($link['label'] ?? '') ?>" placeholder="Etiqueta">
                        <input name="nav_url[]" value="<?= e($link['url'] ?? '') ?>" placeholder="/blog?category=tecnologia">
                        <button type="button" data-remove-row>Quitar</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="secondary-button" type="button" data-add-row data-label-name="nav_label[]" data-url-name="nav_url[]" data-label-placeholder="Nueva sección" data-url-placeholder="/blog?category=...">+ Añadir enlace</button>
        </div>
    </section>

    <button>Guardar ajustes</button>
</form>
