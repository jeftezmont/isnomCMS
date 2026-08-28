<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class HealthCheckService
{
    public function __construct(private array $config)
    {
    }

    public function run(): array
    {
        $sections = [
            ['title' => 'Base de datos', 'checks' => $this->databaseChecks()],
            ['title' => 'Sistema de archivos', 'checks' => $this->filesystemChecks()],
            ['title' => 'Configuración', 'checks' => $this->configurationChecks()],
            ['title' => 'Servicios de seguridad', 'checks' => $this->securityServiceChecks()],
            ['title' => 'PHP', 'checks' => $this->phpChecks()],
            ['title' => 'Entorno', 'checks' => $this->environmentChecks()],
        ];

        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0, 'unknown' => 0];
        foreach ($sections as $section) {
            foreach ($section['checks'] as $check) {
                $counts[$check['status']]++;
            }
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => $sections,
            'counts' => $counts,
            'status' => $counts['error'] > 0 ? 'error' : ($counts['warning'] > 0 ? 'warning' : 'ok'),
            'total' => array_sum($counts),
        ];
    }

    public function find(array $report, string $id): ?array
    {
        foreach ($report['sections'] ?? [] as $section) {
            foreach ($section['checks'] ?? [] as $check) {
                if (($check['id'] ?? '') === $id) {
                    return $check;
                }
            }
        }
        return null;
    }

    private function databaseChecks(): array
    {
        try {
            $pdo = Database::connect($this->config);
        } catch (\Throwable $exception) {
            return [$this->check('database_connection', 'Conexión MySQL', 'error', 'No fue posible conectar con MySQL.', 'Revisa DB_HOST, DB_NAME, DB_USER y DB_PASSWORD.')];
        }

        $checks = [$this->check('database_connection', 'Conexión MySQL', 'ok', 'La conexión responde correctamente.')];

        try {
            $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $checks[] = $database !== ''
                ? $this->check('database_selected', 'Base de datos seleccionada', 'ok', 'Hay una base de datos activa.')
                : $this->check('database_selected', 'Base de datos seleccionada', 'error', 'No hay una base de datos seleccionada.');
        } catch (\Throwable) {
            $checks[] = $this->check('database_selected', 'Base de datos seleccionada', 'error', 'No se pudo verificar la base activa.');
        }

        try {
            $pdo->query('SELECT 1')->fetchColumn();
            $checks[] = $this->check('database_select', 'Operaciones SELECT', 'ok', 'Las consultas de lectura funcionan.');
        } catch (\Throwable) {
            $checks[] = $this->check('database_select', 'Operaciones SELECT', 'error', 'Las consultas de lectura fallaron.');
        }

        $checks[] = $this->databaseWriteCheck($pdo);
        $schema = $this->expectedSchema();
        if ($schema === []) {
            $checks[] = $this->check('database_schema_source', 'Esquema esperado', 'unknown', 'No se pudo interpretar database.sql.');
            return $checks;
        }

        try {
            $tables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            $checks[] = $this->check('database_tables', 'Tablas requeridas', 'error', 'No se pudo consultar la estructura de tablas.');
            return $checks;
        }

        foreach ($schema as $table => $columns) {
            if (!in_array($table, $tables, true)) {
                $checks[] = $this->check('table_' . $table, $table, 'error', 'Falta la tabla requerida.', 'Puede crearse desde database.sql sin borrar datos existentes.');
                continue;
            }

            try {
                $actualColumns = array_column($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(), 'Field');
                $missing = array_values(array_diff($columns, $actualColumns));
                $checks[] = $missing === []
                    ? $this->check('table_' . $table, $table, 'ok', 'Tabla y columnas esenciales disponibles.')
                    : $this->check('table_' . $table, $table, 'error', 'Estructura incompatible.', 'Faltan columnas: ' . implode(', ', $missing) . '.');
            } catch (\Throwable) {
                $checks[] = $this->check('table_' . $table, $table, 'unknown', 'La tabla existe, pero no se pudo verificar su estructura.');
            }
        }

        return $checks;
    }

    private function databaseWriteCheck(PDO $pdo): array
    {
        try {
            $pdo->exec('CREATE TEMPORARY TABLE isnomcms_health_probe (id INT NOT NULL)');
            $pdo->exec('INSERT INTO isnomcms_health_probe (id) VALUES (1)');
            $pdo->exec('DROP TEMPORARY TABLE isnomcms_health_probe');
            return $this->check('database_write', 'Operaciones de escritura', 'ok', 'La base permite escrituras seguras.');
        } catch (\Throwable) {
            try {
                $pdo->exec('DROP TEMPORARY TABLE IF EXISTS isnomcms_health_probe');
            } catch (\Throwable) {
            }
            return $this->check('database_write', 'Operaciones de escritura', 'error', 'No se pudo completar una escritura temporal segura.');
        }
    }

    private function expectedSchema(): array
    {
        return (new DatabaseSchemaService(ROOT_PATH . '/database.sql'))->expectedColumns();
    }

    private function filesystemChecks(): array
    {
        $this->ensureDirectory($this->config['upload_dir'] ?? ROOT_PATH . '/public/uploads');
        $this->ensureDirectory($this->config['storage_dir'] ?? ROOT_PATH . '/storage');
        $this->ensureDirectory($this->config['log_dir'] ?? ROOT_PATH . '/storage/logs');

        return [
            $this->pathCheck('uploads_directory', 'uploads/', $this->config['upload_dir'] ?? ROOT_PATH . '/public/uploads'),
            $this->pathCheck('storage_directory', 'storage/', $this->config['storage_dir'] ?? ROOT_PATH . '/storage'),
            $this->pathCheck('logs_directory', 'storage/logs/', $this->config['log_dir'] ?? ROOT_PATH . '/storage/logs'),
        ];
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    private function pathCheck(string $id, string $label, string $path): array
    {
        if (!is_dir($path)) {
            return $this->check($id, $label, 'error', 'El directorio no existe.');
        }
        if (!is_writable($path)) {
            return $this->check($id, $label, 'error', 'El directorio existe, pero no es escribible.');
        }
        return $this->check($id, $label, 'ok', 'Existe y es escribible.');
    }

    private function configurationChecks(): array
    {
        $checks = [];
        $envPath = ROOT_PATH . '/.env';
        $checks[] = is_file($envPath)
            ? $this->check('env_file', 'Archivo .env', 'ok', 'El archivo de entorno existe.')
            : $this->check('env_file', 'Fuente de configuración', 'ok', 'No existe .env; se utiliza la configuración efectiva de config.php.');

        $required = [
            'APP_ENV' => $this->config['app_env'] ?? '',
            'APP_URL' => $this->config['app_url'] ?? '',
            'DB_HOST' => $this->config['db']['host'] ?? '',
            'DB_NAME' => $this->config['db']['name'] ?? '',
            'DB_USER' => $this->config['db']['user'] ?? '',
        ];
        foreach ($required as $key => $value) {
            $configured = $this->configuredValue((string) $value);
            $checks[] = $this->check('env_' . strtolower($key), $key, $configured ? 'ok' : 'error', $configured ? 'Configurado.' : 'No configurado.');
        }

        $passwordConfigured = $this->configuredValue((string) ($this->config['db']['password'] ?? ''));
        $production = strtolower((string) ($this->config['app_env'] ?? 'production')) === 'production';
        $checks[] = $this->check(
            'env_db_password',
            'DB_PASSWORD',
            $passwordConfigured || !$production ? 'ok' : 'warning',
            $passwordConfigured ? 'Configurado.' : ($production ? 'No configurado.' : 'Vacío; puede ser válido en desarrollo local.'),
            $production && !$passwordConfigured ? 'En producción normalmente debe configurarse.' : null
        );

        $setupTokenConfigured = $this->configuredValue((string) ($this->config['setup_token'] ?? ''));
        $checks[] = $this->check(
            'env_setup_token',
            'SETUP_TOKEN',
            $setupTokenConfigured || !$production ? 'ok' : 'warning',
            $setupTokenConfigured ? 'Configurado.' : ($production ? 'No configurado.' : 'Opcional en desarrollo local.'),
            $production && !$setupTokenConfigured ? 'Se recomienda durante la instalación inicial y deja de ser necesario cuando ya existe un administrador.' : null
        );

        return $checks;
    }

    private function configuredValue(string $value): bool
    {
        $value = trim($value);
        return $value !== '' && !in_array(strtolower($value), [
            'your_database',
            'your_user',
            'your_password',
            'change_me',
        ], true);
    }

    private function securityServiceChecks(): array
    {
        $siteKey = trim((string) ($this->config['turnstile']['site_key'] ?? ''));
        $secretKey = trim((string) ($this->config['turnstile']['secret_key'] ?? ''));
        if ($siteKey !== '' && $secretKey !== '') {
            $turnstile = $this->check('turnstile', 'Cloudflare Turnstile', 'ok', 'Site Key y Secret Key configuradas.');
        } elseif ($siteKey === '' && $secretKey === '') {
            $turnstile = $this->check('turnstile', 'Cloudflare Turnstile', 'warning', 'Funcionalidad deshabilitada.', 'Configura ambas claves para proteger el login.');
        } else {
            $turnstile = $this->check('turnstile', 'Cloudflare Turnstile', 'error', 'Configuración incompleta.', 'Se requieren Site Key y Secret Key.');
        }

        $rpId = trim((string) ($this->config['webauthn']['rp_id'] ?? ''));
        $origins = array_values(array_filter($this->config['webauthn']['origins'] ?? []));
        $originSecure = $origins !== [] && count(array_filter($origins, fn(string $origin): bool => $this->secureWebAuthnOrigin($origin))) === count($origins);

        return [
            $turnstile,
            $this->check('webauthn_rp', 'WebAuthn RP ID', $rpId !== '' ? 'ok' : 'error', $rpId !== '' ? 'Configurado para ' . $rpId . '.' : 'No configurado.'),
            $this->check('webauthn_origin', 'WebAuthn Origin', $originSecure ? 'ok' : ($origins === [] ? 'error' : 'warning'), $originSecure ? 'Los origins configurados son compatibles.' : ($origins === [] ? 'No hay origins configurados.' : 'Algún origin no usa HTTPS ni localhost.')),
        ];
    }

    private function secureWebAuthnOrigin(string $origin): bool
    {
        $parts = parse_url($origin);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        return $scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
    }

    private function phpChecks(): array
    {
        $checks = [];
        $versionStatus = version_compare(PHP_VERSION, '8.0.0', '<') ? 'error' : (version_compare(PHP_VERSION, '8.2.0', '<') ? 'warning' : 'ok');
        $checks[] = $this->check('php_version', 'PHP ' . PHP_VERSION, $versionStatus, $versionStatus === 'ok' ? 'Versión compatible y recomendada.' : ($versionStatus === 'warning' ? 'Compatible; se recomienda PHP 8.2 o superior.' : 'Se requiere PHP 8.0 o superior.'));

        $required = ['PDO', 'pdo_mysql', 'fileinfo', 'openssl', 'json', 'mbstring', 'iconv', 'session'];
        foreach ($required as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = $this->check('php_ext_' . strtolower($extension), $extension, $loaded ? 'ok' : 'error', $loaded ? 'Extensión disponible.' : 'Extensión requerida no disponible.');
        }

        foreach (['gd' => 'Optimización automática de imágenes', 'zip' => 'Backups comprimidos'] as $extension => $purpose) {
            $loaded = extension_loaded($extension);
            $checks[] = $this->check('php_ext_' . $extension, $extension, $loaded ? 'ok' : 'warning', $loaded ? $purpose . ' disponible.' : $purpose . ' no estará disponible.');
        }
        $curl = extension_loaded('curl');
        $checks[] = $this->check('php_ext_curl', 'curl', $curl ? 'ok' : 'warning', $curl ? 'Validación remota de Dropbox disponible.' : 'Dropbox no podrá validarse; el audio local seguirá funcionando.');

        $remoteAccess = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        $checks[] = $this->check('php_remote_access', 'allow_url_fopen', $remoteAccess ? 'ok' : 'warning', $remoteAccess ? 'Disponible para validar Turnstile.' : 'Turnstile no podrá validarse con el método actual.');
        return $checks;
    }

    private function environmentChecks(): array
    {
        $environment = strtolower((string) ($this->config['app_env'] ?? 'production'));
        $known = ['production', 'development', 'local', 'dev'];
        $environmentStatus = in_array($environment, $known, true) ? 'ok' : 'warning';
        $https = $this->isHttps();
        $production = $environment === 'production';

        return [
            $this->check('app_environment', 'APP_ENV', $environmentStatus, 'Entorno actual: ' . ($environment ?: 'no definido') . '.'),
            $this->check('https', 'HTTPS', $https ? 'ok' : ($production ? 'error' : 'warning'), $https ? 'La solicitud actual usa HTTPS.' : ($production ? 'Producción está sirviendo sin HTTPS.' : 'No activo en esta sesión local.')),
            $this->check('production_mode', 'Modo de producción', $production ? 'ok' : 'warning', $production ? 'Los detalles técnicos están ocultos al público.' : 'El CMS está en modo de desarrollo/local.'),
        ];
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    private function check(string $id, string $label, string $status, string $message, ?string $detail = null): array
    {
        return compact('id', 'label', 'status', 'message', 'detail');
    }
}
