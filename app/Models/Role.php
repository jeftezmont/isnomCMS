<?php

namespace App\Models;

use PDO;

final class Role
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $roles = $this->pdo->query('SELECT id, name, slug FROM roles ORDER BY FIELD(slug, "super_admin", "admin", "editor", "author")')->fetchAll();
        $stmt = $this->pdo->prepare('SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? ORDER BY p.slug');
        foreach ($roles as &$role) {
            $stmt->execute([(int) $role['id']]);
            $role['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($role);
        return $roles;
    }

    public function permissions(): array
    {
        return $this->pdo->query('SELECT name, slug FROM permissions ORDER BY slug')->fetchAll();
    }

    public function replacePermissions(string $roleSlug, array $slugs): void
    {
        if ($roleSlug === 'super_admin') throw new \InvalidArgumentException('Super Admin conserva siempre acceso completo.');
        $role = $this->pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $role->execute([$roleSlug]);
        $roleId = (int) $role->fetchColumn();
        if (!$roleId) throw new \InvalidArgumentException('El rol no existe.');

        $available = array_flip(array_map('strval', $this->pdo->query('SELECT slug FROM permissions')->fetchAll(PDO::FETCH_COLUMN)));
        $slugs = array_values(array_unique(array_filter(array_map('strval', $slugs), fn(string $slug): bool => isset($available[$slug]))));
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
            $insert = $this->pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE slug = ?');
            foreach ($slugs as $slug) $insert->execute([$roleId, $slug]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }
}
