<?php

namespace App\Services;

final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(string $secret, string $code, int $window = 1, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) return null;
        $step = intdiv($now ?? time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $step + $offset;
            if (hash_equals($this->codeAt($secret, $candidate), $code)) return $candidate;
        }
        return null;
    }

    public function codeAt(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N2', intdiv($step, 0x100000000), $step & 0xffffffff);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $output;
    }

    private function base32Decode(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value) ?? '')) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) throw new \InvalidArgumentException('Secreto TOTP inválido.');
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $output .= chr(bindec($chunk));
        return $output;
    }
}
