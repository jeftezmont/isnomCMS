<?php require APP_PATH . '/Views/public/partials.php'; ?>
<?php
$episodePath = '/podcast/' . rawurlencode($episode['podcast_slug']) . '/' . rawurlencode($episode['slug']);
$shareUrl = $canonical ?? ($config['app_url'] . $episodePath);
$cover = $episode['image_url'] ?: $episode['podcast_cover_image'];
$seasonLabel = $episode['season_number'] ? 'TEMPORADA ' . (int) $episode['season_number'] : 'PODCAST';
$episodeLabel = $episode['episode_number'] ? 'EPISODIO ' . str_pad((string) $episode['episode_number'], 2, '0', STR_PAD_LEFT) : strtoupper((string) $episode['episode_type']);
?>
<main class="article-frame podcast-episode-page">
    <header class="page-head"><span>/podcast</span><a href="/" class="brand-lockup small">jefté montenegro<br>computer engineer</a></header>
    <div class="rule"></div>
    <nav class="article-menu" aria-label="Navegación principal"><?php foreach ($blogNav as $item): ?><a class="nav-link" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a><?php endforeach; ?></nav>

    <article class="podcast-episode" data-podcast-player data-waveform-seed="<?= e($episode['guid']) ?>" data-waveform="<?= e($episode['waveform_data'] ?? '') ?>">
        <nav class="episode-breadcrumb" aria-label="Migas de pan"><a href="/podcast">Podcast</a><span>/</span><a href="/podcast/<?= e($episode['podcast_slug']) ?>"><?= e($episode['podcast_name']) ?></a></nav>
        <header class="episode-intro">
            <img class="episode-cover" src="<?= e($cover) ?>" alt="Portada de <?= e($episode['title']) ?>">
            <div class="episode-heading">
                <p class="episode-kicker"><?= e($seasonLabel) ?> <span>•</span> <?= e($episodeLabel) ?></p>
                <h1><?= e($episode['title']) ?></h1>
                <p class="episode-summary"><?= e($episode['summary']) ?></p>
                <p class="episode-facts"><span>▣ <?= e(excerpt_date($episode['published_at'])) ?></span><?php if ($episode['duration']): ?><span>◷ <?= e($episode['duration']) ?></span><?php endif; ?><span>▣ <?= !empty($episode['explicit']) ? 'EXPLÍCITO' : 'NO EXPLÍCITO' ?></span></p>
            </div>
        </header>

        <section class="custom-audio-player" aria-label="Reproductor de <?= e($episode['title']) ?>">
            <audio preload="metadata" data-audio-engine><source src="<?= e($episode['audio']['url']) ?>" type="<?= e($episode['audio']['mime_type']) ?>"></audio>
            <div class="waveform" data-waveform><span class="waveform-bars" data-waveform-bars aria-hidden="true"></span><span class="waveform-playhead" data-waveform-playhead aria-hidden="true"></span><input class="waveform-seek" type="range" min="0" max="1000" step="1" value="0" data-waveform-seek aria-label="Posición del episodio" title="Haz clic o arrastra para buscar"></div>
            <div class="player-time"><time data-current-time>00:00</time><span class="player-progress-line" aria-hidden="true"><i data-progress-line></i></span><time data-duration><?= e($episode['duration'] ?: '00:00') ?></time></div>
            <div class="player-controls">
                <button type="button" class="player-icon" data-skip="-15" aria-label="Retroceder 15 segundos">↶<small>15</small></button>
                <?php if ($adjacent['previous']): ?><a class="player-icon" href="/podcast/<?= e($episode['podcast_slug']) ?>/<?= e($adjacent['previous']['slug']) ?>" aria-label="Episodio anterior">|◀</a><?php else: ?><span class="player-icon is-disabled" aria-hidden="true">|◀</span><?php endif; ?>
                <button type="button" class="player-play" data-play aria-label="Reproducir"><span data-play-icon>▶</span></button>
                <?php if ($adjacent['next']): ?><a class="player-icon" href="/podcast/<?= e($episode['podcast_slug']) ?>/<?= e($adjacent['next']['slug']) ?>" aria-label="Episodio siguiente">▶|</a><?php else: ?><span class="player-icon is-disabled" aria-hidden="true">▶|</span><?php endif; ?>
                <button type="button" class="player-icon" data-skip="15" aria-label="Avanzar 15 segundos">↷<small>15</small></button>
                <label class="player-rate"><span class="sr-only">Velocidad</span><select data-rate aria-label="Velocidad de reproducción"><option value="0.75">0.75x</option><option value="1" selected>1x</option><option value="1.25">1.25x</option><option value="1.5">1.5x</option><option value="1.75">1.75x</option><option value="2">2x</option></select></label>
                <label class="player-volume"><span aria-hidden="true">◖))</span><span class="sr-only">Volumen</span><input type="range" min="0" max="1" step="0.05" value="1" data-volume aria-label="Volumen"></label>
            </div>
        </section>

        <section class="episode-notes"><h2>Notas del episodio</h2><div class="article-content"><?= markdownish($episode['show_notes']) ?></div></section>
        <section class="episode-share"><h2>Compartir</h2><div><button type="button" data-copy-share="<?= e($shareUrl) ?>" aria-label="Copiar enlace">↗</button><a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($episode['title']) ?>" aria-label="Compartir en X">X</a><a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" aria-label="Compartir en Facebook">f</a><a target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($episode['title'] . ' ' . $shareUrl) ?>" aria-label="Compartir en WhatsApp">◉</a></div><p data-share-status role="status" aria-live="polite"></p></section>
    </article>

    <nav class="episode-adjacent" aria-label="Navegación entre episodios">
        <?php if ($adjacent['previous']): ?><a href="/podcast/<?= e($episode['podcast_slug']) ?>/<?= e($adjacent['previous']['slug']) ?>"><span>← Episodio anterior</span><strong><?= e($adjacent['previous']['title']) ?></strong><small>T<?= (int)($adjacent['previous']['season_number'] ?: 1) ?> · E<?= str_pad((string)($adjacent['previous']['episode_number'] ?: 0), 2, '0', STR_PAD_LEFT) ?></small></a><?php endif; ?>
        <?php if ($adjacent['next']): ?><a href="/podcast/<?= e($episode['podcast_slug']) ?>/<?= e($adjacent['next']['slug']) ?>"><span>Siguiente episodio →</span><strong><?= e($adjacent['next']['title']) ?></strong><small>T<?= (int)($adjacent['next']['season_number'] ?: 1) ?> · E<?= str_pad((string)($adjacent['next']['episode_number'] ?: 0), 2, '0', STR_PAD_LEFT) ?></small></a><?php endif; ?>
    </nav>

    <?php if ($recentEpisodes): ?><section class="episode-recent"><h2>Todos los episodios</h2><div><?php foreach ($recentEpisodes as $item): ?><a href="/podcast/<?= e($episode['podcast_slug']) ?>/<?= e($item['slug']) ?>"><img src="<?= e($item['image_url'] ?: $episode['podcast_cover_image']) ?>" alt=""><span class="episode-row-number"><?= str_pad((string)($item['episode_number'] ?: 0), 2, '0', STR_PAD_LEFT) ?></span><span><strong><?= e($item['title']) ?></strong><small><?= e(excerpt_date($item['published_at'])) ?><?= $item['duration'] ? ' · ' . e($item['duration']) : '' ?></small></span><b aria-hidden="true">▷</b></a><?php endforeach; ?></div></section><?php endif; ?>
    <?php site_footer($config); ?>
</main>
