<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

use App\Controllers\PodcastController;
use App\Core\Paginator;
use App\Services\DatabaseSchemaService;
use App\Services\PodcastAudioService;

function assert_true(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$first = Paginator::result(Paginator::page('no'), 9, 20);
$last = Paginator::result(99, 9, 20);
assert_true($first['page'] === 1 && $first['offset'] === 0, 'La página inválida no se normalizó.');
assert_true($last['page'] === 3 && $last['offset'] === 18, 'La página fuera de rango no se ajustó.');

$schema = new DatabaseSchemaService(ROOT_PATH . '/database.sql');
$definitions = $schema->definitions();
assert_true(isset($definitions['podcasts'], $definitions['podcast_episodes']), 'Faltan tablas del podcast.');
foreach (['podcast_id', 'status', 'published_at', 'guid', 'audio_source', 'audio_url', 'audio_mime_type', 'audio_file_size'] as $column) {
    assert_true(isset($definitions['podcast_episodes']['columns'][$column]), "Falta podcast_episodes.{$column}.");
}

$audio = new PodcastAudioService([
    'audio_upload_dir' => sys_get_temp_dir(), 'audio_upload_url' => '/uploads/audio',
    'max_audio_upload_bytes' => 1024,
]);
foreach (['http://www.dropbox.com/s/a/file.mp3', 'https://localhost/s/a/file.mp3', 'https://example.com/file.mp3'] as $url) {
    try { $audio->validateDropbox($url); throw new RuntimeException("Se aceptó URL insegura: {$url}"); }
    catch (InvalidArgumentException|RuntimeException $e) {
        assert_true(!str_starts_with($e->getMessage(), 'Se aceptó'), $e->getMessage());
    }
}

$config = ['app_url' => 'https://example.test', 'site' => ['name' => 'Test']];
$controller = new PodcastController($config);
$method = new ReflectionMethod($controller, 'rss');
$podcast = [
    'slug'=>'demo','name'=>'Demo & Podcast','website_url'=>null,'short_description'=>'Descripción','description'=>'Notas <seguras>',
    'language'=>'es-MX','copyright'=>'2026','author'=>'Autor','owner_name'=>'Autor','owner_email'=>'rss@example.test',
    'cover_image'=>'/cover.jpg','category_primary'=>'Technology','category_secondary'=>null,'explicit'=>0,
];
$episode = [
    'slug'=>'episodio','title'=>'Uno & dos','summary'=>'Resumen','show_notes'=>'Texto ]]> seguro','published_at'=>'2026-08-28 12:00:00',
    'audio_url'=>'https://example.test/audio.mp3','audio_file_size'=>1234,'audio_mime_type'=>'audio/mpeg','guid'=>'11111111-1111-4111-8111-111111111111',
    'duration'=>'00:01:00','episode_number'=>1,'season_number'=>1,'episode_type'=>'full','explicit'=>0,'image_url'=>null,
];
$xml = $method->invoke($controller, $podcast, [$episode]);
assert_true(substr_count($xml, '<enclosure ') === 1, 'El RSS no generó un enclosure.');
assert_true(str_contains($xml, 'length="1234"') && str_contains($xml, 'type="audio/mpeg"'), 'El enclosure está incompleto.');
assert_true(str_contains($xml, $episode['guid']), 'El RSS no conserva el GUID.');
if (class_exists(DOMDocument::class)) {
    $dom = new DOMDocument();
    assert_true(@$dom->loadXML($xml), 'El RSS no es XML válido.');
}

echo "Podcast acceptance checks: OK\n";
