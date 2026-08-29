<?php $setupActive = !empty($setupSecret) && !empty($setupUri); ?>
<?php if ($error): ?><p class="notice notice--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (!empty($message)): ?><p class="notice" role="status"><?= e($message) ?></p><?php endif; ?>

<section class="security-summary settings-panel">
    <h1>Seguridad de la cuenta</h1>
    <div class="stats">
        <article><strong><?= count($passkeys) ?></strong><span>Passkeys registradas</span></article>
        <article><strong><?= $twoFactorEnabled ? 'Activada' : 'Desactivada' ?></strong><span>Autenticación en dos factores</span></article>
        <article><strong><?= (int) $recoveryCount ?></strong><span>Códigos de recuperación disponibles</span></article>
    </div>
</section>

<section class="settings-panel passkey-panel" data-passkey-register data-csrf="<?= e($csrfToken) ?>">
    <div class="admin-actions compact-actions"><h2>Passkeys</h2><button type="button" data-passkey-add>+ Añadir Passkey</button></div>
    <p class="admin-help">Usa Face ID, Touch ID, Apple Passwords, Windows Hello u otro gestor compatible.</p>
    <label>Nombre de esta passkey<input name="passkey_label" data-passkey-label placeholder="MacBook, iPhone, llave de seguridad"></label>
    <p class="notice passkey-message" data-passkey-message hidden></p>
    <table class="admin-table"><thead><tr><th>Nombre</th><th>Creada</th><th>Último uso</th><th></th></tr></thead><tbody>
    <?php foreach ($passkeys as $passkey): ?><tr><td><?= e($passkey['label'] ?: 'Passkey') ?></td><td><?= e(excerpt_date($passkey['created_at'])) ?></td><td><?= $passkey['last_used_at'] ? e(excerpt_date($passkey['last_used_at'])) : 'Nunca' ?></td><td><form method="post" action="/admin/passkeys/<?= (int) $passkey['id'] ?>/delete" onsubmit="return confirm('¿Eliminar esta Passkey?')"><?= \App\Core\Csrf::field() ?><button>Eliminar</button></form></td></tr><?php endforeach; ?>
    <?php if (!$passkeys): ?><tr><td colspan="4">Todavía no tienes Passkeys registradas.</td></tr><?php endif; ?>
    </tbody></table>
</section>

<section class="settings-panel">
    <h2>Autenticación en dos factores</h2>
    <p>Estado: <strong><?= $twoFactorEnabled ? 'Activada' : 'Desactivada' ?></strong></p>
    <?php if (!$appKeyReady): ?><p class="notice notice--error">Configura APP_KEY con una clave aleatoria de al menos 32 caracteres antes de activar 2FA.</p><?php endif; ?>
    <?php if (!$twoFactorEnabled && !$setupActive): ?>
        <form method="post" action="/admin/security/2fa/start" class="security-form"><?= \App\Core\Csrf::field() ?><label>Confirma tu contraseña actual<input type="password" name="password" required autocomplete="current-password"></label><button <?= !$appKeyReady ? 'disabled' : '' ?>>Activar 2FA</button></form>
    <?php elseif ($setupActive): ?>
        <div class="two-factor-setup" data-totp-qr="<?= e($setupUri) ?>"><div data-totp-qr-canvas aria-label="Código QR para configurar 2FA"></div><div><p>Escanea el QR con Apple Passwords, Google Authenticator, 1Password u otra aplicación TOTP.</p><label>Secreto manual<input readonly value="<?= e($setupSecret) ?>"></label></div></div>
        <form method="post" action="/admin/security/2fa/confirm" class="security-form"><?= \App\Core\Csrf::field() ?><label>Código de seis dígitos<input name="code" required inputmode="numeric" autocomplete="one-time-code"></label><button>Confirmar y activar</button></form>
    <?php else: ?>
        <form method="post" action="/admin/security/2fa/disable" class="security-form" onsubmit="return confirm('¿Desactivar la autenticación en dos factores?')"><?= \App\Core\Csrf::field() ?><label>Contraseña actual<input type="password" name="password" required autocomplete="current-password"></label><label>Código 2FA o de recuperación<input name="code" required autocomplete="one-time-code"></label><button>Desactivar autenticación en dos factores</button></form>
    <?php endif; ?>
</section>

<section class="settings-panel">
    <h2>Códigos de recuperación</h2>
    <?php if (!empty($recoveryCodes)): ?><p>Estos códigos solo se muestran una vez. Guárdalos en un lugar seguro.</p><pre class="recovery-codes"><?= e(implode("\n", $recoveryCodes)) ?></pre><div class="admin-actions"><button type="button" data-copy-recovery="<?= e(implode("\n", $recoveryCodes)) ?>">Copiar códigos</button><button type="button" class="secondary-button" data-download-recovery="<?= e(implode("\n", $recoveryCodes)) ?>">Descargar .txt</button></div><?php elseif ($twoFactorEnabled): ?><p><?= (int) $recoveryCount ?> códigos disponibles.</p><form method="post" action="/admin/security/2fa/recovery" class="security-form" onsubmit="return confirm('Los códigos anteriores dejarán de funcionar. ¿Continuar?')"><?= \App\Core\Csrf::field() ?><label>Confirma tu contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Regenerar códigos</button></form><?php else: ?><p>Se generarán cuando actives 2FA.</p><?php endif; ?>
</section>
