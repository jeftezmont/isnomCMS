<?php

namespace App\Core;

use App\Models\RememberToken;

final class Auth
{
    public static function check(array $config = []): bool
    {
        if (!empty($_SESSION['user_id'])) {
            return true;
        }
        return $config !== [] && self::restore($config);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function attempt(array $config, string $email, string $password, bool $remember = false): bool
    {
        $pdo = Database::connect($config);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        self::loginUser((int) $user['id'], $user['name']);
        if ($remember) {
            $rememberToken = new RememberToken($pdo);
            $rememberToken->deleteExpired();
            self::setRememberCookie($rememberToken->create((int) $user['id']));
        } else {
            self::clearRememberCookie();
        }
        return true;
    }

    public static function loginUser(int $id, string $name): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
    }

    public static function logout(array $config = []): void
    {
        if (!empty($_COOKIE[RememberToken::COOKIE]) && $config !== []) {
            try {
                (new RememberToken(Database::connect($config)))->revokeCookie($_COOKIE[RememberToken::COOKIE]);
            } catch (\Throwable) {
            }
        }
        self::clearRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function restore(array $config): bool
    {
        $cookie = $_COOKIE[RememberToken::COOKIE] ?? '';
        if ($cookie === '') {
            return false;
        }

        try {
            $restored = (new RememberToken(Database::connect($config)))->consume($cookie);
        } catch (\Throwable) {
            $restored = null;
        }

        if (!$restored) {
            self::clearRememberCookie();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $restored['user_id'];
        $_SESSION['user_name'] = $restored['name'];
        self::setRememberCookie($restored['cookie']);
        return true;
    }

    private static function setRememberCookie(string $value): void
    {
        setcookie(RememberToken::COOKIE, $value, [
            'expires' => time() + RememberToken::DAYS * 86400,
            'path' => '/',
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[RememberToken::COOKIE] = $value;
    }

    private static function clearRememberCookie(): void
    {
        setcookie(RememberToken::COOKIE, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[RememberToken::COOKIE]);
    }

    private static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
