<section class="health-overview" aria-labelledby="deploy-heading">
    <div>
        <p class="health-kicker">Checklist automático</p>
        <h1 id="deploy-heading">Listo para Hostinger</h1>
        <p><?= $report['ready'] ? 'El CMS no tiene bloqueos críticos detectados para producción.' : 'Hay elementos que requieren atención antes de subir con confianza.' ?></p>
    </div>
    <div class="health-score health-score--<?= e($report['status']) ?>">
        <strong><?= e((string) $report['counts']['ok']) ?>/<?= e((string) $report['total']) ?></strong>
        <span><?= $report['ready'] ? 'Listo para producción' : 'Requiere atención' ?></span>
    </div>
</section>

<div class="health-legend" aria-label="Resumen de deploy">
    <span class="health-status health-status--ok"><b aria-hidden="true">✓</b><?= e((string) $report['counts']['ok']) ?> correctas</span>
    <span class="health-status health-status--warning"><b aria-hidden="true">!</b><?= e((string) $report['counts']['warning']) ?> recomendadas</span>
    <span class="health-status health-status--error"><b aria-hidden="true">×</b><?= e((string) $report['counts']['error']) ?> requieren atención</span>
    <?php if ($report['counts']['unknown'] > 0): ?>
        <span class="health-status health-status--unknown"><b aria-hidden="true">?</b><?= e((string) $report['counts']['unknown']) ?> no verificables</span>
    <?php endif; ?>
</div>

<section class="health-panel deploy-panel">
    <h2>Comprobaciones</h2>
    <div class="health-list">
        <?php foreach ($report['checks'] as $check): ?>
            <?php $symbol = ['ok' => '✓', 'warning' => '!', 'error' => '×', 'unknown' => '?'][$check['status']] ?? '?'; ?>
            <article class="health-row health-row--<?= e($check['status']) ?>">
                <span class="health-symbol" aria-hidden="true"><?= e($symbol) ?></span>
                <div>
                    <h3><?= e($check['label']) ?></h3>
                    <p><?= e($check['message']) ?></p>
                    <?php if (!empty($check['detail'])): ?><small><?= e($check['detail']) ?></small><?php endif; ?>
                </div>
                <span class="health-state"><?= e(['ok' => 'Correcto', 'warning' => 'Recomendado', 'error' => 'Atención', 'unknown' => 'No verificable'][$check['status']] ?? 'No verificable') ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<p class="health-timestamp">Última comprobación: <?= e($report['generated_at']) ?></p>
