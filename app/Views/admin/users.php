<?php if (!empty($error)): ?>
    <p class="notice"><?= e($error) ?></p>
<?php endif; ?>

<form class="user-form" method="post" autocomplete="off">
    <?= \App\Core\Csrf::field() ?>
    <input name="name" placeholder="Nombre" required>
    <input type="email" name="email" placeholder="correo@dominio.com" required>
    <input type="password" name="password" placeholder="Contraseña nueva" minlength="8" required>
    <button>Crear usuario</button>
</form>

<div class="admin-actions"><h1>Usuarios</h1></div>
<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Creado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= e($user['name']) ?></td>
            <td><?= e($user['email']) ?></td>
            <td><?= e(excerpt_date($user['created_at'])) ?></td>
            <td>
                <?php if ((int) $user['id'] === (int) $currentUserId): ?>
                    <span class="muted-label">Actual</span>
                <?php else: ?>
                    <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/delete" onsubmit="return confirm('Eliminar usuario?')">
                        <?= \App\Core\Csrf::field() ?>
                        <button>Eliminar</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
