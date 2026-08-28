<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Services\PodcastAudioService;

final class PodcastController extends Controller
{
    public function index(): void
    {
        $pdo = Database::connect($this->config);
        $podcasts = (new Podcast($pdo))->all(true);
        if (count($podcasts) === 1) {
            $this->renderPodcast($podcasts[0]);
            return;
        }
        $this->view('public/podcasts', [
            'title' => 'Podcast',
            'description' => 'Podcasts de ' . $this->config['site']['name'] . '.',
            'podcasts' => $podcasts,
            'canonical' => $this->config['app_url'] . '/podcast',
        ], 'site');
    }

    public function show(array $params): void
    {
        $podcast = (new Podcast(Database::connect($this->config)))->findActiveBySlug((string) $params['slug']);
        if (!$podcast) { $this->notFound(); return; }
        $this->renderPodcast($podcast);
    }

    public function episode(array $params): void
    {
        $episode = (new PodcastEpisode(Database::connect($this->config)))->findPublishedBySlugs((string) $params['slug'], (string) $params['episode']);
        if (!$episode) { $this->notFound(); return; }
        $image = $episode['image_url'] ?: $episode['podcast_cover_image'];
        $episode['audio'] = (new PodcastAudioService($this->config))->resolved($episode);
        $path = '/podcast/' . rawurlencode($episode['podcast_slug']) . '/' . rawurlencode($episode['slug']);
        $this->view('public/podcast-episode', [
            'title' => $episode['title'],
            'description' => $episode['summary'],
            'episode' => $episode,
            'canonical' => $this->config['app_url'] . $path,
            'socialImage' => $this->absolute((string) $image),
            'ogType' => 'article',
            'podcastFeedUrl' => $this->config['app_url'] . '/podcast/' . $episode['podcast_slug'] . '/feed.xml',
            'schemaJson' => [
                '@context' => 'https://schema.org', '@type' => 'PodcastEpisode',
                'name' => $episode['title'], 'description' => $episode['summary'],
                'datePublished' => $episode['published_at'], 'url' => $this->config['app_url'] . $path,
                'associatedMedia' => ['@type' => 'MediaObject', 'contentUrl' => $this->absolute($episode['audio']['url']), 'encodingFormat' => $episode['audio']['mime_type']],
                'partOfSeries' => ['@type' => 'PodcastSeries', 'name' => $episode['podcast_name'], 'url' => $this->config['app_url'] . '/podcast/' . $episode['podcast_slug']],
            ],
        ], 'site');
    }

