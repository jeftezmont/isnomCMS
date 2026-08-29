<?php $episode = $episode ?? []; ?>
<?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="editor-form" data-podcast-episode-form>
    <?= \App\Core\Csrf::field() ?>
    <section class="editor-main">
        <label>Podcast<select required name="podcast_id"><option value="">Seleccionar</option><?php foreach ($podcasts as $podcast): ?><option value="<?= (int) $podcast['id'] ?>" <?= (string) ($episode['podcast_id'] ?? '') === (string) $podcast['id'] ? 'selected' : '' ?>><?= e($podcast['name']) ?></option><?php endforeach; ?></select></label>
        <label>Título<input required name="title" value="<?= e($episode['title'] ?? '') ?>" data-slug-source></label>
        <label>Slug<input required name="slug" value="<?= e($episode['slug'] ?? '') ?>" data-slug-target></label>
        <label>Resumen<textarea required name="summary" rows="4"><?= e($episode['summary'] ?? '') ?></textarea></label>
        <label>Show notes<textarea name="show_notes" rows="14" class="content-editor"><?= e($episode['show_notes'] ?? '') ?></textarea></label>
        <fieldset class="settings-panel"><legend>Fuente de audio</legend>
            <label><select name="audio_source" data-audio-source><option value="local" <?= ($episode['audio_source'] ?? 'local') === 'local' ? 'selected' : '' ?>>Archivo local</option><option value="dropbox" <?= ($episode['audio_source'] ?? '') === 'dropbox' ? 'selected' : '' ?>>Dropbox</option></select></label>
            <div data-audio-local><label>Archivo MP3, AAC o M4A<input type="file" name="audio_upload" accept="audio/mpeg,audio/mp4,audio/aac,.mp3,.m4a,.aac"></label><?php if (($episode['audio_source'] ?? '') === 'local'): ?><small>Actual: <?= e($episode['audio_url'] ?? '') ?></small><?php endif; ?></div>
            <div data-audio-dropbox><label>URL compartida de Dropbox<input type="url" name="audio_original_url" value="<?= e($episode['audio_original_url'] ?? '') ?>" data-dropbox-url></label><button type="button" class="ghost-button" data-validate-dropbox>Validar URL</button><p data-dropbox-status></p></div>
        </fieldset>
        <div class="two-columns"><label>Duración (HH:MM:SS)<input name="duration" pattern="(?:\d+:)?[0-5]?\d:[0-5]\d|\d+" value="<?= e($episode['duration'] ?? '') ?>"></label><label>Autor opcional<input name="author" value="<?= e($episode['author'] ?? '') ?>"></label></div>
        <div class="two-columns"><label>Número de episodio<input type="number" min="1" name="episode_number" value="<?= e((string) ($episode['episode_number'] ?? '')) ?>"></label><label>Temporada<input type="number" min="1" name="season_number" value="<?= e((string) ($episode['season_number'] ?? '')) ?>"></label></div>
    </section>
    <aside class="editor-side">
        <label>Tipo<select name="episode_type"><option value="full">Completo</option><option value="trailer" <?= ($episode['episode_type'] ?? '') === 'trailer' ? 'selected' : '' ?>>Tráiler</option><option value="bonus" <?= ($episode['episode_type'] ?? '') === 'bonus' ? 'selected' : '' ?>>Bonus</option></select></label>
        <label>Estado<select name="status" <?= empty($canPublish) ? 'disabled' : '' ?>><option value="draft">Borrador</option><option value="scheduled" <?= ($episode['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Programado</option><option value="published" <?= ($episode['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publicado</option></select><?php if (empty($canPublish)): ?><input type="hidden" name="status" value="draft"><?php endif; ?></label>
        <label>Publicación<input type="datetime-local" name="published_at" value="<?= !empty($episode['published_at']) ? e(date('Y-m-d\TH:i', strtotime($episode['published_at']))) : '' ?>"></label>
        <label class="toggle-field"><input type="checkbox" name="explicit" value="1" <?= !empty($episode['explicit']) ? 'checked' : '' ?>>Explícito</label>
        <label>Imagen URL<input name="image_url" value="<?= e($episode['image_url'] ?? '') ?>" data-featured-image></label>
        <label>Subir imagen<input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp"></label>
        <?php if (!empty($episode['guid'])): ?><label>GUID permanente<input readonly value="<?= e($episode['guid']) ?>"></label><?php endif; ?>
        <button>Guardar episodio</button>
    </aside>
</form>
