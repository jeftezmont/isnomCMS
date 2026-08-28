<?php

namespace App\Services;

use App\Core\ErrorHandler;
use PDO;

final class DatabaseSchemaService
{
    private ?array $definitions = null;

    public function __construct(private string $schemaPath)
    {
    }

    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $sql = is_file($this->schemaPath) ? file_get_contents($this->schemaPath) : false;
        if (!is_string($sql)) {
            return $this->definitions = [];
        }

        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=.*?;/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        $tables = [];
        foreach ($matches as $match) {
            $columns = [];
            $indexes = [];
            foreach (preg_split('/\R/', $match[2]) ?: [] as $line) {
                $definition = rtrim(ltrim(trim($line), ','), ',');
                if ($definition === '') {
                    continue;
                }
                if (preg_match('/^(?:(?:UNIQUE|FULLTEXT)\s+)?(?:KEY|INDEX)\s+`?([a-z0-9_]+)`?\s*\(/i', $definition, $indexMatch)) {
                    $indexes[$indexMatch[1]] = $definition;
                    continue;
                }
                if (preg_match('/^(?:PRIMARY|CONSTRAINT|FOREIGN|CHECK)\b/i', $definition)) {
                    continue;
                }
                if (preg_match('/^`?([a-z0-9_]+)`?\s+/i', $definition, $columnMatch)) {
                    $columns[$columnMatch[1]] = $definition;
                }
            }

            $tables[$match[1]] = [
                'create' => trim($match[0]),
                'columns' => $columns,
                'indexes' => $indexes,
            ];
        }

        return $this->definitions = $tables;
    }

    public function expectedColumns(): array
    {
        $columns = [];
        foreach ($this->definitions() as $table => $definition) {
            $columns[$table] = array_keys($definition['columns']);
        }
        return $columns;
    }

    public function inspect(PDO $pdo): array
    {
        $definitions = $this->definitions();
        $existingTables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        $tables = [];

        foreach ($definitions as $table => $definition) {
            $exists = in_array($table, $existingTables, true);
            $missingColumns = [];
            $safeColumns = [];
            $manualColumns = [];
            $missingIndexes = [];

            if ($exists) {
                $actualColumns = array_column($pdo->query('SHOW COLUMNS FROM ' . $this->identifier($table))->fetchAll(), 'Field');
                $missingColumns = array_values(array_diff(array_keys($definition['columns']), $actualColumns));
                foreach ($missingColumns as $column) {
                    if ($this->isSafeColumnDefinition($definition['columns'][$column])) {
                        $safeColumns[] = $column;
                    } else {
                        $manualColumns[] = $column;
                    }
                }

                $actualIndexes = array_values(array_unique(array_column($pdo->query('SHOW INDEX FROM ' . $this->identifier($table))->fetchAll(), 'Key_name')));
                $missingIndexes = array_values(array_diff(array_keys($definition['indexes']), $actualIndexes));
            }

            $tables[$table] = compact('exists', 'missingColumns', 'safeColumns', 'manualColumns', 'missingIndexes');
        }

        $missingTables = array_keys(array_filter($tables, fn(array $table): bool => !$table['exists']));
        $safeColumnCount = array_sum(array_map(fn(array $table): int => count($table['safeColumns']), $tables));
        $manualColumnCount = array_sum(array_map(fn(array $table): int => count($table['manualColumns']), $tables));
        $missingIndexCount = array_sum(array_map(fn(array $table): int => count($table['missingIndexes']), $tables));

        return [
            'source_available' => $definitions !== [],
            'expected_count' => count($definitions),
            'existing_count' => count($definitions) - count($missingTables),
            'missing_tables' => $missingTables,
            'safe_column_count' => $safeColumnCount,
            'manual_column_count' => $manualColumnCount,
            'missing_index_count' => $missingIndexCount,
            'tables' => $tables,
            'complete' => $missingTables === [] && $safeColumnCount === 0 && $manualColumnCount === 0 && $missingIndexCount === 0,
        ];
    }

    public function applySafeUpdates(PDO $pdo): array
    {
        $definitions = $this->definitions();
        $before = $this->inspect($pdo);
        $applied = [];
        $skipped = [];
        $errors = [];

        foreach ($definitions as $table => $definition) {
            $tableState = $before['tables'][$table] ?? null;
            if (!$tableState) {
                continue;
            }

            if (!$tableState['exists']) {
                try {
                    $statement = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $definition['create'], 1);
                    $pdo->exec((string) $statement);
                    $applied[] = 'Tabla creada: ' . $table;
                } catch (\Throwable $exception) {
                    $errorId = ErrorHandler::report($exception, 'setup-schema');
                    $errors[] = 'No se pudo crear ' . $table . '. Error ID: ' . $errorId;
                }
                continue;
            }

            foreach ($tableState['safeColumns'] as $column) {
                try {
                    $pdo->exec('ALTER TABLE ' . $this->identifier($table) . ' ADD COLUMN ' . $definition['columns'][$column]);
                    $applied[] = 'Columna añadida: ' . $table . '.' . $column;
                } catch (\Throwable $exception) {
                    $errorId = ErrorHandler::report($exception, 'setup-schema');
                    $errors[] = 'No se pudo añadir ' . $table . '.' . $column . '. Error ID: ' . $errorId;
                }
            }

            foreach ($tableState['manualColumns'] as $column) {
                $skipped[] = 'Revisión manual necesaria: ' . $table . '.' . $column;
            }
        }

        $afterColumns = $this->inspect($pdo);
        foreach ($definitions as $table => $definition) {
            if (empty($afterColumns['tables'][$table]['exists'])) {
                continue;
            }
            foreach ($afterColumns['tables'][$table]['missingIndexes'] as $index) {
                $indexColumns = $this->indexColumns($definition['indexes'][$index]);
                if (array_intersect($indexColumns, $afterColumns['tables'][$table]['missingColumns']) !== []) {
                    $skipped[] = 'Índice pendiente de revisión: ' . $table . '.' . $index;
                    continue;
                }
                try {
                    $pdo->exec('ALTER TABLE ' . $this->identifier($table) . ' ADD ' . $definition['indexes'][$index]);
                    $applied[] = 'Índice añadido: ' . $table . '.' . $index;
                } catch (\Throwable $exception) {
                    $errorId = ErrorHandler::report($exception, 'setup-schema');
                    $errors[] = 'No se pudo añadir el índice ' . $table . '.' . $index . '. Error ID: ' . $errorId;
                }
            }
        }

        return [
            'applied' => $applied,
            'skipped' => $skipped,
            'errors' => $errors,
            'state' => $this->inspect($pdo),
        ];
    }

    private function isSafeColumnDefinition(string $definition): bool
    {
        if (preg_match('/\bPRIMARY\s+KEY\b|\bAUTO_INCREMENT\b/i', $definition)) {
            return false;
        }
        return !preg_match('/\bNOT\s+NULL\b/i', $definition) || preg_match('/\bDEFAULT\b/i', $definition) === 1;
    }

    private function identifier(string $value): string
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $value)) {
            throw new \InvalidArgumentException('Identificador SQL inválido.');
        }
        return '`' . $value . '`';
    }

    private function indexColumns(string $definition): array
    {
        if (!preg_match('/\((.*?)\)/', $definition, $match)) {
            return [];
        }
        $columns = [];
        foreach (explode(',', $match[1]) as $column) {
            if (preg_match('/`?([a-z0-9_]+)`?/i', trim($column), $columnMatch)) {
                $columns[] = $columnMatch[1];
            }
        }
        return $columns;
    }
}
