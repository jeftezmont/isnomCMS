<?php

namespace App\Models;

use PDO;

final class Post
{
    public function __construct(private PDO $pdo)
    {
    }

    public function published(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name category_name, c.slug category_slug FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = "published" AND p.published_at <= NOW()';
        $params = [];
        if (!empty($filters['category'])) {
            $sql .= ' AND c.slug = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term);
        }
        $sql .= ' ORDER BY p.published_at DESC LIMIT 50';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function allPublic(): array
    {
        return $this->pdo->query('SELECT p.*, c.name category_name, c.slug category_slug, u.name author_name FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id WHERE p.status = "published" AND p.published_at <= NOW() ORDER BY p.published_at DESC, p.id DESC')->fetchAll();
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, c.name category_name, c.slug category_slug, u.name author_name FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id WHERE p.slug = ? AND p.status = "published" AND p.published_at <= NOW() LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findVisibleBySlug(string $slug, string $previewToken = ''): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT p.*, c.name category_name, c.slug category_slug, u.name author_name FROM posts p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.author_id WHERE p.slug = ? AND ((p.status = "published" AND p.published_at <= NOW()) OR (p.preview_token IS NOT NULL AND p.preview_token = ?)) LIMIT 1');
            $stmt->execute([$slug, $previewToken]);
            return $stmt->fetch() ?: null;
        } catch (\Throwable) {
            return $this->findPublishedBySlug($slug);
        }
    }

    public function related(int $postId, ?int $categoryId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, c.name category_name FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id != ? AND p.status = "published" AND p.published_at <= NOW() AND (? IS NULL OR p.category_id = ?) ORDER BY p.published_at DESC LIMIT 3');
        $stmt->execute([$postId, $categoryId, $categoryId]);
        return $stmt->fetchAll();
    }

    public function adjacent(string $publishedAt): array
    {
        $prev = $this->pdo->prepare('SELECT title, slug FROM posts WHERE status = "published" AND published_at <= NOW() AND published_at < ? ORDER BY published_at DESC LIMIT 1');
        $next = $this->pdo->prepare('SELECT title, slug FROM posts WHERE status = "published" AND published_at <= NOW() AND published_at > ? ORDER BY published_at ASC LIMIT 1');
        $prev->execute([$publishedAt]);
        $next->execute([$publishedAt]);
        return ['prev' => $prev->fetch() ?: null, 'next' => $next->fetch() ?: null];
    }

    public function tagsFor(int $postId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.* FROM tags t INNER JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name');
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    }

    public function allAdmin(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name category_name FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND p.title LIKE ?';
            $params[] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['author_id'])) {
            $sql .= ' AND p.author_id = ?';
            $params[] = (int) $filters['author_id'];
        }
        $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function save(array $data, int $authorId, ?int $id = null): int
    {
        if (empty($data['preview_token'])) {
            $data['preview_token'] = bin2hex(random_bytes(16));
        }
        $fields = ['title', 'slug', 'excerpt', 'content', 'featured_image', 'category_id', 'status', 'published_at', 'seo_title', 'seo_description', 'og_image', 'preview_token'];
        if ($id) {
            $sets = implode(', ', array_map(fn($field) => "{$field} = ?", $fields));
            $values = array_map(fn($field) => $data[$field] ?: null, $fields);
            $values[] = $id;
            $stmt = $this->pdo->prepare("UPDATE posts SET {$sets}, updated_at = NOW() WHERE id = ?");
            $stmt->execute($values);
            return $id;
        }
        $columns = implode(', ', array_merge($fields, ['author_id', 'created_at', 'updated_at']));
        $marks = rtrim(str_repeat('?, ', count($fields) + 3), ', ');
        $values = array_map(fn($field) => $data[$field] ?: null, $fields);
        array_push($values, $authorId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
        $stmt = $this->pdo->prepare("INSERT INTO posts ({$columns}) VALUES ({$marks})");
        $stmt->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function syncTags(int $postId, string $tagList): void
    {
        $names = array_filter(array_unique(array_map('trim', explode(',', $tagList))));
        $this->pdo->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
        foreach ($names as $name) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)), '-'));
            $stmt = $this->pdo->prepare('INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE name = VALUES(name)');
            $stmt->execute([$name, $slug]);
            $tagId = (int) $this->pdo->lastInsertId();
            if (!$tagId) {
                $find = $this->pdo->prepare('SELECT id FROM tags WHERE slug = ?');
                $find->execute([$slug]);
                $tagId = (int) $find->fetchColumn();
            }
            $this->pdo->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)')->execute([$postId, $tagId]);
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    public function stats(?int $authorId = null): array
    {
        $where = $authorId ? ' WHERE author_id = ' . (int) $authorId : '';
        $and = $authorId ? ' AND author_id = ' . (int) $authorId : '';
        return [
            'total' => (int) $this->pdo->query('SELECT COUNT(*) FROM posts' . $where)->fetchColumn(),
            'published' => (int) $this->pdo->query('SELECT COUNT(*) FROM posts WHERE status = "published"' . $and)->fetchColumn(),
            'private' => (int) $this->pdo->query('SELECT COUNT(*) FROM posts WHERE status = "private"' . $and)->fetchColumn(),
            'drafts' => (int) $this->pdo->query('SELECT COUNT(*) FROM posts WHERE status = "draft"' . $and)->fetchColumn(),
            'categories' => (int) $this->pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        ];
    }
}
