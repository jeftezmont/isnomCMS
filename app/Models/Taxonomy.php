<?php

namespace App\Models;

use PDO;

final class Taxonomy
{
    public function __construct(private PDO $pdo)
    {
    }

    public function categories(): array
    {
        return $this->pdo->query("SELECT c.*, COUNT(p.id) post_count FROM categories c LEFT JOIN posts p ON p.category_id = c.id GROUP BY c.id ORDER BY FIELD(c.slug, 'tecnologia', 'teologia', 'desarrollo', 'diseno', 'musica', 'arte'), c.name")->fetchAll();
    }

    public function tags(): array
    {
        return $this->pdo->query('SELECT t.*, COUNT(pt.post_id) post_count FROM tags t LEFT JOIN post_tags pt ON pt.tag_id = t.id GROUP BY t.id ORDER BY t.name')->fetchAll();
    }

    public function saveCategory(array $data): void
    {
        $slug = $data['slug'] ?: $this->slug($data['name']);
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?');
            $stmt->execute([$data['name'], $slug, $data['description'], (int) $data['id']]);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$data['name'], $slug, $data['description']]);
    }

    public function saveTag(array $data): void
    {
        $slug = $data['slug'] ?: $this->slug($data['name']);
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
            $stmt->execute([$data['name'], $slug, (int) $data['id']]);
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$data['name'], $slug]);
    }

    public function deleteCategory(int $id): void
    {
        $this->pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    }

    public function deleteTag(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$id]);
    }

    private function slug(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $value)), '-'));
    }
}
