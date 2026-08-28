<?php

namespace App\Services;

final class SecurityHeaders
{
    public function __construct(private array $config)
    {
    }

    public function send(): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Content-Security-Policy: ' . $this->contentSecurityPolicy());

        if ($this->isProduction() && $this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com",
            "connect-src 'self' https://challenges.cloudflare.com",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://open.spotify.com https://embed.music.apple.com https://music.apple.com https://challenges.cloudflare.com",
            "media-src 'self' https:",
            "font-src 'self' data:",
        ]);
    }

    private function isProduction(): bool
    {
        return strtolower((string) ($this->config['app_env'] ?? 'production')) === 'production';
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
