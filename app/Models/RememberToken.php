<?php

namespace App\Models;

use PDO;

final class RememberToken
{
    public const COOKIE = 'remember_admin';
    public const DAYS = 30;

    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId): string
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + self::DAYS * 86400);

        $stmt = $this->pdo->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at, last_used_at) VALUES (?, ?, ?, ?, NOW(), NULL)');
        $stmt->execute([$userId, $selector, $hash, $expiresAt]);

        return $selector . ':' . $validator;
    }

    public function consume(string $cookie): ?array
    {
        [$selector, $validator] = array_pad(explode(':', $cookie, 2), 2, '');
        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.name FROM remember_tokens rt INNER JOIN users u ON u.id = rt.user_id WHERE rt.selector = ? AND rt.revoked_at IS NULL LIMIT 1');
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!$row || strtotime($row['expires_at']) < time() || !hash_equals($row['token_hash'], hash('sha256', $validator))) {
            $this->revokeSelector($selector);
            return null;
        }

        $this->revoke((int) $row['id']);
        $newCookie = $this->create((int) $row['user_id']);

        return [
            'user_id' => (int) $row['user_id'],
            'name' => $row['name'],
            'cookie' => $newCookie,
        ];
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function revokeCookie(string $cookie): void
    {
        [$selector] = explode(':', $cookie, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            $this->revokeSelector($selector);
        }
    }

    public function revokeForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
        $stmt->execute([$userId]);
    }

    public function deleteExpired(): void
    {
        $this->pdo->exec('DELETE FROM remember_tokens WHERE expires_at < NOW() OR revoked_at IS NOT NULL');
    }

    private function revokeSelector(string $selector): void
    {
        $stmt = $this->pdo->prepare('UPDATE remember_tokens SET revoked_at = NOW() WHERE selector = ?');
        $stmt->execute([$selector]);
    }
}
