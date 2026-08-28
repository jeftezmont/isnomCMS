<?php

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

function env_value(string $key, string $default = ''): string
{
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : (string) $value;
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function excerpt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $months = ['Jan', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $time = strtotime($date);
    return strtoupper(date('d ', $time) . $months[(int) date('n', $time) - 1] . date(' Y', $time));
}

function markdownish(string $content): string
{
    $escaped = e($content);
    $escaped = preg_replace_callback('/^\[spotify:(https:\/\/open\.spotify\.com\/[^\]\s<]+)\]$/m', fn($m) => spotify_embed($m[1]) ?: $m[0], $escaped);
    $escaped = preg_replace_callback('/^\[applemusic:(https:\/\/(?:embed\.)?music\.apple\.com\/[^\]\s<]+)\]$/m', fn($m) => apple_music_embed($m[1]) ?: $m[0], $escaped);
    $escaped = preg_replace_callback('/^https:\/\/www\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)(?:&amp;[^\s<]+)?$/m', fn($m) => media_embed('https://www.youtube.com/embed/' . $m[1], 'YouTube video', 'embed--video'), $escaped);
    $escaped = preg_replace_callback('/^https:\/\/youtu\.be\/([a-zA-Z0-9_-]+)(?:\?[^\s<]+)?$/m', fn($m) => media_embed('https://www.youtube.com/embed/' . $m[1], 'YouTube video', 'embed--video'), $escaped);
    $escaped = preg_replace_callback('/^https:\/\/open\.spotify\.com\/[^\s<]+$/m', fn($m) => spotify_embed($m[0]) ?: $m[0], $escaped);
    $escaped = preg_replace_callback('/^https:\/\/(?:embed\.)?music\.apple\.com\/[^\s<]+$/m', fn($m) => apple_music_embed($m[0]) ?: $m[0], $escaped);
    $escaped = preg_replace('/!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/', '<img class="content-image" src="$2" alt="$1" loading="lazy">', $escaped);
    $escaped = preg_replace('/^(---|\*\*\*)$/m', '<hr>', $escaped);
    $escaped = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $escaped);
    $escaped = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $escaped);
    $escaped = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $escaped);
    $escaped = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $escaped);
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/`(.+?)`/s', '<code>$1</code>', $escaped);
    $escaped = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $escaped);
    $blocks = preg_split("/\n{2,}/", trim($escaped));
    return implode("\n", array_map(function (string $block): string {
        if (preg_match('/^<(h1|h2|h3|h4|ul|ol|blockquote|pre|hr|img|div)/', $block)) {
            return $block;
        }
        if (str_starts_with($block, '&gt; ')) {
            return '<blockquote>' . substr($block, 5) . '</blockquote>';
        }
        if (str_starts_with($block, '- ')) {
            $items = array_map(fn($line) => '<li>' . substr($line, 2) . '</li>', explode("\n", $block));
            return '<ul>' . implode('', $items) . '</ul>';
        }
        return '<p>' . nl2br($block) . '</p>';
    }, $blocks));
}

function media_embed(string $src, string $title, string $class): string
{
    return '<div class="embed ' . e($class) . '"><iframe src="' . e($src) . '" title="' . e($title) . '" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
}

function spotify_embed(string $url): ?string
{
    $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $parts = parse_url($decoded);
    $path = trim($parts['path'] ?? '', '/');
    $segments = $path === '' ? [] : explode('/', $path);
    if (($segments[0] ?? '') !== '' && str_starts_with($segments[0], 'intl-')) {
        array_shift($segments);
    }
    $type = $segments[0] ?? '';
    $id = preg_replace('/[^A-Za-z0-9]/', '', $segments[1] ?? '');
    $allowed = ['album', 'artist', 'episode', 'playlist', 'show', 'track'];
    if (!in_array($type, $allowed, true) || $id === '') {
        return null;
    }
    return media_embed("https://open.spotify.com/embed/{$type}/{$id}", 'Spotify embed', 'embed--spotify');
}

function apple_music_embed(string $url): ?string
{
    $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $parts = parse_url($decoded);
    $path = $parts['path'] ?? '';
    if ($path === '') {
        return null;
    }
    $src = 'https://embed.music.apple.com' . $path;
    if (!empty($parts['query'])) {
        $src .= '?' . $parts['query'];
    }
    return media_embed($src, 'Apple Music embed', 'embed--music');
}

function inline_emphasis(string $content): string
{
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', e($content));
}
