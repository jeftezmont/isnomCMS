<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Paginator;
use App\Models\Media;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Services\PodcastAudioService;

final class PodcastAdminController extends Controller
{
    public function podcasts(): void
    {
        $this->requireAuth();
        $model = new Podcast(Database::connect($this->config));
        $this->view('admin/podcasts', ['title' => 'Podcast', 'podcasts' => $model->all()], 'admin');
    }

    public function podcastForm(array $params = []): void
    {
        $this->requireAuth();
        $pdo = Database::connect($this->config);
        $model = new Podcast($pdo);
        $media = new Media($pdo, $this->config);
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $podcast = $id ? $model->find($id) : null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            try {
                $cover = trim((string) ($_POST['cover_image'] ?? '')) ?: (string) ($podcast['cover_image'] ?? '');
                if (!empty($_FILES['cover_upload']['name'])) {
                    $cover = $media->store($_FILES['cover_upload'], Auth::id() ?? 0) ?: $cover;
                }
                $data = $this->podcastData($_POST, $cover);
                $model->save($data, $id);
                $this->redirect('/admin/podcasts');
            } catch (\Throwable $exception) {
                ErrorHandler::report($exception, 'podcast-admin');
                $error = $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'No se pudo guardar el podcast. Revisa campos y slug.';
                $podcast = array_merge($podcast ?? [], $_POST);
            }
        }
        $this->view('admin/podcast-form', [
            'title' => $id ? 'Editar podcast' : 'Nuevo podcast',
            'podcast' => $podcast,
            'mediaItems' => $media->all(),
            'error' => $error,
        ], 'admin');
    }

    public function deletePodcast(array $params): void
    {
        $this->requireAuth();
        Csrf::verify();
        (new Podcast(Database::connect($this->config)))->delete((int) $params['id']);
        $this->redirect('/admin/podcasts');
    }

    public function episodes(): void
    {
        $this->requireAuth();
        $page = Paginator::page($_GET['page'] ?? 1);
        $result = (new PodcastEpisode(Database::connect($this->config)))->paginateAdmin($page);
        $this->view('admin/podcast-episodes', [
            'title' => 'Episodios',
            'episodes' => $result['items'],
            'pagination' => $result['pagination'],
        ], 'admin');
    }

    public function episodeForm(array $params = []): void
    {
        $this->requireAuth();
        $pdo = Database::connect($this->config);
        $podcasts = new Podcast($pdo);
        $episodes = new PodcastEpisode($pdo);
        $media = new Media($pdo, $this->config);
        $audioService = new PodcastAudioService($this->config);
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $episode = $id ? $episodes->find($id) : null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            try {
                $audio = $this->episodeAudio($_POST, $_FILES['audio_upload'] ?? [], $episode, $audioService);
                $image = trim((string) ($_POST['image_url'] ?? '')) ?: (string) ($episode['image_url'] ?? '');
                if (!empty($_FILES['image_upload']['name'])) {
                    $image = $media->store($_FILES['image_upload'], Auth::id() ?? 0) ?: $image;
                }
                $data = $this->episodeData($_POST, $audio, $image);
                $episodes->save($data, $id);
                $this->redirect('/admin/podcast-episodes');
            } catch (\Throwable $exception) {
                ErrorHandler::report($exception, 'podcast-episode-admin');
                $error = $exception->getMessage();
                $episode = array_merge($episode ?? [], $_POST);
            }
        }

        $this->view('admin/podcast-episode-form', [
            'title' => $id ? 'Editar episodio' : 'Nuevo episodio',
            'episode' => $episode,
            'podcasts' => $podcasts->all(),
            'mediaItems' => $media->all(),
            'error' => $error,
        ], 'admin');
    }

    public function deleteEpisode(array $params): void
    {
        $this->requireAuth();
        Csrf::verify();
        (new PodcastEpisode(Database::connect($this->config)))->delete((int) $params['id']);
        $this->redirect('/admin/podcast-episodes');
    }

    public function validateDropbox(): void
    {
        $this->requireAuth();
        if (!Csrf::valid((string) ($_POST['_csrf'] ?? ''))) {
            $this->json(['ok' => false, 'error' => 'CSRF inválido.'], 419);
        }
        try {
            $audio = (new PodcastAudioService($this->config))->validateDropbox((string) ($_POST['url'] ?? ''));
            $this->json(['ok' => true, 'audio' => $audio]);
        } catch (\Throwable $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        }
    }

    private function podcastData(array $input, string $cover): array
    {
        $required = ['name','author','owner_name','owner_email','category_primary'];
        foreach ($required as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') throw new \InvalidArgumentException('Completa los campos obligatorios del podcast.');
        }
        if (!filter_var($input['owner_email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('El correo RSS no es válido.');
        if ($cover === '') throw new \InvalidArgumentException('Selecciona una portada general.');
        return [
            'name' => trim((string) $input['name']),
            'slug' => $this->slug((string) (($input['slug'] ?? '') ?: $input['name'])),
            'short_description' => trim((string) ($input['short_description'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'author' => trim((string) $input['author']),
            'owner_name' => trim((string) $input['owner_name']),
            'owner_email' => trim((string) $input['owner_email']),
            'language' => trim((string) ($input['language'] ?? 'es-MX')) ?: 'es-MX',
            'category_primary' => trim((string) $input['category_primary']),
            'category_secondary' => trim((string) ($input['category_secondary'] ?? '')) ?: null,
            'copyright' => trim((string) ($input['copyright'] ?? '')) ?: null,
            'website_url' => $this->optionalUrl($input['website_url'] ?? ''),
            'cover_image' => $cover,
            'explicit' => !empty($input['explicit']) ? 1 : 0,
            'active' => !empty($input['active']) ? 1 : 0,
            'apple_podcasts_url' => $this->optionalUrl($input['apple_podcasts_url'] ?? ''),
            'spotify_url' => $this->optionalUrl($input['spotify_url'] ?? ''),
            'episodes_per_page' => max(1, min(50, (int) ($input['episodes_per_page'] ?? 9))),
        ];
    }

    private function episodeData(array $input, array $audio, string $image): array
    {
        if ((int) ($input['podcast_id'] ?? 0) < 1 || trim((string) ($input['title'] ?? '')) === '') {
            throw new \InvalidArgumentException('Selecciona un podcast y escribe el título.');
        }
        $status = in_array($input['status'] ?? '', ['draft','scheduled','published'], true) ? $input['status'] : 'draft';
        $published = trim((string) ($input['published_at'] ?? ''));
        if ($status !== 'draft' && $published === '') throw new \InvalidArgumentException('Los episodios programados o publicados necesitan fecha.');
        return [
            'podcast_id' => (int) $input['podcast_id'],
            'title' => trim((string) $input['title']),
            'slug' => $this->slug((string) (($input['slug'] ?? '') ?: $input['title'])),
            'summary' => trim((string) ($input['summary'] ?? '')),
            'show_notes' => trim((string) ($input['show_notes'] ?? '')),
            'audio_source' => $audio['source'],
            'audio_local_path' => $audio['local_path'],
            'audio_original_url' => $audio['original_url'],
            'audio_url' => $audio['url'],
            'audio_mime_type' => $audio['mime_type'],
            'audio_file_size' => $audio['file_size'],
            'duration' => trim((string) ($input['duration'] ?? '')) ?: null,
            'image_url' => $image ?: null,
            'author' => trim((string) ($input['author'] ?? '')) ?: null,
            'episode_number' => ($input['episode_number'] ?? '') !== '' ? max(1, (int) $input['episode_number']) : null,
            'season_number' => ($input['season_number'] ?? '') !== '' ? max(1, (int) $input['season_number']) : null,
            'episode_type' => in_array($input['episode_type'] ?? '', ['full','trailer','bonus'], true) ? $input['episode_type'] : 'full',
            'explicit' => !empty($input['explicit']) ? 1 : 0,
            'status' => $status,
            'published_at' => $published !== '' ? str_replace('T', ' ', $published) . (strlen($published) === 16 ? ':00' : '') : null,
        ];
    }

    private function episodeAudio(array $input, array $file, ?array $episode, PodcastAudioService $service): array
    {
        $source = in_array($input['audio_source'] ?? '', ['local','dropbox'], true) ? $input['audio_source'] : 'local';
        if ($source === 'local') {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) return $service->storeLocal($file);
            if ($episode && $episode['audio_source'] === 'local') return [
                'source' => 'local', 'local_path' => $episode['audio_local_path'], 'original_url' => null,
                'url' => $episode['audio_url'], 'mime_type' => $episode['audio_mime_type'], 'file_size' => (int) $episode['audio_file_size'],
            ];
            throw new \RuntimeException('Selecciona un archivo de audio local.');
        }
        $url = trim((string) ($input['audio_original_url'] ?? ''));
        if ($episode && $episode['audio_source'] === 'dropbox' && $url === (string) $episode['audio_original_url']) return [
            'source' => 'dropbox', 'local_path' => null, 'original_url' => $episode['audio_original_url'],
            'url' => $episode['audio_url'], 'mime_type' => $episode['audio_mime_type'], 'file_size' => (int) $episode['audio_file_size'],
        ];
        return $service->validateDropbox($url);
    }

    private function optionalUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($value), 'https://')) throw new \InvalidArgumentException('Las URLs deben ser HTTPS válidas.');
        return $value;
    }

    private function slug(string $value): string
    {
        return strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $value)), '-'));
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
