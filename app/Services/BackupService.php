<?php

namespace App\Services;

use PDO;

final class BackupService
{
    private const TABLES = [
        'categories',
        'tags',
        'posts',
        'post_tags',
        'media',
        'site_settings',
        'nav_links',
    ];

    public function __construct(private PDO $pdo, private array $config = [])
    {
    }

    public function summary(): array
    {
        $summary = [];
        foreach (self::TABLES as $table) {
            try {
                $exists = $this->tableExists($table);
                $summary[$table] = [
                    'exists' => $exists,
                    'rows' => $exists ? $this->countRows($table) : 0,
                ];
            } catch (\Throwable) {
                $summary[$table] = [
                    'exists' => false,
                    'rows' => 0,
                    'error' => true,
                ];
            }
        }
        return $summary;
    }

    public function json(): array
    {
        $tables = [];
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                $tables[$table] = [];
                continue;
            }
            $tables[$table] = $this->safeRows($table);
        }

        return [
            'generated_at' => date('c'),
            'generator' => 'isnomCMS',
            'format' => 'content-backup-v1',
            'tables' => $tables,
        ];
    }

    public function sql(): string
    {
        $lines = [
            '-- isnomCMS content backup',
            '-- Generated at ' . date('c'),
            'SET FOREIGN_KEY_CHECKS=0;',
        ];

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $lines[] = '';
            $lines[] = '-- Table: ' . $table;
            $create = $this->createStatement($table);
            if ($create) {
                $lines[] = $create . ';';
            }

            $rows = $this->safeRows($table);
            foreach ($rows as $row) {
                $columns = array_map(fn($column) => '`' . str_replace('`', '``', (string) $column) . '`', array_keys($row));
                $values = array_map(fn($value) => $value === null ? 'NULL' : $this->pdo->quote((string) $value), array_values($row));
                $lines[] = 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }
        }

        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        return implode("\n", $lines) . "\n";
    }

    public function zip(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extensión zip no está disponible.');
        }

        $path = tempnam(sys_get_temp_dir(), 'isnomcms-backup-');
        if ($path === false) {
            throw new \RuntimeException('No se pudo preparar el archivo temporal.');
        }
        $zipPath = $path . '.zip';
        rename($path, $zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el backup ZIP.');
        }

        $manifest = [
            'generated_at' => date('c'),
            'generator' => 'isnomCMS',
            'format' => 'backup-zip-v1',
            'contains' => ['database.sql', 'uploads/', 'manifest.json', 'README.txt'],
            'excluded' => ['.env', 'users data', 'passkeys', 'sessions', 'logs', 'secrets'],
        ];

        $zip->addFromString('database.sql', $this->sql());
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $zip->addFromString('README.txt', "Backup de contenido de isnomCMS.\n\nNo incluye .env, usuarios, passkeys, sesiones, logs ni secretos.\nImporta primero database.sql del proyecto base o ejecuta /admin/setup si tu base esta vacia.\n");
        $this->addUploads($zip);
        $zip->close();

        return $zipPath;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    private function createStatement(string $table): ?string
    {
        try {
            $stmt = $this->pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $create = (string) ($row['Create Table'] ?? '');
            return $create === '' ? null : preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $create);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeRows(string $table): array
    {
        $rows = $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll();
        foreach ($rows as &$row) {
            if ($table === 'posts' && array_key_exists('author_id', $row)) {
                $row['author_id'] = null;
            }
            if ($table === 'media' && array_key_exists('uploaded_by', $row)) {
                $row['uploaded_by'] = null;
            }
        }
        unset($row);
        return $rows;
    }

    private function addUploads(\ZipArchive $zip): void
    {
        $uploadDir = (string) ($this->config['upload_dir'] ?? '');
        if ($uploadDir === '' || !is_dir($uploadDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($uploadDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filename = $file->getFilename();
            if (str_starts_with($filename, '.') || preg_match('/\.(php|phtml|phar|cgi|pl|sh|log|env|sql)$/i', $filename)) {
                continue;
            }
            $relative = 'uploads/' . ltrim(str_replace($uploadDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), str_replace(DIRECTORY_SEPARATOR, '/', $relative));
        }
    }
}
