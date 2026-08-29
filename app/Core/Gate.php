<?php

namespace App\Core;

use App\Services\RoleSeeder;
use PDO;

final class Gate
{
    private static array $cache = [];

    public static function allows(array $config, string $permission, ?int $userId = null): bool
    {
        $userId ??= Auth::id();
        if (!$userId) return false;
        $access = self::access($config, $userId);
        return $access['role'] === 'super_admin' || in_array($permission, $access['permissions'], true);
    }

    public static function role(array $config, ?int $userId = null): ?string
    {
        $userId ??= Auth::id();
        return $userId ? self::access($config, $userId)['role'] : null;
    }

    public static function clear(?int $userId = null): void
    {
        if ($userId === null) self::$cache = [];
        else unset(self::$cache[$userId]);
    }

    private static function access(array $config, int $userId): array
    {
        if (isset(self::$cache[$userId])) return self::$cache[$userId];
        try {
            $pdo = Database::connect($config);
            self::ensureSeeded($pdo);
            $stmt = $pdo->prepare('SELECT r.slug role_slug, p.slug permission_slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id LEFT JOIN role_permissions rp ON rp.role_id = r.id LEFT JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ?');
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll();
            return self::$cache[$userId] = [
                'role' => $rows[0]['role_slug'] ?? null,
                'permissions' => array_values(array_filter(array_column($rows, 'permission_slug'))),
            ];
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'authorization');
            return self::$cache[$userId] = ['role' => null, 'permissions' => []];
        }
    }

    private static function ensureSeeded(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        if ($count < 4) (new RoleSeeder())->seed($pdo);
    }
}