    public function feed(array $params = []): void
    {
        $pdo = Database::connect($this->config);
        $podcasts = new Podcast($pdo);
        $podcast = !empty($params['slug']) ? $podcasts->findActiveBySlug((string) $params['slug']) : $podcasts->firstActive();
        if (!$podcast) { http_response_code(404); header('Content-Type: application/rss+xml; charset=utf-8'); echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Podcast no encontrado</title></channel></rss>'; return; }
        $episodes = (new PodcastEpisode($pdo))->allForFeed((int) $podcast['id']);
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $this->rss($podcast, $episodes);
    }

    private function renderPodcast(array $podcast): void
    {
        $page = Paginator::page($_GET['page'] ?? 1);
        $result = (new PodcastEpisode(Database::connect($this->config)))->paginatePublic((int) $podcast['id'], $page, (int) $podcast['episodes_per_page']);
        $audioService = new PodcastAudioService($this->config);
        foreach ($result['items'] as &$episode) $episode['audio'] = $audioService->resolved($episode);
        unset($episode);
        $page = $result['pagination']['page'];
        $path = '/podcast/' . $podcast['slug'];
        $canonical = $this->config['app_url'] . $path . ($page > 1 ? '?page=' . $page : '');
        $this->view('public/podcast', [
            'title' => $podcast['name'] . ($page > 1 ? ' — Página ' . $page : ''),
            'description' => $podcast['short_description'],
            'podcast' => $podcast,
            'episodes' => $result['items'],
            'pagination' => $result['pagination'],
            'canonical' => $canonical,
            'socialImage' => $this->absolute($podcast['cover_image']),
            'podcastFeedUrl' => $this->config['app_url'] . $path . '/feed.xml',
            'schemaJson' => ['@context' => 'https://schema.org', '@type' => 'PodcastSeries', 'name' => $podcast['name'], 'description' => $podcast['short_description'], 'url' => $this->config['app_url'] . $path, 'image' => $this->absolute($podcast['cover_image']), 'author' => ['@type' => 'Person', 'name' => $podcast['author']]],
        ], 'site');
    }

    private function rss(array $podcast, array $episodes): string
    {
        $site = $this->config['app_url'] . '/podcast/' . $podcast['slug'];
        $last = $episodes[0]['published_at'] ?? date('Y-m-d H:i:s');
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/">', '<channel>'];
        $add = function (string $tag, mixed $value) use (&$lines): void { if ($value !== null && $value !== '') $lines[] = '<'.$tag.'>'.$this->xml((string) $value).'</'.$tag.'>'; };
        $add('title', $podcast['name']); $add('link', $podcast['website_url'] ?: $site); $add('description', $podcast['short_description']); $add('language', $podcast['language']); $add('copyright', $podcast['copyright']);
        $add('lastBuildDate', date(DATE_RSS, strtotime($last))); $add('pubDate', date(DATE_RSS, strtotime($last))); $add('itunes:author', $podcast['author']); $add('itunes:summary', $podcast['description']);
        $lines[] = '<itunes:owner><itunes:name>'.$this->xml($podcast['owner_name']).'</itunes:name><itunes:email>'.$this->xml($podcast['owner_email']).'</itunes:email></itunes:owner>';
        $lines[] = '<itunes:image href="'.$this->xml($this->absolute($podcast['cover_image'])).'" />';
        $category = '<itunes:category text="'.$this->xml($podcast['category_primary']).'"';
        $category .= $podcast['category_secondary'] ? '><itunes:category text="'.$this->xml($podcast['category_secondary']).'" /></itunes:category>' : ' />';
        $lines[] = $category; $add('itunes:explicit', $podcast['explicit'] ? 'true' : 'false');
        foreach ($episodes as $episode) {
            $audio = (new PodcastAudioService($this->config))->resolved($episode);
            $url = $this->absolute($audio['url']);
            if ($url === '' || $audio['file_size'] < 1 || $audio['mime_type'] === '') continue;
            $episodeLink = $site . '/' . rawurlencode($episode['slug']);
            $lines[] = '<item>';
            $add('title', $episode['title']); $add('description', $episode['summary']);
            $lines[] = '<content:encoded>'.$this->cdata(markdownish($episode['show_notes'])).'</content:encoded>';
            $add('link', $episodeLink); $lines[] = '<guid isPermaLink="false">'.$this->xml($episode['guid']).'</guid>'; $add('pubDate', date(DATE_RSS, strtotime($episode['published_at'])));
            $lines[] = '<enclosure url="'.$this->xml($url).'" length="'.$audio['file_size'].'" type="'.$this->xml($audio['mime_type']).'" />';
            $add('itunes:duration', $episode['duration']); $add('itunes:episode', $episode['episode_number']); $add('itunes:season', $episode['season_number']); $add('itunes:episodeType', $episode['episode_type']); $add('itunes:explicit', $episode['explicit'] ? 'true' : 'false');
            $image = $episode['image_url'] ?: $podcast['cover_image']; if ($image) $lines[] = '<itunes:image href="'.$this->xml($this->absolute($image)).'" />';
            $lines[] = '</item>';
        }
        $lines[] = '</channel></rss>';
        return implode("\n", $lines);
    }

    private function notFound(): void { http_response_code(404); (new SiteController($this->config))->notFound(); }
    private function absolute(string $url): string { if ($url === '') return ''; return str_starts_with($url, 'http') ? $url : $this->config['app_url'] . '/' . ltrim($url, '/'); }
    private function xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function cdata(string $value): string { return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>'; }
}
