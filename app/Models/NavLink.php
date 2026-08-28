<?php

namespace App\Models;

use PDO;

final class NavLink
{
    public function __construct(private PDO $pdo)
    {
    }

    public function forBlog(): array
    {
        try {
            $items = $this->pdo->query('SELECT * FROM nav_links WHERE menu = "blog" ORDER BY sort_order, id')->fetchAll();
        } catch (\Throwable) {
            return [];
        }
        return $items ?: [];
    }

    public function replaceBlog(array $labels, array $urls): void
    {
        $this->pdo->beginTransaction();
        $this->pdo->prepare('DELETE FROM nav_links WHERE menu = "blog"')->execute();
        $stmt = $this->pdo->prepare('INSERT INTO nav_links (menu, label, url, sort_order, created_at, updated_at) VALUES ("blog", ?, ?, ?, NOW(), NOW())');
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $url = trim((string) ($urls[$index] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $stmt->execute([$label, $url, $index + 1]);
        }
        $this->pdo->commit();
    }
}
