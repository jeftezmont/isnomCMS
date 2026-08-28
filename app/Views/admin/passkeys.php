<section class="settings-panel passkey-panel" data-passkey-register data-csrf="<?= e($csrfToken) ?>">
    <div class="admin-actions compact-actions">
        <h1>Passkeys</h1>
        <button type="button" data-passkey-add>+ Añadir Passkey</button>
    </div>
    <p class="admin-help">Puedes usar Face ID, Touch ID, Apple Passwords, Windows Hello u otro gestor compatible. El login con contraseña sigue disponible como recuperación.</p>
    <label>Nombre de esta passkey <input name="passkey_label" data-passkey-label placeholder="MacBook, iPhone, llave de seguridad"></label>
    <p class="notice passkey-message" data-passkey-message hidden></p>
</section>

<table class="admin-table">
    <thead><tr><th>Nombre</th><th>Creada</th><th>Último uso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($passkeys as $passkey): ?>
        <tr>
            <td><?= e($passkey['label'] ?: 'Passkey') ?></td>
            <td><?= e(excerpt_date($passkey['created_at'])) ?></td>
            <td><?= $passkey['last_used_at'] ? e(excerpt_date($passkey['last_used_at'])) : '<span class="muted-label">Nunca</span>' ?></td>
            <td>
                <form method="post" action="/admin/passkeys/<?= e((string) $passkey['id']) ?>/delete" onsubmit="return confirm('Eliminar esta passkey del CMS? La passkey puede seguir existiendo en tu dispositivo.')">
                    <?= \App\Core\Csrf::field() ?>
                    <button>Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$passkeys): ?>
        <tr><td colspan="4" class="muted-label">Todavía no tienes passkeys registradas.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
