<section class="stat-grid">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat"><strong><?= e((string) $value) ?></strong><span><?= e($label) ?></span></div>
    <?php endforeach; ?>
</section>

<section class="dashboard-health dashboard-health--<?= e($healthReport['status']) ?>" aria-labelledby="dashboard-health-heading">
    <div class="dashboard-health__head">
        <div>
            <p class="health-kicker">Diagnóstico automático</p>
            <h2 id="dashboard-health-heading">Salud del sitio</h2>
        </div>
        <strong><?= e((string) $healthReport['counts']['ok']) ?>/<?= e((string) $healthReport['total']) ?></strong>
    </div>
    <div class="dashboard-health__checks">
        <?php foreach ($healthHighlights as $check): ?>
            <?php $symbol = ['ok' => '✓', 'warning' => '!', 'error' => '×', 'unknown' => '?'][$check['status']] ?? '?'; ?>
            <span class="health-status health-status--<?= e($check['status']) ?>"><b aria-hidden="true"><?= e($symbol) ?></b><?= e($check['label']) ?></span>
        <?php endforeach; ?>
    </div>
    <a href="/admin/health">Ver diagnóstico completo →</a>
</section>

<section class="dashboard-health dashboard-health--<?= e($deployReport['status']) ?>" aria-labelledby="dashboard-deploy-heading">
    <div class="dashboard-health__head">
        <div>
            <p class="health-kicker">Deploy</p>
            <h2 id="dashboard-deploy-heading">Listo para Hostinger</h2>
        </div>
        <strong><?= e((string) $deployReport['counts']['ok']) ?>/<?= e((string) $deployReport['total']) ?></strong>
    </div>
    <div class="dashboard-health__checks">
        <span class="health-status health-status--<?= $deployReport['ready'] ? 'ok' : 'error' ?>"><b aria-hidden="true"><?= $deployReport['ready'] ? '✓' : '×' ?></b><?= $deployReport['ready'] ? 'Sin bloqueos críticos' : 'Requiere atención' ?></span>
        <span class="health-status health-status--warning"><b aria-hidden="true">!</b><?= e((string) $deployReport['counts']['warning']) ?> recomendaciones</span>
    </div>
    <a href="/admin/deploy">Revisar checklist →</a>
</section>

<?php if (!empty($contentError)): ?><p class="notice" role="alert"><?= e($contentError) ?></p><?php endif; ?>
<div class="admin-actions"><h1>Artículos recientes</h1><a class="button" href="/admin/posts/create">+ Nuevo artículo</a></div>
<table class="admin-table">
    <thead><tr><th>Título</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $post): ?>
        <tr>
            <td><?= e($post['title']) ?></td>
            <td><?= e($post['status']) ?></td>
            <td><?= e(excerpt_date($post['published_at'] ?: $post['created_at'])) ?></td>
            <td><a href="/admin/posts/<?= e((string) $post['id']) ?>/edit">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($recent === []): ?>
        <tr><td colspan="4" class="admin-empty">No hay artículos disponibles.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
