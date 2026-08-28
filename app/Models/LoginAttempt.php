<?php

namespace App\Models;

use PDO;

final class LoginAttempt
{
    private const MAX_ATTEMPTS = 8;
    private const WINDOW_SECONDS = 900;

    public function __construct(private PDO $pdo)
    {
    }

    public function tooMany(string $ip, string $email): bool
    {
        $this->cleanup();
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempted_at >= ? AND (ip_address = ? OR email = ?)');
        $stmt->execute([$cutoff, $ip, $email]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function record(string $ip, string $email, bool $success): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (ip_address, email, success, attempted_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$ip, $email, $success ? 1 : 0]);
        if ($success) {
            $this->clear($ip, $email);
        }
    }

    private function clear(string $ip, string $email): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ? OR email = ?');
        $stmt->execute([$ip, $email]);
    }

    private function cleanup(): void
    {
        $this->pdo->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }
}
