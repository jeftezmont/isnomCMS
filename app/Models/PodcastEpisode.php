<?php

namespace App\Models;

use App\Core\Paginator;
use PDO;

final class PodcastEpisode
{
    public function __construct(private PDO $pdo) {}

    public function paginatePublic(int $podcastId, int $page, int $perPage): array
    {
        $where = "podcast_id = ? AND status IN ('published','scheduled') AND published_at <= NOW()";
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM podcast_episodes WHERE {$where}");
        $count->execute([$podcastId]);
        $pagination = Paginator::result($page, $perPage, (int) $count->fetchColumn());
        $stmt = $this->pdo->prepare("SELECT * FROM podcast_episodes WHERE {$where} ORDER BY published_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
        $stmt->execute([$podcastId]);
        return ['items' => $stmt->fetchAll(), 'pagination' => $pagination];
    }

    public function paginateAdmin(int $page, int $perPage = 20): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM podcast_episodes')->fetchColumn();
        $pagination = Paginator::result($page, $perPage, $total);
        $sql = "SELECT e.*, p.name podcast_name, p.slug podcast_slug FROM podcast_episodes e JOIN podcasts p ON p.id = e.podcast_id ORDER BY COALESCE(e.published_at,e.created_at) DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
        return ['items' => $this->pdo->query($sql)->fetchAll(), 'pagination' => $pagination];
    }

    public function allForFeed(int $podcastId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM podcast_episodes WHERE podcast_id = ? AND status IN ('published','scheduled') AND published_at <= NOW() ORDER BY published_at DESC");
        $stmt->execute([$podcastId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM podcast_episodes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findPublishedBySlugs(string $podcastSlug, string $episodeSlug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT e.*, p.name podcast_name, p.slug podcast_slug, p.short_description podcast_short_description, p.description podcast_description, p.author podcast_author, p.cover_image podcast_cover_image, p.website_url podcast_website_url FROM podcast_episodes e JOIN podcasts p ON p.id = e.podcast_id WHERE p.slug = ? AND e.slug = ? AND p.active = 1 AND e.status IN ('published','scheduled') AND e.published_at <= NOW() LIMIT 1");
        $stmt->execute([$podcastSlug, $episodeSlug]);
        return $stmt->fetch() ?: null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $fields = ['podcast_id','title','slug','summary','show_notes','audio_source','audio_local_path','audio_original_url','audio_url','audio_mime_type','audio_file_size','duration','image_url','author','episode_number','season_number','episode_type','explicit','status','published_at'];
        $values = array_map(fn(string $field) => $data[$field] ?? null, $fields);
        if ($id) {
            $sets = implode(', ', array_map(fn(string $field) => "{$field} = ?", $fields));
            $values[] = $id;
            $this->pdo->prepare("UPDATE podcast_episodes SET {$sets}, updated_at = NOW() WHERE id = ?")->execute($values);
            return $id;
        }
        $guid = self::uuid();
        $columns = implode(', ', array_merge($fields, ['guid','created_at','updated_at']));
        $marks = implode(', ', array_fill(0, count($fields) + 3, '?'));
        array_push($values, $guid, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
        $this->pdo->prepare("INSERT INTO podcast_episodes ({$columns}) VALUES ({$marks})")->execute($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM podcast_episodes WHERE id = ?')->execute([$id]);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
