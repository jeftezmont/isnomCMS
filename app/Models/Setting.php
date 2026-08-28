<?php

namespace App\Models;

use PDO;

final class Setting
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        try {
            $rows = $this->pdo->query('SELECT `key`, `value` FROM site_settings')->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        return array_column($rows, 'value', 'key');
    }

    public function saveMany(array $values): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO site_settings (`key`, `value`, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()');
        foreach ($values as $key => $value) {
            $stmt->execute([$key, (string) $value]);
        }
    }
}
