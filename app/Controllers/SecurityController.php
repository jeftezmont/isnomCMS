<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\TwoFactor;
use App\Models\RememberToken;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\SecretCipher;
use App\Services\TotpService;

final class SecurityController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('security.view');
        $this->render();
    }

    public function start(): void
    {
        $this->requirePermission('security.view');
        Csrf::verify();
        $pdo = Database::connect($this->config);
        if ((new TwoFactor($pdo))->enabled(Auth::id() ?? 0)) {
            $this->render('2FA ya está activado. Desactívalo antes de iniciar una configuración nueva.');
            return;
        }
        $user = (new User($pdo))->findWithPassword(Auth::id() ?? 0);
        if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            $this->render('La contraseña actual no es correcta.');
            return;
        }
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $encrypted = (new SecretCipher((string) ($this->config['app_key'] ?? '')))->encrypt($secret);
        (new TwoFactor($pdo))->savePending((int) $user['id'], $encrypted);
        $issuer = trim((string) ($this->config['webauthn']['rp_name'] ?? 'isnomCMS')) ?: 'isnomCMS';
        $this->render(null, ['setupSecret' => $secret, 'setupUri' => $totp->uri($secret, (string) $user['email'], $issuer)]);
    }

    public function confirm(): void
    {
        $this->requirePermission('security.view');
        Csrf::verify();
        $pdo = Database::connect($this->config);
        $model = new TwoFactor($pdo);
        $row = $model->find(Auth::id() ?? 0);
        if (!$row || $row['enabled_at'] !== null) {
            $this->render('Inicia nuevamente la activación de 2FA.');
            return;
        }
        $secret = (new SecretCipher((string) ($this->config['app_key'] ?? '')))->decrypt($row['encrypted_secret']);
        $step = (new TotpService())->verify($secret, (string) ($_POST['code'] ?? ''));
        if ($step === null) {
            $this->render('El código no es válido. Revisa la hora del dispositivo e inténtalo de nuevo.', ['setupSecret' => $secret, 'setupUri' => (new TotpService())->uri($secret, (string) ((new User($pdo))->find(Auth::id() ?? 0)['email'] ?? ''), trim((string) ($this->config['webauthn']['rp_name'] ?? 'isnomCMS')))]);
            return;
        }
        $model->enable(Auth::id() ?? 0, $step);
        (new RememberToken($pdo))->revokeForUser(Auth::id() ?? 0);
        $this->render(null, ['recoveryCodes' => $model->replaceRecoveryCodes(Auth::id() ?? 0), 'message' => '2FA quedó activado correctamente. Guarda ahora tus códigos de recuperación.']);
    }

    public function regenerate(): void
    {
        $this->requirePermission('security.view');
        Csrf::verify();
        $pdo = Database::connect($this->config);
        if (!(new User($pdo))->verifyPassword(Auth::id() ?? 0, (string) ($_POST['password'] ?? ''))) {
            $this->render('La contraseña actual no es correcta.');
            return;
        }
        $model = new TwoFactor($pdo);
        if (!$model->enabled(Auth::id() ?? 0)) {
            $this->render('Activa 2FA antes de generar códigos de recuperación.');
            return;
        }
        $this->render(null, ['recoveryCodes' => $model->replaceRecoveryCodes(Auth::id() ?? 0), 'message' => 'Los códigos anteriores quedaron invalidados. Guarda los nuevos ahora.']);
    }

    public function disable(): void
    {
        $this->requirePermission('security.view');
        Csrf::verify();
        $pdo = Database::connect($this->config);
        $userId = Auth::id() ?? 0;
        $users = new User($pdo);
        $model = new TwoFactor($pdo);
        $row = $model->find($userId);
        $valid = $row !== null && $users->verifyPassword($userId, (string) ($_POST['password'] ?? ''));
        if ($valid) {
            $secret = (new SecretCipher((string) ($this->config['app_key'] ?? '')))->decrypt($row['encrypted_secret']);
            $step = (new TotpService())->verify($secret, (string) ($_POST['code'] ?? ''));
            $valid = $step !== null || $model->consumeRecoveryCode($userId, (string) ($_POST['code'] ?? ''));
        }
        if (!$valid) {
            $this->render('Confirma tu contraseña y un código 2FA o de recuperación válido.');
            return;
        }
        $model->disable($userId);
        (new RememberToken($pdo))->revokeForUser($userId);
        Auth::clearTwoFactorPending();
        $this->render(null, ['message' => 'La autenticación en dos factores fue desactivada.']);
    }

    private function render(?string $error = null, array $extra = []): void
    {
        $pdo = Database::connect($this->config);
        $userId = Auth::id() ?? 0;
        $twoFactor = new TwoFactor($pdo);
        $passkeys = (new WebAuthnCredential($pdo))->forUser($userId);
        $enabled = $twoFactor->enabled($userId);
        $this->view('admin/security', array_merge([
            'title' => 'Seguridad', 'error' => $error, 'message' => null,
            'passkeys' => $passkeys, 'twoFactorEnabled' => $enabled,
            'recoveryCount' => $enabled ? $twoFactor->remainingCodes($userId) : 0,
            'appKeyReady' => strlen(trim((string) ($this->config['app_key'] ?? ''))) >= 32,
            'csrfToken' => Csrf::token(),
        ], $extra), 'admin');
    }
}
