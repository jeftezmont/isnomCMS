<?php
$roleDescriptions = ['super_admin' => 'Acceso total', 'admin' => 'Administración general', 'editor' => 'Gestión editorial', 'author' => 'Contenido propio'];
$roleIcons = ['super_admin' => 'shield', 'admin' => 'user', 'editor' => 'file-text', 'author' => 'plus'];
$moduleMeta = [
    'dashboard' => ['Panel', 'Acceso al resumen administrativo', 'house'],
    'posts' => ['Artículos', 'Creación, edición y publicación', 'file-text'],
    'podcast' => ['Podcast', 'Programas y episodios', 'mic'],
    'media' => ['Medios', 'Biblioteca y cargas', 'image'],
    'taxonomy' => ['Taxonomías', 'Categorías y etiquetas', 'tag'],
    'users' => ['Usuarios', 'Cuentas y asignación de roles', 'user'],
    'roles' => ['Roles', 'Matriz de autorización', 'key-round'],
    'settings' => ['Ajustes', 'Configuración general', 'settings'],
    'navigation' => ['Navegación', 'Menús públicos', 'list-audio'],
    'security' => ['Seguridad', 'Credenciales personales', 'shield'],
    'tools' => ['Herramientas', 'Salud, deploy, setup y backups', 'wrench'],
];
$actionLabels = [
    'view' => 'Consultar', 'create' => 'Crear', 'edit' => 'Editar', 'edit_own' => 'Editar contenido propio',
    'publish' => 'Publicar', 'delete' => 'Eliminar', 'manage' => 'Administrar', 'assign_roles' => 'Asignar roles',
    'health' => 'Salud del sitio', 'deploy' => 'Deploy', 'setup' => 'Configuración inicial', 'backups' => 'Backups',
];
$groupedPermissions = array_fill_keys(array_keys($moduleMeta), []);
foreach ($permissions as $permission) {
    [$module, $action] = array_pad(explode('.', (string) $permission['slug'], 2), 2, 'manage');
    $permission['action_label'] = $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    $groupedPermissions[$module][] = $permission;
}
$editableRoles = array_values(array_filter($roles, fn(array $role): bool => $role['slug'] !== 'super_admin'));
$activeRole = $editableRoles[0]['slug'] ?? '';
?>

<div class="roles-page" data-roles-matrix data-active-role="<?= e($activeRole) ?>">
    <header class="roles-heading">
        <span class="roles-heading__icon"><?= admin_icon('shield') ?></span>
        <div><h1>Roles y permisos</h1><p>Gestiona qué puede consultar y modificar cada rol dentro de isnomCMS.</p></div>
    </header>

    <?php if (!empty($message)): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>

    <section class="roles-super-note">
        <span><?= admin_icon('shield') ?></span>
        <div><strong>Super Admin</strong><p>Acceso completo al sistema. Sus permisos están protegidos y no pueden modificarse.</p></div>
    </section>

    <?php foreach ($editableRoles as $role): ?>
    <form id="role-form-<?= e($role['slug']) ?>" method="post" action="/admin/roles" class="roles-hidden-form">
        <?= \App\Core\Csrf::field() ?><input type="hidden" name="role" value="<?= e($role['slug']) ?>">
    </form>
    <?php endforeach; ?>

    <div class="roles-toolbar">
        <div class="roles-legend" aria-label="Leyenda"><span><i class="permission-state permission-state--on">✓</i> Permitido</span><span><i class="permission-state"></i> No permitido</span></div>
        <div class="roles-bulk-actions">
            <span>Editando: <strong data-active-role-label><?= e($editableRoles[0]['name'] ?? '') ?></strong></span>
            <button type="button" class="ghost-button" data-role-check-all>Seleccionar todo</button>
            <button type="button" class="ghost-button" data-role-clear-all>Limpiar todo</button>
        </div>
    </div>

    <div class="roles-matrix-scroll" tabindex="0" aria-label="Matriz de roles y permisos">
        <table class="roles-matrix">
            <thead><tr>
                <th scope="colgroup" colspan="2" class="roles-module-head"><strong>Módulos del sistema</strong><span>Permisos disponibles</span></th>
                <?php foreach ($roles as $role): ?>
                <th scope="col" class="role-column role-column--<?= e($role['slug']) ?> <?= $role['slug'] === 'super_admin' ? 'is-protected' : ($role['slug'] === $activeRole ? 'is-selected' : '') ?>" data-role-column="<?= e($role['slug']) ?>">
                    <?php if ($role['slug'] === 'super_admin'): ?>
                        <span class="role-column__icon"><?= admin_icon($roleIcons[$role['slug']]) ?></span><strong><?= e($role['name']) ?></strong><small><?= e($roleDescriptions[$role['slug']]) ?></small>
                    <?php else: ?>
                        <button type="button" data-select-role="<?= e($role['slug']) ?>" data-role-name="<?= e($role['name']) ?>"><span class="role-column__icon"><?= admin_icon($roleIcons[$role['slug']]) ?></span><strong><?= e($role['name']) ?></strong><small><?= e($roleDescriptions[$role['slug']]) ?></small></button>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($groupedPermissions as $module => $items): if ($items === []) continue; foreach ($items as $index => $permission): ?>
                <tr>
                    <?php if ($index === 0): ?><th scope="rowgroup" rowspan="<?= count($items) ?>" class="roles-module-cell"><span><?= admin_icon($moduleMeta[$module][2] ?? 'key-round') ?></span><div><strong><?= e($moduleMeta[$module][0] ?? ucfirst($module)) ?></strong><small><?= e($moduleMeta[$module][1] ?? '') ?></small></div></th><?php endif; ?>
                    <th scope="row" class="permission-label"><strong><?= e($permission['action_label']) ?></strong><code><?= e($permission['slug']) ?></code></th>
                    <?php foreach ($roles as $role): ?>
                    <td class="role-column role-column--<?= e($role['slug']) ?> <?= $role['slug'] === 'super_admin' ? 'is-protected' : ($role['slug'] === $activeRole ? 'is-selected' : '') ?>" data-role-column="<?= e($role['slug']) ?>">
                        <?php if ($role['slug'] === 'super_admin'): ?>
                            <span class="permission-state permission-state--on" role="img" aria-label="Permitido">✓</span>
                        <?php else: ?>
                            <label class="permission-toggle" title="<?= e($role['name'] . ': ' . $permission['slug']) ?>"><input type="checkbox" name="permissions[]" value="<?= e($permission['slug']) ?>" form="role-form-<?= e($role['slug']) ?>" data-role-permission="<?= e($role['slug']) ?>" <?= in_array($permission['slug'], $role['permissions'], true) ? 'checked' : '' ?>><span aria-hidden="true">✓</span><span class="sr-only"><?= e($role['name'] . ' puede ' . $permission['action_label']) ?></span></label>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
        </table>
    </div>

    <footer class="roles-footer">
        <span class="roles-footer__icon"><?= admin_icon('key-round') ?></span>
        <div><strong>Permisos granulares</strong><p>Los cambios afectan el acceso administrativo de los usuarios asignados al rol seleccionado.</p></div>
        <button type="submit" form="role-form-<?= e($activeRole) ?>" data-role-save>Guardar cambios</button>
    </footer>
</div>
