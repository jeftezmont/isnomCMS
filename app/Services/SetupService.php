<?php

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use PDO;

final class SetupService
{
    public function __construct(private array $config)
    {
    }

    public function state(): array
    {
        try {
            $pdo = Database::connect($this->config);
        } catch (\Throwable) {
            return [
                'connected' => false,
                'schema' => null,
                'admin_count' => null,
                'ready' => false,
                'message' => 'No fue posible conectar con MySQL. Revisa las variables DB_* del entorno.',
            ];
        }

        try {
            $schema = $this->schema()->inspect($pdo);
        } catch (\Throwable) {
            $schema = null;
        }

        $adminCount = null;
        if (!empty($schema['tables']['users']['exists'])) {
            try {
                $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (\Throwable) {
                $adminCount = null;
            }
        }

        return [
            'connected' => true,
            'schema' => $schema,
            'admin_count' => $adminCount,
            'ready' => !empty($schema['complete']) && $adminCount !== null && $adminCount > 0,
            'message' => $schema === null ? 'La conexión funciona, pero no se pudo inspeccionar database.sql.' : null,
        ];
    }

    public function repairSchema(): array
    {
        $pdo = Database::connect($this->config);
        $result = $this->schema()->applySafeUpdates($pdo);
        if (empty($result['errors'])) {
            (new RoleSeeder())->seed($pdo);
        }
        return $result;
    }

    public function createFirstAdmin(array $data): array
    {
        $password = (string) ($data['password'] ?? '');
        $confirmation = (string) ($data['password_confirmation'] ?? '');
        if ($password !== $confirmation) {
            throw new \InvalidArgumentException('Las contraseñas no coinciden.');
        }

        $pdo = Database::connect($this->config);
        $state = $this->schema()->inspect($pdo);
        if (empty($state['tables']['users']['exists'])) {
            throw new \InvalidArgumentException('Primero crea las tablas faltantes.');
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Ya existe un administrador. Crea usuarios adicionales desde el panel de Usuarios.');
        }

        $user = new User($pdo);
        $id = $user->create($data);
        return ['id' => $id, 'name' => trim((string) ($data['name'] ?? ''))];
    }

    public function isProduction(): bool
    {
        return strtolower((string) ($this->config['app_env'] ?? 'production')) === 'production';
    }

    public function setupTokenConfigured(): bool
    {
        return trim((string) ($this->config['setup_token'] ?? '')) !== '';
    }

    public function unlock(string $token): bool
    {
        $expected = trim((string) ($this->config['setup_token'] ?? ''));
        return $expected !== '' && hash_equals($expected, $token);
    }

    private function schema(): DatabaseSchemaService
    {
        return new DatabaseSchemaService(ROOT_PATH . '/database.sql');
    }
}
