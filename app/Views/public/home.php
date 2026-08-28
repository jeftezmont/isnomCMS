<?php require APP_PATH . '/Views/public/partials.php'; ?>
<main class="home-grid">
    <section class="hero-panel">
        <div class="halftone-figure" aria-label="Imagen editorial en semitono rosa"></div>
    </section>
    <section class="bio-panel">
        <header class="brand-lockup">
            <a href="/">jefté montenegro<br>computer engineer</a>
        </header>
        <div class="rule"></div>
        <article class="bio-copy">
            <?php foreach ($bio as $paragraph): ?><p><?= inline_emphasis($paragraph) ?></p><?php endforeach; ?>
            <h2>PROYECTOS</h2>
            <p class="project-list">
                <?php foreach ($projects as $index => $project): ?>
                    <a href="<?= e($project[1]) ?>" target="_blank" rel="noopener"><?= e($project[0]) ?></a><?= $index < count($projects) - 1 ? ' / ' : '' ?>
                <?php endforeach; ?>
            </p>
            <p><strong>Para proyectos, colaboraciones o simplemente conectar:</strong> redes sociales / <a href="<?= e($discordUrl) ?>" target="_blank" rel="noopener">Discord</a> — <strong>jeftezmont</strong></p>
        </article>
        <?php site_footer($config); ?>
    </section>
</main>
