<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Post;
use App\Models\Podcast;
use App\Models\PodcastEpisode;

final class SeoController extends Controller
{
    public function sitemap(): void
    {
        $pdo = Database::connect($this->config);
        $posts = (new Post($pdo))->published();
        $podcasts = (new Podcast($pdo))->all(true);
        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (['/', '/blog'] as $path) {
            echo '<url><loc>' . e($this->config['app_url'] . $path) . "</loc></url>\n";
        }
        foreach ($posts as $post) {
            echo '<url><loc>' . e($this->config['app_url'] . '/blog/' . $post['slug']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($post['updated_at']))) . "</lastmod></url>\n";
        }
        foreach ($podcasts as $podcast) {
            $podcastUrl = $this->config['app_url'] . '/podcast/' . $podcast['slug'];
            echo '<url><loc>' . e($podcastUrl) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($podcast['updated_at']))) . "</lastmod></url>\n";
            foreach ((new PodcastEpisode($pdo))->allForFeed((int) $podcast['id']) as $episode) {
                echo '<url><loc>' . e($podcastUrl . '/' . $episode['slug']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($episode['updated_at']))) . "</lastmod></url>\n";
            }
        }
        echo "</urlset>";
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: {$this->config['app_url']}/sitemap.xml\n";
    }
}
