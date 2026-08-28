<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\WebAuthn;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;

final class AuthController extends Controller
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $email = trim($_POST['email'] ?? '');
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $error = 'Correo/usuario o contraseña incorrectos.';

            try {
                $attempts = new LoginAttempt(Database::connect($this->config));
                if ($attempts->tooMany($ip, $email)) {
                    $this->view('admin/login', [
                        'title' => 'Login',
                        'error' => 'Demasiados intentos. Intenta de nuevo en unos minutos.',
                        'turnstileSiteKey' => $this->turnstileSiteKey(),
                        'turnstileAction' => $this->turnstileAction(),
                        'csrfToken' => Csrf::token(),
                    ], 'admin-auth');
                    return;
                }

                if (!$this->verifyTurnstile()) {
                    $attempts->record($ip, $email, false);
                    $this->view('admin/login', [
                        'title' => 'Login',
                        'error' => 'No pudimos validar el CAPTCHA. Intenta de nuevo.',
                        'turnstileSiteKey' => $this->turnstileSiteKey(),
                        'turnstileAction' => $this->turnstileAction(),
                        'csrfToken' => Csrf::token(),
                    ], 'admin-auth');
                    return;
                }

                $remember = !empty($_POST['remember']);
                if (Auth::attempt($this->config, $email, $_POST['password'] ?? '', $remember)) {
                    $attempts->record($ip, $email, true);
                    $this->redirect('/admin');
                }
                $attempts->record($ip, $email, false);
            } catch (\Throwable $exception) {
                ErrorHandler::report($exception, 'authentication');
                $error = 'No se pudo procesar el acceso. Revisa la configuración.';
            }
        }

        $this->view('admin/login', [
            'title' => 'Login',
            'error' => $error ?? null,
            'turnstileSiteKey' => $this->turnstileSiteKey(),
            'turnstileAction' => $this->turnstileAction(),
            'csrfToken' => Csrf::token(),
        ], 'admin-auth');
    }

    public function logout(): void
    {
        Csrf::verify();
        Auth::logout($this->config);
        $this->redirect('/admin/login');
    }

    public function passkeyLoginOptions(): void
    {
        $input = $this->jsonInput();
        if (!Csrf::valid((string) ($input['_csrf'] ?? ''))) {
            $this->json(['error' => 'CSRF token inválido.'], 419);
            return;
        }

        try {
            $pdo = Database::connect($this->config);
            $options = (new WebAuthn($this->config))->publicKeyRequestOptions(new WebAuthnChallenge($pdo));
            $this->json(['publicKey' => $options]);
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'passkey-options');
            $this->json(['error' => 'No se pudo iniciar Passkey.'], 500);
        }
    }

    public function passkeyLoginVerify(): void
    {
        $input = $this->jsonInput();
        if (!Csrf::valid((string) ($input['_csrf'] ?? ''))) {
            $this->json(['error' => 'CSRF token inválido.'], 419);
            return;
        }

        try {
            $pdo = Database::connect($this->config);
            $credential = (new WebAuthn($this->config))->verifyAuthentication(
                $this->requireJsonArray($input, 'credential'),
                new WebAuthnChallenge($pdo),
                new WebAuthnCredential($pdo)
            );
            Auth::loginUser((int) $credential['user_id'], (string) $credential['user_name']);
            $this->json(['ok' => true, 'redirect' => '/admin']);
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'passkey-authentication');
            $this->json(['error' => 'No se pudo validar la passkey.'], 401);
        }
    }

    public function passkeyRegisterOptions(): void
    {
        if (!Auth::check($this->config)) {
            $this->json(['error' => 'No autorizado.'], 403);
            return;
        }
        $input = $this->jsonInput();
        if (!Csrf::valid((string) ($input['_csrf'] ?? ''))) {
            $this->json(['error' => 'CSRF token inválido.'], 419);
            return;
        }

        try {
            $pdo = Database::connect($this->config);
            $user = (new User($pdo))->find(Auth::id() ?? 0);
            if (!$user) {
                $this->json(['error' => 'Usuario no encontrado.'], 404);
                return;
            }
            $options = (new WebAuthn($this->config))->publicKeyCreationOptions(
                $user,
                new WebAuthnChallenge($pdo),
                new WebAuthnCredential($pdo)
            );
            $this->json(['publicKey' => $options]);
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'passkey-registration-options');
            $this->json(['error' => 'No se pudo preparar el registro.'], 500);
        }
    }

    public function passkeyRegisterVerify(): void
    {
        if (!Auth::check($this->config)) {
            $this->json(['error' => 'No autorizado.'], 403);
            return;
        }
        $input = $this->jsonInput();
        if (!Csrf::valid((string) ($input['_csrf'] ?? ''))) {
            $this->json(['error' => 'CSRF token inválido.'], 419);
            return;
        }

        try {
            $pdo = Database::connect($this->config);
            $webauthn = new WebAuthn($this->config);
            $data = $webauthn->verifyRegistration(
                $this->requireJsonArray($input, 'credential'),
                Auth::id() ?? 0,
                new WebAuthnChallenge($pdo)
            );
            $data['label'] = trim((string) ($input['label'] ?? '')) ?: 'Passkey';
            (new WebAuthnCredential($pdo))->create(Auth::id() ?? 0, $data);
            $this->json(['ok' => true, 'redirect' => '/admin/passkeys']);
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'passkey-registration');
            $this->json(['error' => 'No se pudo registrar la passkey.'], 400);
        }
    }

    private function verifyTurnstile(): bool
    {
        $secret = trim($this->config['turnstile']['secret_key'] ?? '');
        if ($secret === '') {
            return true;
        }

        $token = trim($_POST['cf-turnstile-response'] ?? '');
        if ($token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        if ($response === false) {
            return false;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['success'])) {
            return false;
        }

        $action = $this->turnstileAction();
        if ($action !== '' && ($data['action'] ?? '') !== $action) {
            return false;
        }

        $allowedHostnames = $this->turnstileHostnames();
        if ($allowedHostnames !== [] && !in_array($data['hostname'] ?? '', $allowedHostnames, true)) {
            return false;
        }

        if (!empty($data['challenge_ts'])) {
            $challengeTime = strtotime((string) $data['challenge_ts']);
            if ($challengeTime === false || abs(time() - $challengeTime) > 300) {
                return false;
            }
        }

        return true;
    }

    private function turnstileSiteKey(): string
    {
        $siteKey = trim($this->config['turnstile']['site_key'] ?? '');
        $secret = trim($this->config['turnstile']['secret_key'] ?? '');
        return $siteKey !== '' && $secret !== '' ? $siteKey : '';
    }

    private function turnstileAction(): string
    {
        return trim($this->config['turnstile']['action'] ?? 'admin_login');
    }

    /**
     * @return array<int, string>
     */
    private function turnstileHostnames(): array
    {
        $hostnames = $this->config['turnstile']['hostnames'] ?? [];
        if (!is_array($hostnames)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($hostname) => trim((string) $hostname), $hostnames)));
    }

    private function jsonInput(): array
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        return is_array($input) ? $input : [];
    }

    private function requireJsonArray(array $input, string $key): array
    {
        if (empty($input[$key]) || !is_array($input[$key])) {
            throw new \InvalidArgumentException("Campo {$key} inválido.");
        }
        return $input[$key];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
