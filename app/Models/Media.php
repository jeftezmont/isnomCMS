<?php

namespace App\Models;

use PDO;

final class Media
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM media ORDER BY created_at DESC')->fetchAll();
    }

    public function store(array $file, int $userId): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] > $this->config['max_upload_bytes']) {
            throw new \RuntimeException('La imagen supera el tamaño permitido.');
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Tipo de archivo no permitido.');
        }
        $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!is_dir($this->config['upload_dir'])) {
            mkdir($this->config['upload_dir'], 0755, true);
        }
        $target = $this->config['upload_dir'] . '/' . $name;
        if (!$this->optimizeUpload($file['tmp_name'], $target, $mime) && !move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('No se pudo guardar la imagen.');
        }
        $url = $this->config['upload_url'] . '/' . $name;
        $stmt = $this->pdo->prepare('INSERT INTO media (filename, original_name, mime_type, size_bytes, url, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$name, basename($file['name']), $mime, is_file($target) ? filesize($target) : (int) $file['size'], $url, $userId]);
        return $url;
    }

    private function optimizeUpload(string $source, string $target, string $mime): bool
    {
        if (!function_exists('imagecreatetruecolor') || $mime === 'image/gif') {
            return false;
        }

        $image = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($source) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1800;
        $scale = min(1, $max / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $target, 82),
            'image/png' => imagepng($canvas, $target, 7),
            'image/webp' => imagewebp($canvas, $target, 82),
            default => false,
        };
        imagedestroy($image);
        imagedestroy($canvas);
        return (bool) $saved;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT filename FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $filename = $stmt->fetchColumn();
        if ($filename) {
            $path = $this->config['upload_dir'] . '/' . $filename;
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
    }
}
