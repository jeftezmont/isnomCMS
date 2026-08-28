<?php

namespace App\Core;

use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;

final class WebAuthn
{
    public function __construct(private array $config)
    {
    }

    public function publicKeyCreationOptions(array $user, WebAuthnChallenge $challenges, WebAuthnCredential $credentials): array
    {
        $challenge = $this->randomBase64Url(32);
        $challenges->create('registration', $challenge, (int) $user['id']);

        return [
            'rp' => ['name' => $this->rpName(), 'id' => $this->rpId()],
            'user' => [
                'id' => $this->base64UrlEncode(pack('N', (int) $user['id'])),
                'name' => $user['email'],
                'displayName' => $user['name'],
            ],
            'challenge' => $challenge,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => $this->timeout(),
            'excludeCredentials' => array_map(static fn ($credential) => [
                'type' => 'public-key',
                'id' => $credential['credential_id'],
                'transports' => $credential['transports'] ? json_decode($credential['transports'], true) : [],
            ], $credentials->forUser((int) $user['id'])),
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
        ];
    }

    public function verifyRegistration(array $credential, int $userId, WebAuthnChallenge $challenges): array
    {
        $rawId = $this->requireString($credential, 'rawId');
        $response = $this->requireArray($credential, 'response');
        $clientDataJson = $this->base64UrlDecode($this->requireString($response, 'clientDataJSON'));
        $clientData = $this->validatedClientData($clientDataJson, 'webauthn.create');

        if (!$challenges->consume('registration', $clientData['challenge'], $userId)) {
            throw new \RuntimeException('Challenge de registro inválido o expirado.');
        }

        $attestationObject = $this->decodeCbor($this->base64UrlDecode($this->requireString($response, 'attestationObject')));
        if (!is_array($attestationObject) || empty($attestationObject['authData'])) {
            throw new \RuntimeException('Attestation inválida.');
        }

        $authData = $attestationObject['authData'];
        $this->validateAuthenticatorData($authData, true);
        $attested = $this->parseAttestedCredentialData($authData);

        if (!hash_equals($rawId, $this->base64UrlEncode($attested['credentialId']))) {
            throw new \RuntimeException('Credential ID inconsistente.');
        }

        return [
            'credential_id' => $rawId,
            'public_key' => $this->base64UrlEncode($attested['credentialPublicKey']),
            'counter' => $attested['counter'],
            'transports' => json_encode($response['transports'] ?? [], JSON_UNESCAPED_SLASHES),
        ];
    }

    public function publicKeyRequestOptions(WebAuthnChallenge $challenges): array
    {
        $challenge = $this->randomBase64Url(32);
        $challenges->create('authentication', $challenge);

        return [
            'challenge' => $challenge,
            'timeout' => $this->timeout(),
            'rpId' => $this->rpId(),
            'userVerification' => 'required',
            'allowCredentials' => [],
        ];
    }

    public function verifyAuthentication(array $assertion, WebAuthnChallenge $challenges, WebAuthnCredential $credentials): array
    {
        $rawId = $this->requireString($assertion, 'rawId');
        $stored = $credentials->findByCredentialId($rawId);
        if (!$stored) {
            throw new \RuntimeException('Credential ID inexistente.');
        }

        $response = $this->requireArray($assertion, 'response');
        $clientDataJson = $this->base64UrlDecode($this->requireString($response, 'clientDataJSON'));
        $clientData = $this->validatedClientData($clientDataJson, 'webauthn.get');

        if (!$challenges->consume('authentication', $clientData['challenge'])) {
            throw new \RuntimeException('Challenge de autenticación inválido o expirado.');
        }

        $authenticatorData = $this->base64UrlDecode($this->requireString($response, 'authenticatorData'));
        $parsedAuthData = $this->validateAuthenticatorData($authenticatorData, false);
        $signature = $this->base64UrlDecode($this->requireString($response, 'signature'));
        $signedData = $authenticatorData . hash('sha256', $clientDataJson, true);
        $publicKey = $this->base64UrlDecode($stored['public_key']);

        if (!$this->verifySignature($publicKey, $signature, $signedData)) {
            throw new \RuntimeException('Firma WebAuthn inválida.');
        }

        $oldCounter = (int) $stored['counter'];
        $newCounter = (int) $parsedAuthData['counter'];
        if ($oldCounter > 0 && $newCounter > 0 && $newCounter <= $oldCounter) {
            throw new \RuntimeException('Sign counter inválido.');
        }

        $credentials->markUsed((int) $stored['id'], $newCounter);
        $stored['counter'] = $newCounter;
        return $stored;
    }

