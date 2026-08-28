<?php

namespace App\Services;

final class PodcastAudioService
{
    private const MIME_EXTENSIONS = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/x-aac' => 'aac',
    ];

    public function __construct(private array $config) {}

    public function storeLocal(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $size = (int) ($file['size'] ?? 0);
        $limit = (int) ($this->config['max_audio_upload_bytes'] ?? 250 * 1024 * 1024);
        if ($size < 1 || $size > $limit) {
            throw new \RuntimeException('El audio está vacío o supera el tamaño máximo permitido.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file((string) $file['tmp_name']));
        if (!isset(self::MIME_EXTENSIONS[$mime])) {
            throw new \RuntimeException('El archivo no es MP3, AAC o M4A compatible.');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['mp3','aac','m4a'], true)) {
            throw new \RuntimeException('La extensión del audio no está permitida.');
        }
        $directory = (string) ($this->config['audio_upload_dir'] ?? ROOT_PATH . '/public/uploads/audio');
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException('No se pudo preparar el directorio de audio.');
        }
        if (!is_writable($directory)) {
            throw new \RuntimeException('El directorio de audio no es escribible.');
        }
        $filename = bin2hex(random_bytes(18)) . '.' . self::MIME_EXTENSIONS[$mime];
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new \RuntimeException('No se pudo guardar el archivo de audio.');
        }
        chmod($target, 0644);
        return [
            'source' => 'local',
            'local_path' => $target,
            'original_url' => null,
            'url' => rtrim((string) ($this->config['audio_upload_url'] ?? '/uploads/audio'), '/') . '/' . $filename,
            'mime_type' => $mime,
            'file_size' => (int) filesize($target),
        ];
    }

    public function validateDropbox(string $originalUrl): array
    {
        $direct = $this->normalizeDropboxUrl($originalUrl);
        $metadata = $this->remoteMetadata($direct);
        if (!isset(self::MIME_EXTENSIONS[$metadata['mime_type']])) {
            throw new \RuntimeException('Dropbox no devolvió un archivo de audio MP3, AAC o M4A compatible.');
        }
        if ($metadata['file_size'] < 1) {
            throw new \RuntimeException('Dropbox no permitió obtener Content-Length; el enclosure RSS no puede publicarse.');
        }
        return [
            'source' => 'dropbox',
            'local_path' => null,
            'original_url' => trim($originalUrl),
            'url' => $metadata['url'],
            'mime_type' => $metadata['mime_type'],
            'file_size' => $metadata['file_size'],
        ];
    }

    public function normalizeDropboxUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new \RuntimeException('La URL de Dropbox debe utilizar HTTPS.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['dropbox.com','www.dropbox.com','dl.dropboxusercontent.com'], true)) {
            throw new \RuntimeException('Utiliza un enlace compartido oficial de Dropbox.');
        }
        if (empty($parts['path']) || !str_contains((string) $parts['path'], '/s')) {
            throw new \RuntimeException('El enlace no parece ser un archivo compartido de Dropbox.');
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['dl']);
        $query['raw'] = '1';
        return 'https://www.dropbox.com' . $parts['path'] . '?' . http_build_query($query);
    }

    public function resolved(array $episode): array
    {
        return [
            'source' => (string) ($episode['audio_source'] ?? ''),
            'url' => (string) ($episode['audio_url'] ?? ''),
            'mime_type' => (string) ($episode['audio_mime_type'] ?? ''),
            'file_size' => (int) ($episode['audio_file_size'] ?? 0),
            'duration' => (string) ($episode['duration'] ?? ''),
        ];
    }

    private function remoteMetadata(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('La extensión cURL es necesaria para validar Dropbox.');
        }
        $current = $url;
        for ($redirects = 0; $redirects <= 4; $redirects++) {
            $resolved = $this->assertSafeRemoteUrl($current);
            $response = $this->requestHeaders($current, false, $resolved);
            if ($response['status'] >= 300 && $response['status'] < 400 && $response['location']) {
                $current = $this->absoluteUrl($current, $response['location']);
                continue;
            }
            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new \RuntimeException('Dropbox respondió con HTTP ' . $response['status'] . '.');
            }
            if ($response['file_size'] < 1) {
                $response = $this->requestHeaders($current, true, $resolved);
            }
            $response['url'] = $current;
            return $response;
        }
        throw new \RuntimeException('Dropbox superó el límite seguro de redirecciones.');
    }

    private function requestHeaders(string $url, bool $range, array $resolved): array
    {
        $headers = [];
        $received = 0;
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_NOBODY => !$range,
            CURLOPT_RESOLVE => [$resolved['host'] . ':443:' . $resolved['ip']],
            CURLOPT_USERAGENT => 'isnomCMS/1.0 Podcast Validator',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$headers): int {
                $trimmed = trim($line);
                if (str_contains($trimmed, ':')) {
                    [$name, $value] = array_map('trim', explode(':', $trimmed, 2));
                    $headers[strtolower($name)] = $value;
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$received): int {
                $received += strlen($chunk);
                return $received > 2048 ? 0 : strlen($chunk);
            },
        ];
        if ($range) $options[CURLOPT_RANGE] = '0-0';
        curl_setopt_array($curl, $options);
        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($curl);
        $curlMessage = curl_error($curl);
        curl_close($curl);
        if ($curlError && !($range && $curlError === CURLE_WRITE_ERROR && $received > 0)) {
            throw new \RuntimeException('No se pudo validar Dropbox: ' . $curlMessage);
        }
        $mime = strtolower(trim(explode(';', (string) ($headers['content-type'] ?? ''))[0]));
        $size = (int) ($headers['content-length'] ?? 0);
        if (!empty($headers['content-range']) && preg_match('#/(\d+)$#', $headers['content-range'], $match)) {
            $size = (int) $match[1];
        }
        return ['status' => $status, 'location' => $headers['location'] ?? null, 'mime_type' => $mime, 'file_size' => $size];
    }

    private function assertSafeRemoteUrl(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new \RuntimeException('Una redirección de Dropbox no utilizó HTTPS.');
        }
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $allowed = in_array($host, ['dropbox.com','www.dropbox.com','dl.dropboxusercontent.com'], true)
            || str_ends_with($host, '.dropboxusercontent.com');
        if (!$allowed || $host === 'localhost') {
            throw new \RuntimeException('Dropbox redirigió a un host no permitido.');
        }
        $ips = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
        }
        if ($ips === []) {
            throw new \RuntimeException('No se pudo resolver el host remoto.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('La URL remota resolvió a una red privada o reservada.');
            }
        }
        return ['host' => $host, 'ip' => $ips[0]];
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (str_starts_with($location, 'https://')) return $location;
        if (str_starts_with($location, '//')) return 'https:' . $location;
        $parts = parse_url($base);
        if (!is_array($parts) || !str_starts_with($location, '/')) {
            throw new \RuntimeException('Dropbox devolvió una redirección inválida.');
        }
        return 'https://' . $parts['host'] . $location;
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El audio supera el límite aceptado por PHP.',
            UPLOAD_ERR_PARTIAL => 'La subida del audio quedó incompleta.',
            UPLOAD_ERR_NO_FILE => 'Selecciona un archivo de audio.',
            default => 'No se pudo recibir el archivo de audio.',
        };
    }
}
