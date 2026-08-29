<div class="admin-actions"><h1>Roles y permisos</h1></div>
<?php if (!empty($message)): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
<p class="form-note">Super Admin conserva acceso completo. Los demás roles pueden ajustarse mediante permisos granulares.</p>

<?php foreach ($roles as $role): ?>
<section class="settings-group">
    <h2><?= e($role['name']) ?></h2>
    <?php if ($role['slug'] === 'super_admin'): ?>
        <p>Acceso completo protegido.</p>
    <?php else: ?>
    <form method="post" action="/admin/roles">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="role" value="<?= e($role['slug']) ?>">
        <div class="permission-grid">
            <?php foreach ($permissions as $permission): ?>
            <label><input type="checkbox" name="permissions[]" value="<?= e($permission['slug']) ?>" <?= in_array($permission['slug'], $role['permissions'], true) ? 'checked' : '' ?>> <?= e($permission['slug']) ?></label>
            <?php endforeach; ?>
        </div>
        <button>Guardar permisos</button>
    </form>
    <?php endif; ?>
</section>
<?php endforeach; ?>
