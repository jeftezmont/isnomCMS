<?php

namespace App\Models;

use PDO;

final class WebAuthnChallenge
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $type, string $challenge, ?int $userId = null): void
    {
        $this->deleteExpired();
        $stmt = $this->pdo->prepare('INSERT INTO webauthn_challenges (type, user_id, challenge, expires_at, created_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())');
        $stmt->execute([$type, $userId, $challenge]);
    }

    public function consume(string $type, string $challenge, ?int $userId = null): bool
    {
        $sql = 'SELECT id FROM webauthn_challenges WHERE type = ? AND challenge = ? AND consumed_at IS NULL AND expires_at > NOW()';
        $params = [$type, $challenge];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if (!$id) {
            return false;
        }
        $update = $this->pdo->prepare('UPDATE webauthn_challenges SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL');
        $update->execute([(int) $id]);
        return $update->rowCount() === 1;
    }

    private function deleteExpired(): void
    {
        $this->pdo->exec('DELETE FROM webauthn_challenges WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR consumed_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}
