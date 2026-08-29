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

function admin_icon(string $name): string
{
    $icons = [
        'house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10.5 12 3l9 7.5"/><path d="M5 8.8V21h14V8.8"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v5h6"/><path d="M8 13h8"/><path d="M8 17h8"/><path d="M8 9h2"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'folder' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.8a2 2 0 0 1-1.6-.8L9.4 3.8A2 2 0 0 0 7.8 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'tag' => '<path d="M12 2H2v10l9.3 9.3a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4Z"/><path d="M7 7h.01"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'mic' => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v3"/>',
        'list-audio' => '<path d="M11 6h10"/><path d="M11 12h10"/><path d="M11 18h10"/><path d="M4 17v-6"/><circle cx="4" cy="19" r="2"/>',
        'user' => '<path d="M19 21a7 7 0 0 0-14 0"/><circle cx="12" cy="7" r="4"/>',
        'key-round' => '<path d="M2 18v3h3l8.7-8.7"/><circle cx="16" cy="8" r="6"/><path d="M19.5 4.5 18 6"/><path d="m15 9 3-3"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<path d="M12.2 2h-.4a2 2 0 0 0-2 1.7l-.2 1.2a7.8 7.8 0 0 0-1.6.9L6.9 5.3a2 2 0 0 0-2.6.8l-.2.4a2 2 0 0 0 .4 2.7l.9.8a8 8 0 0 0 0 1.9l-.9.8a2 2 0 0 0-.4 2.7l.2.4a2 2 0 0 0 2.6.8l1.1-.5a7.8 7.8 0 0 0 1.6.9l.2 1.2a2 2 0 0 0 2 1.7h.4a2 2 0 0 0 2-1.7l.2-1.2a7.8 7.8 0 0 0 1.6-.9l1.1.5a2 2 0 0 0 2.6-.8l.2-.4a2 2 0 0 0-.4-2.7l-.9-.8a8 8 0 0 0 0-1.9l.9-.8a2 2 0 0 0 .4-2.7l-.2-.4a2 2 0 0 0-2.6-.8l-1.1.5a7.8 7.8 0 0 0-1.6-.9l-.2-1.2a2 2 0 0 0-2-1.7Z"/><circle cx="12" cy="12" r="3"/>',
        'archive' => '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>',
        'rocket' => '<path d="M4.5 16.5c-1.2 1-1.7 2.6-1.5 4.5 1.9.2 3.5-.3 4.5-1.5"/><path d="M9 15 4 20"/><path d="M15 9l-6 6"/><path d="M14 3c3.5.2 5.8 1.2 7 3-1.8 4.8-4.8 8.1-9 10l-5-5c1.9-4.2 5.2-7.2 10-9Z"/><path d="M14 3c-.4 2 .1 3.8 1.5 5.2S18.7 10.1 21 9"/><circle cx="15" cy="9" r="1"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5-5L7 4l3 3 2.7-2.7a4 4 0 0 0 2 5L6.3 17.7a2 2 0 0 0 0 2.8l.2.2a2 2 0 0 0 2.8 0l8.4-8.4a4 4 0 0 0 5-2l-3 3-3-3 3-3a4 4 0 0 0-5-1Z"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    ];
    return '<span class="admin-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? '') . '</svg></span>';
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
