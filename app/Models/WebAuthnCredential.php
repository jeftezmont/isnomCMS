<?php

namespace App\Models;

use PDO;

final class WebAuthnCredential
{
    public function __construct(private PDO $pdo)
    {
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webauthn_credentials WHERE user_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, u.name AS user_name, u.email AS user_email FROM webauthn_credentials c INNER JOIN users u ON u.id = c.user_id WHERE c.credential_id = ? LIMIT 1');
        $stmt->execute([$credentialId]);
        $credential = $stmt->fetch();
        return $credential ?: null;
    }

    public function create(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO webauthn_credentials (user_id, credential_id, public_key, counter, transports, label, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $userId,
            $data['credential_id'],
            $data['public_key'],
            (int) $data['counter'],
            $data['transports'] ?? '[]',
            trim($data['label'] ?? '') ?: 'Passkey',
        ]);
    }

    public function markUsed(int $id, int $counter): void
    {
        $stmt = $this->pdo->prepare('UPDATE webauthn_credentials SET counter = ?, last_used_at = NOW() WHERE id = ?');
        $stmt->execute([$counter, $id]);
    }

    public function deleteForUser(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
    }
}
