<main class="login-box">
    <a href="/" class="brand-lockup">jefté montenegro<br>computer engineer</a>
    <div class="rule"></div>
    <form method="post">
        <?= \App\Core\Csrf::field() ?>
        <?php if ($error): ?><p class="notice"><?= e($error) ?></p><?php endif; ?>
        <label>Email <input type="email" name="email" required autocomplete="email"></label>
        <label>Contraseña <input type="password" name="password" required autocomplete="current-password"></label>
        <label class="remember-field"><input type="checkbox" name="remember" value="1"> <span>Recordarme durante 30 días</span></label>
        <?php if (!empty($turnstileSiteKey)): ?>
            <div class="cf-turnstile" data-sitekey="<?= e($turnstileSiteKey) ?>" data-action="<?= e($turnstileAction ?? 'admin_login') ?>"></div>
        <?php endif; ?>
        <button>Entrar</button>
    </form>
    <div class="passkey-login" data-passkey-login data-csrf="<?= e($csrfToken ?? '') ?>">
        <span aria-hidden="true">O</span>
        <button type="button" class="secondary-button" data-passkey-login-button>Iniciar sesión con Passkey</button>
        <p class="notice passkey-message" data-passkey-message hidden></p>
    </div>
</main>
