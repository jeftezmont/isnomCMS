<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\FileCache;
use App\Core\XmlResponse;
use App\Models\NavLink;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Taxonomy;

final class BlogController extends Controller
{
    public function index(): void
    {
        try {
            $pdo = Database::connect($this->config);
            $posts = (new Post($pdo))->published([
                'category' => $_GET['category'] ?? '',
                'q' => $_GET['q'] ?? '',
            ]);
            $categories = (new Taxonomy($pdo))->categories();
            $blogNav = (new NavLink($pdo))->forBlog();
            $settings = (new Setting($pdo))->all();
        } catch (\Throwable) {
            $posts = $this->samplePosts($_GET['q'] ?? '', $_GET['category'] ?? '');
            $categories = $this->sampleCategories();
            $blogNav = [];
            $settings = [];
        }
        $this->view('public/blog', [
            'title' => 'Blog',
            'description' => 'Artículos de jefté montenegro sobre teología, desarrollo, diseño, música y arte.',
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $_GET['category'] ?? '',
            'query' => $_GET['q'] ?? '',
            'blogNav' => $blogNav ?: $this->defaultBlogNav(),
            'settings' => $settings,
            'blogFeedUrl' => $this->config['app_url'] . '/blog/feed.xml',
        ], 'site');
    }

    public function show(array $params): void
    {
        try {
            $pdo = Database::connect($this->config);
            $model = new Post($pdo);
            $previewToken = is_string($_GET['preview'] ?? null) ? $_GET['preview'] : '';
            $post = $model->findVisibleBySlug($params['slug'], $previewToken);
            $tags = $post ? $model->tagsFor((int) $post['id']) : [];
            $related = $post ? $model->related((int) $post['id'], $post['category_id'] ? (int) $post['category_id'] : null) : [];
            $adjacent = ($post && !empty($post['published_at'])) ? $model->adjacent($post['published_at']) : ['prev' => null, 'next' => null];
            $blogNav = (new NavLink($pdo))->forBlog();
        } catch (\Throwable) {
            $post = $this->samplePost($params['slug']);
            $tags = [['name' => 'preview'], ['name' => 'editorial']];
            $related = array_slice($this->samplePosts('', ''), 1, 3);
            $adjacent = ['prev' => null, 'next' => null];
            $blogNav = [];
        }
        if (!$post) {
            http_response_code(404);
            (new SiteController($this->config))->notFound();
            return;
        }
        $this->view('public/article', [
            'title' => ($post['seo_title'] ?? '') ?: $post['title'],
            'description' => ($post['seo_description'] ?? '') ?: $post['excerpt'],
            'post' => $post,
            'tags' => $tags,
            'related' => $related,
            'adjacent' => $adjacent,
            'blogNav' => $blogNav ?: $this->defaultBlogNav(),
            'schemaArticle' => true,
            'blogFeedUrl' => $this->config['app_url'] . '/blog/feed.xml',
        ], 'site');
    }

