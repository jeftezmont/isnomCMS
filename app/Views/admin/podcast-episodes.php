<div class="admin-actions"><a class="primary-button" href="/admin/podcast-episodes/create">+ Nuevo episodio</a><a class="secondary-button" href="/admin/podcasts">Configurar podcast</a></div>
<table class="admin-table"><thead><tr><th>Episodio</th><th>Podcast</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>
<?php foreach ($episodes as $episode): ?><tr>
    <td><strong><?= e($episode['title']) ?></strong><br><span><?= e($episode['audio_source']) ?> · <?= e($episode['audio_mime_type']) ?></span></td>
    <td><?= e($episode['podcast_name']) ?></td><td><?= e($episode['status']) ?></td><td><?= e(excerpt_date($episode['published_at'])) ?></td>
    <td class="row-actions">
        <a href="/admin/podcast-episodes/<?= (int) $episode['id'] ?>/edit">Editar</a>
        <form method="post" action="/admin/podcast-episodes/<?= (int) $episode['id'] ?>/delete" onsubmit="return confirm('¿Eliminar este episodio?')">
            <?= \App\Core\Csrf::field() ?><button type="submit">Eliminar</button>
        </form>
    </td>
</tr><?php endforeach; ?>
<?php if (!$episodes): ?><tr><td colspan="5">Todavía no hay episodios.</td></tr><?php endif; ?>
</tbody></table>
<?php $paginationBase = '/admin/podcast-episodes'; $paginationQuery = []; require APP_PATH . '/Views/public/pagination.php'; ?>
