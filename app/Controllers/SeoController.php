<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\FileCache;
use App\Core\XmlResponse;
use App\Models\Post;
use App\Models\Podcast;
use App\Models\PodcastEpisode;

final class SeoController extends Controller
{
    public function sitemap(): void
    {
        $payload = FileCache::fromConfig($this->config)->remember('public.sitemap', function (): array {
            $pdo = Database::connect($this->config);
            $posts = (new Post($pdo))->allPublic();
            $podcasts = (new Podcast($pdo))->all(true);
            $urls = [['loc' => $this->config['app_url'] . '/'], ['loc' => $this->config['app_url'] . '/blog'], ['loc' => $this->config['app_url'] . '/podcast']];
            foreach ($posts as $post) $urls[] = ['loc' => $this->config['app_url'] . '/blog/' . rawurlencode($post['slug']), 'lastmod' => $post['updated_at']];
            foreach ($podcasts as $podcast) {
                $base = $this->config['app_url'] . '/podcast/' . rawurlencode($podcast['slug']);
                $urls[] = ['loc' => $base, 'lastmod' => $podcast['updated_at']];
                foreach ((new PodcastEpisode($pdo))->allForFeed((int) $podcast['id']) as $episode) $urls[] = ['loc' => $base . '/' . rawurlencode($episode['slug']), 'lastmod' => $episode['updated_at']];
            }
            $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
            $modified = 0;
            foreach ($urls as $url) {
                $line = '<url><loc>' . $this->xml($url['loc']) . '</loc>';
                if (!empty($url['lastmod'])) {
                    $time = strtotime($url['lastmod']) ?: 0;
                    if ($time) { $line .= '<lastmod>' . gmdate('Y-m-d', $time) . '</lastmod>'; $modified = max($modified, $time); }
                }
                $lines[] = $line . '</url>';
            }
            $lines[] = '</urlset>';
            return ['body' => implode("\n", $lines), 'modified' => $modified ?: time()];
        });
        XmlResponse::send($payload['body'], 'application/xml', (int) $payload['modified']);
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: {$this->config['app_url']}/sitemap.xml\n";
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