    public function feed(): void
    {
        $payload = FileCache::fromConfig($this->config)->remember('public.blog.rss', function (): array {
            $posts = (new Post(Database::connect($this->config)))->allPublic();
            $site = $this->config['app_url'];
            $last = $posts[0]['updated_at'] ?? null;
            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">',
                '<channel>',
                '<title>' . $this->xml($this->config['site']['name'] . ' — Blog') . '</title>',
                '<link>' . $this->xml($site . '/blog') . '</link>',
                '<description>' . $this->xml($this->config['site']['description']) . '</description>',
                '<language>es-MX</language>',
                '<lastBuildDate>' . date(DATE_RSS, strtotime($last ?: 'now')) . '</lastBuildDate>',
            ];
            foreach ($posts as $post) {
                $link = $site . '/blog/' . rawurlencode($post['slug']);
                $lines[] = '<item>';
                $lines[] = '<title>' . $this->xml($post['title']) . '</title>';
                $lines[] = '<link>' . $this->xml($link) . '</link>';
                $lines[] = '<guid isPermaLink="false">isnomcms:post:' . (int) $post['id'] . '</guid>';
                $lines[] = '<pubDate>' . date(DATE_RSS, strtotime($post['published_at'])) . '</pubDate>';
                $lines[] = '<description>' . $this->xml($post['excerpt']) . '</description>';
                $lines[] = '<content:encoded>' . $this->cdata(markdownish($post['content'])) . '</content:encoded>';
                $lines[] = '</item>';
            }
            $lines[] = '</channel></rss>';
            return ['body' => implode("\n", $lines), 'modified' => strtotime($last ?: 'now') ?: time()];
        });
        XmlResponse::send($payload['body'], 'application/rss+xml', (int) $payload['modified']);
    }

    public function legacyRedirect(array $params): void
    {
        header('Location: /blog/' . rawurlencode($params['slug']), true, 301);
        exit;
    }

    private function sampleCategories(): array
    {
        return [
            ['id' => 1, 'name' => 'Teología', 'slug' => 'teologia'],
            ['id' => 2, 'name' => 'Desarrollo', 'slug' => 'desarrollo'],
            ['id' => 3, 'name' => 'Diseño', 'slug' => 'diseno'],
            ['id' => 4, 'name' => 'Música', 'slug' => 'musica'],
            ['id' => 5, 'name' => 'Arte', 'slug' => 'arte'],
        ];
    }

    private function xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function cdata(string $value): string { return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>'; }

    private function defaultBlogNav(): array
    {
        return [
            ['label' => 'Inicio', 'url' => '/blog'],
            ['label' => 'Tecnología', 'url' => '/blog?category=tecnologia'],
            ['label' => 'Teología', 'url' => '/blog?category=teologia'],
            ['label' => 'Música', 'url' => '/blog?category=musica'],
        ];
    }

    private function samplePosts(string $query, string $category): array
    {
        $posts = [
            ['id' => 1, 'title' => 'De Canaán a la Nueva Jerusalén', 'slug' => 'de-canaan-a-la-nueva-jerusalen', 'excerpt' => 'Un recorrido teológico por la narrativa bíblica que conecta la tierra prometida con la esperanza escatológica de la ciudad eterna.', 'content' => '', 'featured_image' => asset('img/hero-portrait.png'), 'status' => 'published', 'published_at' => '2026-06-10 10:00:00', 'updated_at' => '2026-06-10 10:00:00', 'category_id' => 1, 'category_name' => 'Teología', 'category_slug' => 'teologia', 'author_name' => 'jefté montenegro'],
            ['id' => 2, 'title' => 'Pensamientos sobre construir en público', 'slug' => 'pensamientos-sobre-construir-en-publico', 'excerpt' => 'Notas personales sobre consistencia, intención y compartir el proceso detrás de los proyectos.', 'content' => '', 'featured_image' => asset('img/hero-portrait.png'), 'status' => 'published', 'published_at' => '2026-06-02 10:00:00', 'updated_at' => '2026-06-02 10:00:00', 'category_id' => 2, 'category_name' => 'Desarrollo', 'category_slug' => 'desarrollo', 'author_name' => 'jefté montenegro'],
            ['id' => 3, 'title' => 'Identidad visual con intención', 'slug' => 'identidad-visual-con-intencion', 'excerpt' => 'Reflexiones sobre crear sistemas visuales que comuniquen más allá de la estética.', 'content' => '', 'featured_image' => asset('img/hero-portrait.png'), 'status' => 'published', 'published_at' => '2026-05-28 10:00:00', 'updated_at' => '2026-05-28 10:00:00', 'category_id' => 3, 'category_name' => 'Diseño', 'category_slug' => 'diseno', 'author_name' => 'jefté montenegro'],
            ['id' => 4, 'title' => 'Música que me ha formado', 'slug' => 'musica-que-me-ha-formado', 'excerpt' => 'Una lista curada de artistas y álbumes que han marcado mi camino creativo.', 'content' => '', 'featured_image' => asset('img/hero-portrait.png'), 'status' => 'published', 'published_at' => '2026-05-15 10:00:00', 'updated_at' => '2026-05-15 10:00:00', 'category_id' => 4, 'category_name' => 'Música', 'category_slug' => 'musica', 'author_name' => 'jefté montenegro'],
        ];
        return array_values(array_filter($posts, function (array $post) use ($query, $category): bool {
            $matchesCategory = $category === '' || $post['category_slug'] === $category;
            $matchesQuery = $query === '' || stripos($post['title'] . ' ' . $post['excerpt'], $query) !== false;
            return $matchesCategory && $matchesQuery;
        }));
    }

    private function samplePost(string $slug): ?array
    {
        foreach ($this->samplePosts('', '') as $post) {
            if ($post['slug'] === $slug) {
                $post['content'] = "## Una lectura con estructura\n\nEste artículo de preview existe para poder revisar diseño sin una base MySQL configurada. En producción, el contenido vendrá desde la tabla `posts`.\n\n> La intención visual también comunica arquitectura.\n\n- Títulos\n- Citas\n- Listas\n- Código inline\n\n## Cierre\n\nCuando importes `database.sql`, estos datos de muestra dejarán paso al CMS real.";
                return $post;
            }
        }
        return null;
    }
}
