<section class="settings-panel backup-panel">
    <div class="backup-head">
        <div>
            <h2>Exportar contenido</h2>
            <p>Descarga una copia de artículos, categorías, etiquetas, medios, navegación y ajustes. No incluye usuarios, passkeys, sesiones ni intentos de login.</p>
        </div>
        <div class="backup-actions">
            <?php if (class_exists(\ZipArchive::class)): ?>
            <form method="post" action="/admin/backups/download?format=zip">
                <?= \App\Core\Csrf::field() ?>
                <button>Crear backup ZIP</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/backups/download?format=json">
                <?= \App\Core\Csrf::field() ?>
                <button class="ghost-button">Descargar JSON</button>
            </form>
            <form method="post" action="/admin/backups/download?format=sql">
                <?= \App\Core\Csrf::field() ?>
                <button class="ghost-button">Descargar SQL</button>
            </form>
        </div>
    </div>
</section>

<table class="admin-table">
    <thead><tr><th>Tabla</th><th>Estado</th><th>Filas</th></tr></thead>
    <tbody>
    <?php foreach ($summary as $table => $info): ?>
        <tr>
            <td><?= e($table) ?></td>
            <td><?= !empty($info['error']) ? 'No verificable' : (!empty($info['exists']) ? 'Lista' : 'No encontrada') ?></td>
            <td><?= e((string) ($info['rows'] ?? 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
