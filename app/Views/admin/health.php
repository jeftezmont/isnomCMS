<section class="health-overview" aria-labelledby="health-heading">
    <div>
        <p class="health-kicker">Diagnóstico automático</p>
        <h1 id="health-heading">Salud del sitio</h1>
        <p>Comprobación real del entorno, la base de datos y los servicios que utiliza isnomCMS.</p>
    </div>
    <div class="health-score health-score--<?= e($report['status']) ?>">
        <strong><?= e((string) $report['counts']['ok']) ?>/<?= e((string) $report['total']) ?></strong>
        <span>comprobaciones correctas</span>
    </div>
</section>

<div class="health-legend" aria-label="Resumen de estados">
    <span class="health-status health-status--ok"><b aria-hidden="true">✓</b><?= e((string) $report['counts']['ok']) ?> correctas</span>
    <span class="health-status health-status--warning"><b aria-hidden="true">!</b><?= e((string) $report['counts']['warning']) ?> advertencias</span>
    <span class="health-status health-status--error"><b aria-hidden="true">×</b><?= e((string) $report['counts']['error']) ?> errores</span>
    <?php if ($report['counts']['unknown'] > 0): ?>
        <span class="health-status health-status--unknown"><b aria-hidden="true">?</b><?= e((string) $report['counts']['unknown']) ?> no verificables</span>
    <?php endif; ?>
</div>

<div class="health-sections">
    <?php foreach ($report['sections'] as $section): ?>
        <section class="health-panel">
            <h2><?= e($section['title']) ?></h2>
            <div class="health-list">
                <?php foreach ($section['checks'] as $check): ?>
                    <?php $symbol = ['ok' => '✓', 'warning' => '!', 'error' => '×', 'unknown' => '?'][$check['status']] ?? '?'; ?>
                    <article class="health-row health-row--<?= e($check['status']) ?>">
                        <span class="health-symbol" aria-hidden="true"><?= e($symbol) ?></span>
                        <div>
                            <h3><?= e($check['label']) ?></h3>
                            <p><?= e($check['message']) ?></p>
                            <?php if (!empty($check['detail'])): ?><small><?= e($check['detail']) ?></small><?php endif; ?>
                        </div>
                        <span class="health-state"><?= e(['ok' => 'Correcto', 'warning' => 'Advertencia', 'error' => 'Error', 'unknown' => 'No verificable'][$check['status']] ?? 'No verificable') ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<p class="health-timestamp">Última comprobación: <?= e($report['generated_at']) ?></p>
