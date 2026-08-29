<?php

$turnstileHostnames = env_value(
    'TURNSTILE_HOSTNAMES',
    parse_url(env_value('APP_URL', 'https://jeftezmont.me'), PHP_URL_HOST) ?: ''
);
$webauthnOrigins = env_value('WEBAUTHN_ORIGINS', env_value('APP_URL', 'https://jeftezmont.me'));

return [
    'app_version' => '1.0',
    'app_env' => env_value('APP_ENV', 'production'),
    'app_url' => rtrim(env_value('APP_URL', 'https://jeftezmont.me'), '/'),
    'app_key' => env_value('APP_KEY', ''),
    'timezone' => env_value('APP_TIMEZONE', 'America/Mexico_City'),
    'setup_token' => env_value('SETUP_TOKEN', ''),
    'db' => [
        'host' => env_value('DB_HOST', 'localhost'),
        'name' => env_value('DB_NAME', 'your_database'),
        'user' => env_value('DB_USER', 'your_user'),
        'password' => env_value('DB_PASSWORD', 'your_password'),
        'charset' => 'utf8mb4',
    ],
    'turnstile' => [
        'site_key' => env_value('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env_value('TURNSTILE_SECRET_KEY', env_value('TURNSTILE_SECRET', '')),
        'action' => env_value('TURNSTILE_ACTION', 'admin_login'),
        'hostnames' => array_values(array_filter(array_map('trim', explode(',', $turnstileHostnames)))),
    ],
    'webauthn' => [
        'rp_id' => env_value('WEBAUTHN_RP_ID', parse_url(env_value('APP_URL', 'https://jeftezmont.me'), PHP_URL_HOST) ?: 'jeftezmont.me'),
        'rp_name' => env_value('WEBAUTHN_RP_NAME', 'jeftezmont.me'),
        'origins' => array_values(array_filter(array_map('trim', explode(',', $webauthnOrigins)))),
        'timeout' => 60000,
    ],
    'upload_dir' => __DIR__ . '/public/uploads',
    'upload_url' => '/uploads',
    'max_upload_bytes' => 8 * 1024 * 1024,
    'max_audio_upload_bytes' => 250 * 1024 * 1024,
    'audio_upload_dir' => __DIR__ . '/public/uploads/audio',
    'audio_upload_url' => '/uploads/audio',
    'storage_dir' => __DIR__ . '/storage',
    'log_dir' => __DIR__ . '/storage/logs',
    'cache_dir' => __DIR__ . '/storage/cache',
    'cache_ttl' => max(60, (int) env_value('CACHE_TTL', '900')),
    'site' => [
        'name' => 'jefté montenegro',
        'role' => 'computer engineer',
        'description' => 'Ingeniero en Sistemas, desarrollador web y creador digital en Ciudad de México.',
        'social' => [
            'Instagram' => 'https://instagram.com/jeftezmont',
            'SoundCloud' => 'https://soundcloud.com/jeftezmont',
            'Threads' => 'https://www.threads.com/@jeftezmont',
            'Blog' => '/blog',
        ],
    ],
];
