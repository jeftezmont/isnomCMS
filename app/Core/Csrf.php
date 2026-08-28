<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!self::valid(is_string($token) ? $token : '')) {
            http_response_code(419);
            exit('CSRF token inválido.');
        }
    }

    public static function valid(string $token): bool
    {
        return $token !== '' && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }
}
