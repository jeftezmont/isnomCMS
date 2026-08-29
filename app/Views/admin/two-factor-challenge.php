<main class="login-box">
    <a href="/" class="brand-lockup">jefté montenegro<br>computer engineer</a>
    <div class="rule"></div>
    <form method="post">
        <?= \App\Core\Csrf::field() ?>
        <h1>Verificación en dos pasos</h1>
        <p>Introduce el código de tu aplicación de autenticación o un código de recuperación.</p>
        <?php if ($error): ?><p class="notice" role="alert"><?= e($error) ?></p><?php endif; ?>
        <label>Código de autenticación<input name="code" required inputmode="numeric" autocomplete="one-time-code" autofocus></label>
        <button>Verificar y entrar</button>
    </form>
    <form method="post" action="/admin/login/2fa/cancel">
        <?= \App\Core\Csrf::field() ?>
        <button class="secondary-button">Cancelar</button>
    </form>
</main>
