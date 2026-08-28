<?php

namespace App\Models;

use PDO;

final class Podcast
{
    public function __construct(private PDO $pdo) {}

    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM podcasts' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY created_at ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM podcasts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findActiveBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM podcasts WHERE slug = ? AND active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function firstActive(): ?array
    {
        return $this->pdo->query('SELECT * FROM podcasts WHERE active = 1 ORDER BY created_at ASC LIMIT 1')->fetch() ?: null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $fields = ['name','slug','short_description','description','author','owner_name','owner_email','language','category_primary','category_secondary','copyright','website_url','cover_image','explicit','active','apple_podcasts_url','spotify_url','episodes_per_page'];
        $values = array_map(fn(string $field) => $data[$field] ?? null, $fields);
        if ($id) {
            $sets = implode(', ', array_map(fn(string $field) => "{$field} = ?", $fields));
            $values[] = $id;
            $this->pdo->prepare("UPDATE podcasts SET {$sets}, updated_at = NOW() WHERE id = ?")->execute($values);
            return $id;
        }
        $columns = implode(', ', array_merge($fields, ['created_at','updated_at']));
        $marks = implode(', ', array_fill(0, count($fields) + 2, '?'));
        array_push($values, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
        $this->pdo->prepare("INSERT INTO podcasts ({$columns}) VALUES ({$marks})")->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM podcasts WHERE id = ?')->execute([$id]);
    }
}
