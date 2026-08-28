<?php
$schema = $state['schema'] ?? null;
$connectionStatus = $state['connected'] ? 'ok' : 'error';
$phpCheck = null;
$httpsCheck = null;
foreach ($healthReport['sections'] as $section) {
    foreach ($section['checks'] as $check) {
        if ($check['id'] === 'php_version') {
            $phpCheck = $check;
        }
        if ($check['id'] === 'https') {
            $httpsCheck = $check;
        }
    }
}
?>
<section class="setup-page <?= $authenticated ? '' : 'setup-page--public' ?>">
    <?php if (!$authenticated): ?>
        <a href="/" class="brand-lockup">jefté montenegro<br>computer engineer</a>
        <div class="rule"></div>
    <?php endif; ?>

    <header class="setup-head">
        <div>
            <p class="health-kicker">Asistente seguro</p>
            <h1>Configurar isnomCMS</h1>
            <p>Comprueba el entorno y aplica únicamente cambios aditivos compatibles con <code>database.sql</code>.</p>
        </div>
        <span class="setup-ready setup-ready--<?= $state['ready'] ? 'ok' : 'warning' ?>">
            <?= $state['ready'] ? '✓ Listo' : '! Requiere atención' ?>
        </span>
    </header>

    <?php if ($notice): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice notice--error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($locked): ?>
        <section class="setup-lock">
            <h2>Autorizar instalación</h2>
            <?php if ($setupTokenConfigured): ?>
                <p>Introduce la clave definida en <code>SETUP_TOKEN</code>. Solo se conservará la autorización en esta sesión.</p>
                <form method="post" action="/admin/setup">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="action" value="unlock">
                    <label>Clave de instalación<input type="password" name="setup_token" required autocomplete="off"></label>
                    <button type="submit">Autorizar esta sesión</button>
                </form>
            <?php else: ?>
                <p class="notice">Define <code>SETUP_TOKEN</code> en el archivo <code>.env</code> del servidor y vuelve a cargar esta página. El valor nunca se mostrará aquí.</p>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <div class="setup-steps">
            <section class="setup-step">
                <span class="setup-step__number">1</span>
                <div class="setup-step__content">
                    <h2>Entorno</h2>
                    <div class="setup-checks">
                        <?php foreach (array_filter([$phpCheck, $httpsCheck]) as $check): ?>
                            <p class="health-status health-status--<?= e($check['status']) ?>"><b aria-hidden="true"><?= $check['status'] === 'ok' ? '✓' : '!' ?></b><?= e($check['label']) ?> — <?= e($check['message']) ?></p>
                        <?php endforeach; ?>
                        <p class="health-status health-status--<?= e($connectionStatus) ?>"><b aria-hidden="true"><?= $state['connected'] ? '✓' : '×' ?></b>MySQL — <?= e($state['connected'] ? 'Conexión disponible.' : $state['message']) ?></p>
                    </div>
                </div>
            </section>

            <section class="setup-step">
                <span class="setup-step__number">2</span>
                <div class="setup-step__content">
                    <h2>Configuración</h2>
                    <p>Las variables se comprueban sin revelar sus valores.<?php if ($authenticated): ?> Consulta <a href="/admin/health">Salud del sitio</a> para el detalle completo.<?php endif; ?></p>
                    <div class="setup-mini-summary">
                        <span><strong><?= e((string) $healthReport['counts']['ok']) ?></strong> correctas</span>
                        <span><strong><?= e((string) $healthReport['counts']['warning']) ?></strong> advertencias</span>
                        <span><strong><?= e((string) $healthReport['counts']['error']) ?></strong> errores</span>
                    </div>
                </div>
            </section>

            <section class="setup-step">
                <span class="setup-step__number">3</span>
                <div class="setup-step__content">
                    <h2>Base de datos</h2>
                    <?php if (!$state['connected']): ?>
                        <p class="notice notice--error">No se aplicará ningún cambio hasta que MySQL responda correctamente.</p>
                    <?php elseif ($schema === null): ?>
                        <p class="notice notice--error">No se pudo leer o interpretar <code>database.sql</code>.</p>
                    <?php else: ?>
                        <div class="setup-schema-summary">
                            <span><strong><?= e((string) $schema['existing_count']) ?>/<?= e((string) $schema['expected_count']) ?></strong> tablas</span>
                            <span><strong><?= e((string) count($schema['missing_tables'])) ?></strong> faltantes</span>
                            <span><strong><?= e((string) $schema['safe_column_count']) ?></strong> columnas seguras</span>
                            <span><strong><?= e((string) $schema['missing_index_count']) ?></strong> índices</span>
                        </div>

                        <?php if ($schema['missing_tables'] !== []): ?>
                            <p><strong>Tablas faltantes:</strong> <?= e(implode(', ', $schema['missing_tables'])) ?></p>
                        <?php endif; ?>
                        <?php foreach ($schema['tables'] as $table => $tableState): ?>
                            <?php if ($tableState['missingColumns'] !== []): ?>
                                <p><strong><?= e($table) ?>:</strong> faltan <?= e(implode(', ', $tableState['missingColumns'])) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($schema['manual_column_count'] > 0): ?>
                            <p class="notice">Hay columnas que requieren revisión manual porque añadirlas automáticamente podría afectar datos existentes.</p>
                        <?php endif; ?>

                        <?php if (!$schema['complete']): ?>
                            <form method="post" action="/admin/setup" onsubmit="return confirm('Se crearán únicamente tablas, columnas e índices faltantes considerados seguros. No se borrarán datos. ¿Continuar?')">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="action" value="repair_schema">
                                <button type="submit">Aplicar actualizaciones seguras</button>
                            </form>
                        <?php else: ?>
                            <p class="health-status health-status--ok"><b aria-hidden="true">✓</b>El esquema coincide con database.sql.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (is_array($result)): ?>
                        <div class="setup-result">
                            <?php foreach ($result['applied'] ?? [] as $item): ?><p>✓ <?= e($item) ?></p><?php endforeach; ?>
                            <?php foreach ($result['skipped'] ?? [] as $item): ?><p>! <?= e($item) ?></p><?php endforeach; ?>
                            <?php foreach ($result['errors'] ?? [] as $item): ?><p>× <?= e($item) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="setup-step">
                <span class="setup-step__number">4</span>
                <div class="setup-step__content">
                    <h2>Administrador</h2>
                    <?php if (($state['admin_count'] ?? 0) > 0): ?>
                        <p class="health-status health-status--ok"><b aria-hidden="true">✓</b><?= e((string) $state['admin_count']) ?> usuario(s) administrador(es) disponible(s).</p>
                    <?php elseif (empty($schema) || empty($schema['tables']['users']['exists'])): ?>
                        <p>Primero crea la tabla <code>users</code> desde el paso anterior.</p>
                    <?php else: ?>
                        <form class="setup-admin-form" method="post" action="/admin/setup">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="create_admin">
                            <label>Nombre<input name="name" required maxlength="120" autocomplete="name"></label>
                            <label>Correo<input type="email" name="email" required maxlength="190" autocomplete="email"></label>
                            <label>Contraseña<input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
                            <label>Confirmar contraseña<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
                            <button type="submit">Crear primer administrador</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="setup-step setup-step--final">
                <span class="setup-step__number">5</span>
                <div class="setup-step__content">
                    <h2>Estado final</h2>
                    <?php if ($state['ready']): ?>
                        <p class="setup-complete">✓ isnomCMS está listo</p>
                        <a class="button" href="/admin">Ir al dashboard</a>
                    <?php else: ?>
                        <p>Completa los elementos marcados arriba. Ninguna comprobación se asumirá como correcta sin verificarla.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>
</section>
