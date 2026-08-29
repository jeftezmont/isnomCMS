<?php

namespace App\Services;

use PDO;

final class RoleSeeder
{
    private const ROLES = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'editor' => 'Editor',
        'author' => 'Autor',
    ];

    private const PERMISSIONS = [
        'dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'posts.edit_own',
        'posts.publish', 'posts.delete', 'podcast.view', 'podcast.create', 'podcast.edit',
        'podcast.publish', 'podcast.delete', 'media.view', 'media.create', 'media.delete',
        'taxonomy.manage', 'users.view', 'users.create', 'users.edit', 'users.delete',
        'users.assign_roles', 'roles.manage', 'settings.view', 'settings.edit',
        'navigation.edit', 'security.view', 'tools.health', 'tools.deploy',
        'tools.setup', 'tools.backups',
    ];

    private const GRANTS = [
        'admin' => [
            'dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'posts.edit_own',
            'posts.publish', 'posts.delete', 'podcast.view', 'podcast.create', 'podcast.edit',
            'podcast.publish', 'podcast.delete', 'media.view', 'media.create', 'media.delete',
            'taxonomy.manage', 'users.view', 'users.create', 'users.edit', 'users.delete',
            'settings.view', 'settings.edit', 'navigation.edit', 'security.view',
            'tools.health', 'tools.deploy', 'tools.backups',
        ],
        'editor' => [
            'dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'posts.edit_own',
            'posts.publish', 'posts.delete', 'podcast.view', 'podcast.create', 'podcast.edit',
            'podcast.publish', 'podcast.delete', 'media.view', 'media.create', 'media.delete',
            'taxonomy.manage', 'security.view',
        ],
        'author' => ['dashboard.view', 'posts.view', 'posts.create', 'posts.edit_own', 'media.view', 'media.create', 'security.view'],
    ];

    public function seed(PDO $pdo): void
    {
        $pdo->beginTransaction();
        try {
            $role = $pdo->prepare('INSERT INTO roles (name, slug, is_system, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()');
            foreach (self::ROLES as $slug => $name) $role->execute([$name, $slug]);

            $permission = $pdo->prepare('INSERT INTO permissions (name, slug, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name)');
            foreach (self::PERMISSIONS as $slug) $permission->execute([$this->label($slug), $slug]);

            $roleIds = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
            $permissionIds = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
            $grant = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach (self::PERMISSIONS as $slug) $grant->execute([(int) $roleIds['super_admin'], (int) $permissionIds[$slug]]);
            foreach (self::GRANTS as $roleSlug => $permissions) {
                foreach ($permissions as $slug) $grant->execute([(int) $roleIds[$roleSlug], (int) $permissionIds[$slug]]);
            }

            $pdo->exec('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at, assigned_by) SELECT u.id, r.id, NOW(), NULL FROM users u CROSS JOIN roles r WHERE r.slug = "super_admin"');
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private function label(string $slug): string
    {
        return ucfirst(str_replace(['.', '_'], [' · ', ' '], $slug));
    }
}
