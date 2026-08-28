<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit("Este script solo se ejecuta por CLI.\n");
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

$config = require ROOT_PATH . '/config.php';
$pdo = Database::connect($config);
$podcasts = new Podcast($pdo);
$episodes = new PodcastEpisode($pdo);
$cover = '/assets/img/podcast-demo-cover.png';
$podcastSlug = 'conversaciones-con-sentido';
$existingPodcast = $pdo->prepare('SELECT id FROM podcasts WHERE slug = ? LIMIT 1');
$existingPodcast->execute([$podcastSlug]);
$podcastId = $existingPodcast->fetchColumn();
$podcastData = [
    'name'=>'Conversaciones con sentido','slug'=>$podcastSlug,
    'short_description'=>'Ideas, conversaciones y reflexiones sobre tecnología, creatividad y fe.',
    'description'=>'Un espacio para explorar las ideas que conectan nuestra forma de crear, creer y vivir.',
    'author'=>'jefté montenegro','owner_name'=>'jefté montenegro','owner_email'=>'jeftezmont@gmail.com',
    'language'=>'es-MX','category_primary'=>'Religion & Spirituality','category_secondary'=>'Christianity',
    'copyright'=>'© jefté montenegro — 2026','website_url'=>$config['app_url'].'/podcast/'.$podcastSlug,
    'cover_image'=>$cover,'explicit'=>0,'active'=>1,'apple_podcasts_url'=>null,'spotify_url'=>null,'episodes_per_page'=>9,
];
$podcastId = $podcasts->save($podcastData, $podcastId ? (int) $podcastId : null);

$audioDirectory = ROOT_PATH . '/public/uploads/audio';
if (!is_dir($audioDirectory)) mkdir($audioDirectory, 0755, true);
$items = [
    ['number'=>5,'slug'=>'notas-de-ingenieria','title'=>'Notas de ingeniería: aprendizajes recientes','summary'=>'Ideas y aprendizajes que estoy aplicando en proyectos actuales.','days'=>10,'duration'=>'00:01:15','frequency'=>180],
    ['number'=>6,'slug'=>'como-la-musica-mejora-mi-enfoque','title'=>'Cómo la música mejora mi enfoque','summary'=>'La ciencia detrás de por qué la música puede aumentar tu concentración.','days'=>8,'duration'=>'00:01:15','frequency'=>220],
    ['number'=>7,'slug'=>'el-diseno-de-dios-en-lo-cotidiano','title'=>'El diseño de Dios en lo cotidiano','summary'=>'Reflexiones sobre cómo Su diseño se revela en los pequeños detalles de cada día.','days'=>6,'duration'=>'00:01:15','frequency'=>260],
    ['number'=>8,'slug'=>'gracia-verdad-y-libertad-en-cristo','title'=>'Gracia, verdad y libertad en Cristo','summary'=>'Una conversación sobre cómo la gracia transforma nuestra forma de vivir la verdad y nos hace verdaderamente libres.','days'=>4,'duration'=>'00:01:15','frequency'=>300],
    ['number'=>9,'slug'=>'musica-que-me-inspira','title'=>'Música que me inspira','summary'=>'Una selección de artistas, canciones y atmósferas que acompañan mi trabajo creativo.','days'=>2,'duration'=>'00:01:15','frequency'=>340],
];

foreach ($items as $item) {
    $filename = 'podcast-demo-' . $item['number'] . '.mp3';
    $audioPath = $audioDirectory . '/' . $filename;
    $ffmpeg = '/opt/homebrew/bin/ffmpeg';
    if (!is_executable($ffmpeg)) throw new RuntimeException('ffmpeg es necesario para generar los audios demo locales.');
    $filter = 'sine=frequency=' . $item['frequency'] . ':sample_rate=44100:duration=75';
    $command = escapeshellarg($ffmpeg) . ' -hide_banner -loglevel error -f lavfi -i ' . escapeshellarg($filter) . ' -codec:a libmp3lame -q:a 7 ' . escapeshellarg($audioPath) . ' -y';
    exec($command, $output, $exitCode);
    if ($exitCode !== 0 || !is_file($audioPath)) throw new RuntimeException('No se pudo generar ' . $filename . '.');
    $publishedAt = $pdo->query('SELECT DATE_SUB(NOW(), INTERVAL ' . (int) $item['days'] . ' DAY)')->fetchColumn();
    $existing = $pdo->prepare('SELECT id FROM podcast_episodes WHERE podcast_id = ? AND slug = ? LIMIT 1');
    $existing->execute([$podcastId, $item['slug']]);
    $episodeId = $existing->fetchColumn();
    $episodes->save([
        'podcast_id'=>$podcastId,'title'=>$item['title'],'slug'=>$item['slug'],'summary'=>$item['summary'],
        'show_notes'=>"Hablamos sobre la gracia de Dios, cómo la verdad nos sostiene y cómo la libertad en Cristo no es hacer lo que queremos, sino vivir para lo que fuimos creados.\n\n## Enlaces y recursos\n\n- [Sitio del podcast]({$config['app_url']}/podcast/{$podcastSlug})\n- Libro recomendado: Gracia Abundante — John Piper\n- Versículo clave: Juan 8:31-32",
        'audio_source'=>'local','audio_local_path'=>$audioPath,'audio_original_url'=>null,
        'audio_url'=>'/uploads/audio/'.$filename,'audio_mime_type'=>'audio/mpeg','audio_file_size'=>filesize($audioPath),
        'duration'=>$item['duration'],'image_url'=>$cover,'author'=>'jefté montenegro','episode_number'=>$item['number'],
        'season_number'=>1,'episode_type'=>'full','explicit'=>$item['number'] === 8 ? 1 : 0,'status'=>'published','published_at'=>$publishedAt,
    ], $episodeId ? (int) $episodeId : null);
}

echo "Podcast demo listo: {$config['app_url']}/podcast/{$podcastSlug}/gracia-verdad-y-libertad-en-cristo\n";
