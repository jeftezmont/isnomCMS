<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';
load_env(ROOT_PATH . '/.env');
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

use App\Core\Database;
use App\Models\Podcast;
use App\Models\PodcastEpisode;

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function request(string $url, array $headers = []): array {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($curl);
    if (!is_string($response)) throw new RuntimeException(curl_error($curl));
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

$config = require ROOT_PATH . '/config.php';
$base = rtrim($config['app_url'], '/');
if (!str_contains($base, '127.0.0.1') && !str_contains($base, 'localhost')) {
    throw new RuntimeException('Esta prueba solo puede ejecutarse contra localhost.');
}

$pdo = Database::connect($config);
$podcastModel = new Podcast($pdo);
$episodeModel = new PodcastEpisode($pdo);
$token = bin2hex(random_bytes(8));
$slug = 'acceptance-' . $token;
$audioPath = ROOT_PATH . '/public/uploads/audio/' . $slug . '.mp3';
$podcastId = null;

try {
    if (!is_dir(dirname($audioPath))) mkdir(dirname($audioPath), 0755, true);
    $command = escapeshellarg('/opt/homebrew/bin/ffmpeg') . ' -hide_banner -loglevel error -f lavfi -i anullsrc=r=44100:cl=mono -t 1 -codec:a libmp3lame ' . escapeshellarg($audioPath) . ' -y';
    exec($command, $output, $exitCode);
    expect($exitCode === 0 && is_file($audioPath), 'No se pudo generar el MP3 temporal.');
    expect((new finfo(FILEINFO_MIME_TYPE))->file($audioPath) === 'audio/mpeg', 'El fixture no fue detectado como MP3 real.');

    $podcastId = $podcastModel->save([
        'name'=>'Acceptance Test','slug'=>$slug,'short_description'=>'Prueba temporal','description'=>'Prueba temporal',
        'author'=>'isnomCMS','owner_name'=>'isnomCMS','owner_email'=>'test@example.com','language'=>'es-MX',
        'category_primary'=>'Technology','category_secondary'=>null,'copyright'=>'2026','website_url'=>null,
        'cover_image'=>'/assets/favicon/apple-touch-icon.png','explicit'=>0,'active'=>1,
        'apple_podcasts_url'=>null,'spotify_url'=>null,'episodes_per_page'=>9,
    ]);
    $episodeId = $episodeModel->save([
        'podcast_id'=>$podcastId,'title'=>'Episodio temporal','slug'=>'episodio-temporal','summary'=>'Prueba','show_notes'=>'Notas',
        'audio_source'=>'local','audio_local_path'=>$audioPath,'audio_original_url'=>null,
        'audio_url'=>'/uploads/audio/'.$slug.'.mp3','audio_mime_type'=>'audio/mpeg','audio_file_size'=>filesize($audioPath),
        'duration'=>'00:00:01','image_url'=>null,'author'=>null,'episode_number'=>1,'season_number'=>1,
        'episode_type'=>'full','explicit'=>0,'status'=>'published',
        'published_at'=>$pdo->query('SELECT DATE_SUB(NOW(), INTERVAL 1 MINUTE)')->fetchColumn(),
    ]);
    $guid = $episodeModel->find($episodeId)['guid'] ?? '';
    $episodeModel->save(array_merge($episodeModel->find($episodeId), ['title' => 'Episodio temporal editado']), $episodeId);
    expect(($episodeModel->find($episodeId)['guid'] ?? '') === $guid, 'El GUID cambió al editar.');
    expect($episodeModel->findPublishedBySlugs($slug, 'episodio-temporal') !== null, 'La consulta pública no encontró el episodio publicado.');

    $podcastPage = request($base.'/podcast/'.$slug);
    expect($podcastPage['status'] === 200, 'Falló la página del podcast: HTTP '.$podcastPage['status']);
    $episodePage = request($base.'/podcast/'.$slug.'/episodio-temporal');
    expect($episodePage['status'] === 200, 'Falló la página del episodio: HTTP '.$episodePage['status']);
    $feed = request($base.'/podcast/'.$slug.'/feed.xml');
    expect($feed['status'] === 200, 'Falló el feed RSS.');
    $xml = new DOMDocument();
    expect(@$xml->loadXML($feed['body']), 'El feed no es XML válido.');
    $enclosure = $xml->getElementsByTagName('enclosure')->item(0);
    expect($enclosure && $enclosure->getAttribute('url') && $enclosure->getAttribute('length') && $enclosure->getAttribute('type'), 'El enclosure está incompleto.');

    $head = request($base.'/uploads/audio/'.$slug.'.mp3');
    $range = request($base.'/uploads/audio/'.$slug.'.mp3', ['Range: bytes=0-31']);
    expect($head['status'] === 200, 'El audio local no está accesible.');
    expect(in_array($range['status'], [200, 206], true), 'El servidor rechazó la solicitud Range.');
    echo "Podcast HTTP acceptance checks: OK\n";
} finally {
    if (is_int($podcastId)) $podcastModel->delete($podcastId);
    if (is_file($audioPath)) unlink($audioPath);
}
