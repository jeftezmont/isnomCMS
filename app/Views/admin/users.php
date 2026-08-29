<?php $error = $error ?: ($_SESSION['_users_error'] ?? null); unset($_SESSION['_users_error']); ?>
<?php if (!empty($error)): ?>
    <p class="notice"><?= e($error) ?></p>
<?php endif; ?>

<form class="user-form <?= $canAssignRoles ? 'has-role' : '' ?>" method="post" autocomplete="off">
    <?= \App\Core\Csrf::field() ?>
    <input name="name" placeholder="Nombre" required>
    <input type="email" name="email" placeholder="correo@dominio.com" required>
    <input type="password" name="password" placeholder="Contraseña nueva" minlength="8" required>
    <?php if ($canAssignRoles): ?><select name="role" aria-label="Rol">
        <?php foreach ($roles as $role): ?><option value="<?= e($role['slug']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?>
    </select><?php else: ?><input type="hidden" name="role" value="author"><?php endif; ?>
    <button>Crear usuario</button>
</form>

<div class="admin-actions"><h1>Usuarios</h1></div>
<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Creado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= e($user['name']) ?></td>
            <td><?= e($user['email']) ?></td>
            <td>
                <?php if ($canAssignRoles): ?>
                <form method="post" action="/admin/users/<?= e((string) $user['id']) ?>/role">
                    <?= \App\Core\Csrf::field() ?>
                    <select name="role" onchange="this.form.submit()" aria-label="Rol de <?= e($user['name']) ?>">
                        <?php foreach ($roles as $role): ?><option value="<?= e($role['slug']) ?>" <?= $role['slug'] === ($user['role_slug'] ?? '') ? 'selected' : '' ?>><?= e($role['name']) ?></option><?php endforeach; ?>
                    </select>
                </form>
                <?php else: ?><?= e($user['role_name'] ?? 'Sin rol') ?><?php endif; ?>
            </td>
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
