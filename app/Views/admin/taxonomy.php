<form class="taxonomy-form" method="post">
    <?= \App\Core\Csrf::field() ?>
    <input name="name" placeholder="Nombre" required>
    <input name="slug" placeholder="slug-opcional">
    <?php if ($type === 'categories'): ?><input name="description" placeholder="Descripción"><?php endif; ?>
    <button>Crear</button>
</form>
<table class="admin-table editable-table">
    <thead><tr><th>Editar</th><th>Artículos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td>
                <form class="inline-edit" method="post">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                    <input name="name" value="<?= e($item['name']) ?>" required>
                    <input name="slug" value="<?= e($item['slug']) ?>" required>
                    <?php if ($type === 'categories'): ?><input name="description" value="<?= e($item['description'] ?? '') ?>" placeholder="Descripción"><?php endif; ?>
                    <button>Guardar</button>
                </form>
            </td>
            <td><?= e((string) $item['post_count']) ?></td>
            <td><form method="post" action="/admin/<?= e($type) ?>/<?= e((string) $item['id']) ?>/delete" onsubmit="return confirm('Eliminar?')"><?= \App\Core\Csrf::field() ?><button>Eliminar</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
