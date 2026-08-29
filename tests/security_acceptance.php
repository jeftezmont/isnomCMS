<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/helpers.php';
load_env(ROOT_PATH . '/.env');
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require $path;
});

use App\Core\Database;
use App\Core\Auth;
use App\Models\TwoFactor;
use App\Services\SecretCipher;
use App\Services\TotpService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
session_start();

$totp = new TotpService();
$rfcSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$assert($totp->codeAt($rfcSecret, 1) === '287082', 'El vector HOTP RFC 4226 no coincide.');
$secret = $totp->generateSecret();
$step = intdiv(time(), 30);
$assert($totp->verify($secret, $totp->codeAt($secret, $step)) === $step, 'TOTP válido rechazado.');
$assert($totp->verify($secret, '000000', 0) === null || $totp->codeAt($secret, $step) === '000000', 'TOTP inválido aceptado.');

$config = require ROOT_PATH . '/config.php';
$cipher = new SecretCipher((string) $config['app_key']);
$encrypted = $cipher->encrypt($secret);
$assert($encrypted !== $secret && $cipher->decrypt($encrypted) === $secret, 'El cifrado reversible falló.');

$pdo = Database::connect($config);
$email = 'security-test-' . bin2hex(random_bytes(4)) . '@example.test';
$pdo->prepare('INSERT INTO users (name,email,password_hash,created_at,updated_at) VALUES (?,?,?,?,?)')->execute(['Security Test', $email, password_hash('temporary-password', PASSWORD_DEFAULT), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
$userId = (int) $pdo->lastInsertId();
try {
    $model = new TwoFactor($pdo);
    Auth::beginTwoFactor($config, ['id' => $userId, 'name' => 'Security Test'], false);
    $assert(Auth::twoFactorPending()['user_id'] === $userId, 'No se creó la sesión 2FA pendiente.');
    $assert(!Auth::check($config), 'La sesión pendiente obtuvo acceso administrativo.');
    $_SESSION['two_factor_pending']['expires_at'] = time() - 1;
    $assert(Auth::twoFactorPending() === null, 'La sesión pendiente no expiró.');
    $model->savePending($userId, $encrypted);
    $assert(!$model->enabled($userId), '2FA se activó antes de validar el primer código.');
    $model->enable($userId, $step);
    $assert($model->enabled($userId), '2FA no quedó activado.');
    $assert(!$model->acceptStep($userId, $step), 'Se reutilizó el mismo intervalo TOTP.');
    $codes = $model->replaceRecoveryCodes($userId);
    $assert(count($codes) === 10 && $model->remainingCodes($userId) === 10, 'No se generaron diez códigos.');
    $assert($model->consumeRecoveryCode($userId, $codes[0]), 'Código de recuperación válido rechazado.');
    $assert(!$model->consumeRecoveryCode($userId, $codes[0]), 'Código de recuperación reutilizado.');
    $assert($model->remainingCodes($userId) === 9, 'Contador de recuperación incorrecto.');
} finally {
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
}

echo "Security acceptance checks: OK\n";
