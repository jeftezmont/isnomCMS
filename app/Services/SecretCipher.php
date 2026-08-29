<?php

namespace App\Services;

final class SecretCipher
{
    private string $key;

    public function __construct(string $appKey)
    {
        if (strlen(trim($appKey)) < 32) throw new \RuntimeException('APP_KEY no está configurada o es demasiado corta.');
        $this->key = sodium_crypto_generichash($appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $encoded): string
    {
        $value = base64_decode($encoded, true);
        if ($value === false || strlen($value) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('Secreto cifrado inválido.');
        $nonce = substr($value, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($value, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) throw new \RuntimeException('No se pudo descifrar el secreto 2FA.');
        return $plain;
    }
}
