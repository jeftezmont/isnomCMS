<?php

namespace App\Models;

use PDO;

final class TwoFactor
{
    public function __construct(private PDO $pdo) {}

    public function find(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_two_factor WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function enabled(int $userId): bool
    {
        $row = $this->find($userId);
        return $row && $row['enabled_at'] !== null;
    }

    public function savePending(int $userId, string $encryptedSecret): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO user_two_factor (user_id, encrypted_secret, enabled_at, last_used_step, created_at, updated_at) VALUES (?, ?, NULL, NULL, NOW(), NOW()) ON DUPLICATE KEY UPDATE encrypted_secret = VALUES(encrypted_secret), enabled_at = NULL, last_used_step = NULL, updated_at = NOW()');
        $stmt->execute([$userId, $encryptedSecret]);
    }

    public function enable(int $userId, int $step): void
    {
        $stmt = $this->pdo->prepare('UPDATE user_two_factor SET enabled_at = NOW(), last_used_step = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$step, $userId]);
    }

    public function acceptStep(int $userId, int $step): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_two_factor SET last_used_step = ?, updated_at = NOW() WHERE user_id = ? AND enabled_at IS NOT NULL AND (last_used_step IS NULL OR last_used_step < ?)');
        $stmt->execute([$step, $userId, $step]);
        return $stmt->rowCount() === 1;
    }

    public function disable(int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM two_factor_recovery_codes WHERE user_id = ?')->execute([$userId]);
            $this->pdo->prepare('DELETE FROM user_two_factor WHERE user_id = ?')->execute([$userId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function replaceRecoveryCodes(int $userId, int $count = 10): array
    {
        $codes = [];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM two_factor_recovery_codes WHERE user_id = ?')->execute([$userId]);
            $stmt = $this->pdo->prepare('INSERT INTO two_factor_recovery_codes (user_id, code_hash, used_at, created_at) VALUES (?, ?, NULL, NOW())');
            for ($i = 0; $i < $count; $i++) {
                $raw = strtoupper(bin2hex(random_bytes(5)));
                $code = substr($raw, 0, 5) . '-' . substr($raw, 5);
                $stmt->execute([$userId, password_hash($this->normalizeCode($code), PASSWORD_DEFAULT)]);
                $codes[] = $code;
            }
            $this->pdo->commit();
            return $codes;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function remainingCodes(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM two_factor_recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function consumeRecoveryCode(int $userId, string $code): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, code_hash FROM two_factor_recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        $normalized = $this->normalizeCode($code);
        foreach ($stmt->fetchAll() as $row) {
            if (!password_verify($normalized, $row['code_hash'])) continue;
            $consume = $this->pdo->prepare('UPDATE two_factor_recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $consume->execute([$row['id']]);
            return $consume->rowCount() === 1;
        }
        return false;
    }

    public function tooManyAttempts(int $userId, string $ip): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM two_factor_attempts WHERE user_id = ? AND ip_address = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
        $stmt->execute([$userId, $ip]);
        return (int) $stmt->fetchColumn() >= 8;
    }

    public function recordAttempt(int $userId, string $ip, bool $success): void
    {
        $this->pdo->prepare('INSERT INTO two_factor_attempts (user_id, ip_address, success, attempted_at) VALUES (?, ?, ?, NOW())')->execute([$userId, $ip, $success ? 1 : 0]);
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)) ?? '');
    }
}
