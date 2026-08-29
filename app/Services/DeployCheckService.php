<?php

namespace App\Services;

use App\Core\Database;

final class DeployCheckService
{
    public function __construct(private array $config)
    {
    }

    public function run(): array
    {
        $health = (new HealthCheckService($this->config))->run();
        $checks = [
            $this->fromHealth($health, 'php_version', 'PHP compatible'),
            $this->aggregateExtensions($health),
            $this->fromHealth($health, 'https', 'HTTPS activo'),
            $this->fromHealth($health, 'database_connection', 'Conexión MySQL'),
            $this->tablesComplete($health),
            $this->adminExists(),
            $this->fromHealth($health, 'uploads_directory', 'Uploads escribible'),
            $this->fromHealth($health, 'logs_directory', 'Logs escribibles'),
            $this->zipAvailable(),
            $this->fromHealth($health, 'env_file', '.env o variables de hosting'),
            $this->fromHealth($health, 'production_mode', 'APP_ENV=production'),
            $this->fromHealth($health, 'turnstile', 'Turnstile configurado'),
            $this->fromHealth($health, 'app_key', 'APP_KEY para cifrado 2FA'),
            $this->fromHealth($health, 'webauthn_origin', 'Passkeys/WebAuthn'),
            $this->fileCheck('favicon', 'Favicon disponible', ROOT_PATH . '/public/favicon.ico'),
            $this->routeFileCheck('robots', 'robots.txt disponible', '/robots.txt'),
            $this->routeFileCheck('sitemap', 'sitemap.xml disponible', '/sitemap.xml'),
            $this->sensitiveFilesProtected(),
            $this->securityHeadersAvailable(),
            $this->backupsAvailable(),
        ];

        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0, 'unknown' => 0];
        foreach ($checks as $check) {
            $counts[$check['status']]++;
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'checks' => $checks,
            'counts' => $counts,
            'total' => count($checks),
            'ready' => $counts['error'] === 0,
            'status' => $counts['error'] > 0 ? 'error' : ($counts['warning'] > 0 ? 'warning' : 'ok'),
        ];
    }

    private function fromHealth(array $health, string $id, string $label): array
    {
        $check = (new HealthCheckService($this->config))->find($health, $id);
        if (!$check) {
            return $this->check($id, $label, 'unknown', 'No se pudo verificar este punto.');
        }
        return $this->check($id, $label, $check['status'], $check['message'], $check['detail'] ?? null);
    }

    private function aggregateExtensions(array $health): array
    {
        $required = ['php_ext_pdo', 'php_ext_pdo_mysql', 'php_ext_fileinfo', 'php_ext_openssl', 'php_ext_sodium', 'php_ext_json', 'php_ext_mbstring', 'php_ext_iconv', 'php_ext_session'];
        $missing = [];
        foreach ($required as $id) {
            $check = (new HealthCheckService($this->config))->find($health, $id);
            if (($check['status'] ?? 'error') !== 'ok') {
                $missing[] = $check['label'] ?? $id;
            }
        }
        return $missing === []
            ? $this->check('php_required_extensions', 'Extensiones PHP requeridas', 'ok', 'Todas las extensiones necesarias están disponibles.')
            : $this->check('php_required_extensions', 'Extensiones PHP requeridas', 'error', 'Faltan extensiones: ' . implode(', ', $missing) . '.');
    }

    private function tablesComplete(array $health): array
    {
        $connection = (new HealthCheckService($this->config))->find($health, 'database_connection');
        if (($connection['status'] ?? 'error') !== 'ok') {
            return $this->check('database_schema_ready', 'Tablas completas', 'error', 'No se pueden verificar tablas porque MySQL no conecta.');
        }

        $badTables = [];
        foreach ($health['sections'] ?? [] as $section) {
            if (($section['title'] ?? '') !== 'Base de datos') {
                continue;
            }
            foreach ($section['checks'] ?? [] as $check) {
                if (str_starts_with((string) ($check['id'] ?? ''), 'table_') && ($check['status'] ?? '') !== 'ok') {
                    $badTables[] = $check['label'];
                }
            }
        }
        return $badTables === []
            ? $this->check('database_schema_ready', 'Tablas completas', 'ok', 'El esquema requerido está disponible.')
            : $this->check('database_schema_ready', 'Tablas completas', 'error', 'Hay tablas o columnas pendientes.', implode(', ', $badTables));
    }

    private function adminExists(): array
    {
        try {
            $count = (int) Database::connect($this->config)->query('SELECT COUNT(*) FROM users')->fetchColumn();
            return $count > 0
                ? $this->check('admin_user', 'Administrador creado', 'ok', $count . ' usuario(s) disponible(s).')
                : $this->check('admin_user', 'Administrador creado', 'error', 'Todavía no existe un usuario administrador.');
        } catch (\Throwable) {
            return $this->check('admin_user', 'Administrador creado', 'unknown', 'No se pudo consultar la tabla users.');
        }
    }

    private function fileCheck(string $id, string $label, string $path): array
    {
        return is_file($path)
            ? $this->check($id, $label, 'ok', 'Archivo encontrado.')
            : $this->check($id, $label, 'error', 'Archivo no encontrado.');
    }

    private function routeFileCheck(string $id, string $label, string $path): array
    {
        $controller = ROOT_PATH . '/app/Controllers/SeoController.php';
        $routes = ROOT_PATH . '/public/index.php';
        $available = is_file($controller) && is_file($routes) && str_contains((string) file_get_contents($routes), $path);
        return $available
            ? $this->check($id, $label, 'ok', 'Ruta registrada en el CMS.')
            : $this->check($id, $label, 'error', 'Ruta no encontrada.');
    }

    private function sensitiveFilesProtected(): array
    {
        $rootRules = is_file(ROOT_PATH . '/.htaccess') ? (string) file_get_contents(ROOT_PATH . '/.htaccess') : '';
        $publicRules = is_file(ROOT_PATH . '/public/.htaccess') ? (string) file_get_contents(ROOT_PATH . '/public/.htaccess') : '';
        $storageRules = is_file(ROOT_PATH . '/storage/.htaccess') ? (string) file_get_contents(ROOT_PATH . '/storage/.htaccess') : '';

        $rules = [
            'SQL' => str_contains($rootRules, 'database.sql') || str_contains($rootRules, '\\.sql'),
            '.env' => str_contains($rootRules, '.env'),
            'directorios internos' => str_contains($rootRules, 'storage') && str_contains($rootRules, 'app'),
            'public/.htaccess' => str_contains($publicRules, 'FilesMatch') && str_contains($publicRules, 'Require all denied'),
            'storage/.htaccess' => str_contains($storageRules, 'Require all denied'),
        ];
        $missing = array_keys(array_filter($rules, fn(bool $available): bool => !$available));

        return $missing === []
            ? $this->check('sensitive_files', 'Archivos sensibles protegidos', 'ok', 'Reglas .htaccess disponibles.')
            : $this->check('sensitive_files', 'Archivos sensibles protegidos', 'warning', 'Revisa reglas .htaccess en Hostinger.', 'Falta verificar: ' . implode(', ', $missing) . '.');
    }

    private function backupsAvailable(): array
    {
        $ok = is_file(ROOT_PATH . '/app/Services/BackupService.php')
            && is_file(ROOT_PATH . '/app/Views/admin/backups.php');
        return $ok
            ? $this->check('backups', 'Backups disponibles', 'ok', 'El panel de backups está instalado.')
            : $this->check('backups', 'Backups disponibles', 'error', 'Falta el módulo de backups.');
    }

    private function securityHeadersAvailable(): array
    {
        $installed = is_file(ROOT_PATH . '/app/Services/SecurityHeaders.php')
            && str_contains((string) file_get_contents(ROOT_PATH . '/public/index.php'), 'SecurityHeaders');
        return $installed
            ? $this->check('security_headers', 'Headers de seguridad', 'ok', 'El front controller envía headers de hardening.')
            : $this->check('security_headers', 'Headers de seguridad', 'error', 'No se encontró el servicio de headers.');
    }

    private function zipAvailable(): array
    {
        return class_exists(\ZipArchive::class)
            ? $this->check('zip_backup', 'Backup ZIP', 'ok', 'La extensión zip está disponible.')
            : $this->check('zip_backup', 'Backup ZIP', 'warning', 'La extensión zip no está disponible; JSON y SQL siguen funcionando.');
    }

    private function check(string $id, string $label, string $status, string $message, ?string $detail = null): array
    {
        return compact('id', 'label', 'status', 'message', 'detail');
    }
}