    private function validatedClientData(string $clientDataJson, string $expectedType): array
    {
        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData) || ($clientData['type'] ?? '') !== $expectedType) {
            throw new \RuntimeException('Client data inválido.');
        }
        if (empty($clientData['challenge']) || !is_string($clientData['challenge'])) {
            throw new \RuntimeException('Challenge ausente.');
        }
        if (!$this->originAllowed((string) ($clientData['origin'] ?? ''))) {
            throw new \RuntimeException('Origin WebAuthn no permitido.');
        }
        return $clientData;
    }

    private function validateAuthenticatorData(string $authData, bool $requiresAttestedCredentialData): array
    {
        if (strlen($authData) < 37) {
            throw new \RuntimeException('Authenticator data incompleto.');
        }

        $rpIdHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $this->rpId(), true), $rpIdHash)) {
            throw new \RuntimeException('RP ID inválido.');
        }

        $flags = ord($authData[32]);
        if (($flags & 0x01) !== 0x01 || (($flags & 0x04) !== 0x04)) {
            throw new \RuntimeException('Se requiere presencia y verificación del usuario.');
        }
        if ($requiresAttestedCredentialData && (($flags & 0x40) !== 0x40)) {
            throw new \RuntimeException('Attested credential data ausente.');
        }

        $counter = unpack('N', substr($authData, 33, 4))[1];
        return ['flags' => $flags, 'counter' => $counter];
    }

    private function parseAttestedCredentialData(string $authData): array
    {
        $offset = 37 + 16;
        if (strlen($authData) < $offset + 2) {
            throw new \RuntimeException('Credential ID ausente.');
        }

        $credentialIdLength = unpack('n', substr($authData, $offset, 2))[1];
        $offset += 2;
        $credentialId = substr($authData, $offset, $credentialIdLength);
        $offset += $credentialIdLength;
        $credentialPublicKey = substr($authData, $offset);
        if ($credentialId === '' || $credentialPublicKey === '') {
            throw new \RuntimeException('Credential data inválido.');
        }

        $this->publicKeyToPem($credentialPublicKey);
        return [
            'credentialId' => $credentialId,
            'credentialPublicKey' => $credentialPublicKey,
            'counter' => unpack('N', substr($authData, 33, 4))[1],
        ];
    }

    private function verifySignature(string $cosePublicKey, string $signature, string $signedData): bool
    {
        $pem = $this->publicKeyToPem($cosePublicKey);
        $algorithm = $this->coseAlgorithm($cosePublicKey) === -257 ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA256;
        return openssl_verify($signedData, $signature, $pem, $algorithm) === 1;
    }

    private function publicKeyToPem(string $cosePublicKey): string
    {
        $key = $this->decodeCbor($cosePublicKey);
        if (!is_array($key)) {
            throw new \RuntimeException('Public key inválida.');
        }

        $kty = $key[1] ?? null;
        $alg = $key[3] ?? null;
        if ($kty === 2 && $alg === -7) {
            $x = $key[-2] ?? '';
            $y = $key[-3] ?? '';
            if (($key[-1] ?? null) !== 1 || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new \RuntimeException('Llave ES256 inválida.');
            }
            $spki = $this->derSequence(
                $this->derSequence($this->derOid('1.2.840.10045.2.1') . $this->derOid('1.2.840.10045.3.1.7')) .
                $this->derBitString("\x04" . $x . $y)
            );
            return $this->pem($spki);
        }

        if ($kty === 3 && $alg === -257) {
            $n = $key[-1] ?? '';
            $e = $key[-2] ?? '';
            if ($n === '' || $e === '') {
                throw new \RuntimeException('Llave RSA inválida.');
            }
            $rsaPublicKey = $this->derSequence($this->derInteger($n) . $this->derInteger($e));
            $spki = $this->derSequence(
                $this->derSequence($this->derOid('1.2.840.113549.1.1.1') . "\x05\x00") .
                $this->derBitString($rsaPublicKey)
            );
            return $this->pem($spki);
        }

        throw new \RuntimeException('Algoritmo WebAuthn no soportado.');
    }

    private function coseAlgorithm(string $cosePublicKey): int
    {
        $key = $this->decodeCbor($cosePublicKey);
        return is_array($key) ? (int) ($key[3] ?? 0) : 0;
    }

    private function decodeCbor(string $bytes): mixed
    {
        $offset = 0;
        return $this->decodeCborItem($bytes, $offset);
    }

    private function decodeCborItem(string $bytes, int &$offset): mixed
    {
        if ($offset >= strlen($bytes)) {
            throw new \RuntimeException('CBOR incompleto.');
        }
        $initial = ord($bytes[$offset++]);
        $major = $initial >> 5;
        $additional = $initial & 0x1f;
        $length = $this->cborLength($bytes, $offset, $additional);

        if ($major === 0) {
            return $length;
        }
        if ($major === 1) {
            return -1 - $length;
        }
        if ($major === 2) {
            $value = substr($bytes, $offset, $length);
            $offset += $length;
            return $value;
        }
        if ($major === 3) {
            $value = substr($bytes, $offset, $length);
            $offset += $length;
            return $value;
        }
        if ($major === 4) {
            $array = [];
            for ($i = 0; $i < $length; $i++) {
                $array[] = $this->decodeCborItem($bytes, $offset);
            }
            return $array;
        }
        if ($major === 5) {
            $map = [];
            for ($i = 0; $i < $length; $i++) {
                $key = $this->decodeCborItem($bytes, $offset);
                $map[$key] = $this->decodeCborItem($bytes, $offset);
            }
            return $map;
        }
        if ($major === 7) {
            return match ($additional) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new \RuntimeException('CBOR simple value no soportado.'),
            };
        }

        throw new \RuntimeException('CBOR type no soportado.');
    }

    private function cborLength(string $bytes, int &$offset, int $additional): int
    {
        if ($additional < 24) {
            return $additional;
        }
        $sizes = [24 => 1, 25 => 2, 26 => 4, 27 => 8];
        if (!isset($sizes[$additional])) {
            throw new \RuntimeException('CBOR length no soportado.');
        }
        $size = $sizes[$additional];
        $raw = substr($bytes, $offset, $size);
        $offset += $size;
        if ($size === 1) {
            return ord($raw);
        }
        if ($size === 2) {
            return unpack('n', $raw)[1];
        }
        if ($size === 4) {
            return unpack('N', $raw)[1];
        }
        $parts = unpack('Nhigh/Nlow', $raw);
        if ($parts['high'] !== 0) {
            throw new \RuntimeException('CBOR length demasiado largo.');
        }
        return $parts['low'];
    }

    private function requireString(array $source, string $key): string
    {
        if (empty($source[$key]) || !is_string($source[$key])) {
            throw new \RuntimeException("Campo {$key} inválido.");
        }
        return $source[$key];
    }

    private function requireArray(array $source, string $key): array
    {
        if (empty($source[$key]) || !is_array($source[$key])) {
            throw new \RuntimeException("Campo {$key} inválido.");
        }
        return $source[$key];
    }

    private function rpName(): string
    {
        return trim($this->config['webauthn']['rp_name'] ?? '') ?: 'jeftezmont.me';
    }

    private function rpId(): string
    {
        return trim($this->config['webauthn']['rp_id'] ?? '') ?: parse_url($this->config['app_url'] ?? '', PHP_URL_HOST) ?: 'localhost';
    }

    private function timeout(): int
    {
        return max(15000, (int) ($this->config['webauthn']['timeout'] ?? 60000));
    }

    private function originAllowed(string $origin): bool
    {
        $origins = $this->config['webauthn']['origins'] ?? [];
        return is_array($origins) && in_array($origin, $origins, true);
    }

    private function randomBase64Url(int $bytes): string
    {
        return $this->base64UrlEncode(random_bytes($bytes));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Base64url inválido.');
        }
        return $decoded;
    }

    private function derSequence(string $value): string
    {
        return "\x30" . $this->derLength(strlen($value)) . $value;
    }

    private function derBitString(string $value): string
    {
        return "\x03" . $this->derLength(strlen($value) + 1) . "\x00" . $value;
    }

    private function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->derLength(strlen($value)) . $value;
    }

    private function derOid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $body = chr($parts[0] * 40 + $parts[1]);
        for ($i = 2; $i < count($parts); $i++) {
            $value = $parts[$i];
            $encoded = chr($value & 0x7f);
            while ($value >>= 7) {
                $encoded = chr(($value & 0x7f) | 0x80) . $encoded;
            }
            $body .= $encoded;
        }
        return "\x06" . $this->derLength(strlen($body)) . $body;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
