<div class="admin-actions"><a class="primary-button" href="/admin/podcasts/create">+ Nuevo podcast</a><a class="secondary-button" href="/admin/podcast-episodes">Ver episodios</a></div>
<table class="admin-table">
    <thead><tr><th>Podcast</th><th>Estado</th><th>RSS</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($podcasts as $podcast): ?>
        <?php $feed = $config['app_url'] . '/podcast/' . $podcast['slug'] . '/feed.xml'; ?>
        <tr>
            <td><strong><?= e($podcast['name']) ?></strong><br><span>/podcast/<?= e($podcast['slug']) ?></span></td>
            <td><?= !empty($podcast['active']) ? 'Activo' : 'Inactivo' ?></td>
            <td><button type="button" class="ghost-button" data-copy-url="<?= e($feed) ?>">Copiar RSS</button></td>
            <td class="row-actions">
                <a href="/admin/podcasts/<?= (int) $podcast['id'] ?>/edit">Editar</a>
                <form method="post" action="/admin/podcasts/<?= (int) $podcast['id'] ?>/delete" onsubmit="return confirm('¿Eliminar este podcast y todos sus episodios?')">
                    <?= \App\Core\Csrf::field() ?><button type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$podcasts): ?><tr><td colspan="4">Todavía no hay podcasts.</td></tr><?php endif; ?>
    </tbody>
</table>
