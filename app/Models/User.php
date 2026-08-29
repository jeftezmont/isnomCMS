<?php

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT u.id, u.name, u.email, u.created_at, u.updated_at, r.slug role_slug, r.name role_name FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id ORDER BY u.created_at DESC, u.id DESC')
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findWithPassword(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function byLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? OR name = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        return $stmt->fetch() ?: null;
    }

    public function verifyPassword(int $id, string $password): bool
    {
        $user = $this->findWithPassword($id);
        return $user && password_verify($password, $user['password_hash']);
    }

    public function create(array $data, string $roleSlug = 'author', ?int $assignedBy = null): int
    {
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new \InvalidArgumentException('Completa nombre, correo válido y una contraseña de mínimo 8 caracteres.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $this->pdo->lastInsertId();
            $this->assignRole($id, $this->count() === 1 ? 'super_admin' : $roleSlug, $assignedBy);
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function assignRole(int $userId, string $roleSlug, ?int $assignedBy): void
    {
        $role = $this->pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $role->execute([$roleSlug]);
        $roleId = (int) $role->fetchColumn();
        if (!$roleId) throw new \InvalidArgumentException('El rol seleccionado no existe.');
        if ($this->isLastSuperAdmin($userId) && $roleSlug !== 'super_admin') {
            throw new \InvalidArgumentException('No puedes degradar al último Super Admin.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_at, assigned_by) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), assigned_at = NOW(), assigned_by = VALUES(assigned_by)');
        $stmt->execute([$userId, $roleId, $assignedBy]);
    }

    public function roles(bool $includeSuperAdmin = true): array
    {
        $sql = 'SELECT slug, name FROM roles' . ($includeSuperAdmin ? '' : ' WHERE slug <> "super_admin"') . ' ORDER BY FIELD(slug, "super_admin", "admin", "editor", "author")';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function roleFor(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: null;
    }

    public function delete(int $id): void
    {
        if ($this->isLastSuperAdmin($id)) throw new \InvalidArgumentException('No puedes eliminar al último Super Admin.');
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function isLastSuperAdmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() !== 'super_admin') return false;
        return (int) $this->pdo->query('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = "super_admin"')->fetchColumn() <= 1;
    }
}
