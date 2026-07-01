<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Discovers the host application's Eloquent models and maps each to its
 * database table, connection, columns and foreign keys. The resulting
 * {@see TableRegistry} is the allowlist that scopes the entire viewer.
 */
final class ModelDiscovery
{
    private ?TableRegistry $memo = null;

    public function registry(): TableRegistry
    {
        if ($this->memo instanceof TableRegistry) {
            return $this->memo;
        }

        $cache = config('filament-dbview.models.cache');

        if (! ($cache['enabled'] ?? false)) {
            return $this->memo = $this->build();
        }

        /** @var array<int, array<string, mixed>> $payload */
        $payload = $this->cache()->remember(
            $cache['key'],
            $cache['ttl'],
            fn(): array => array_map(
                static fn(TableInfo $info): array => $info->toArray(),
                array_values($this->build()->all()),
            ),
        );

        $tables = [];

        foreach ($payload as $row) {
            /** @var array{model: class-string, table: string, connection: string|null, keyName: string|null, columns: list<string>, foreignKeys: list<array{column: string, foreign_table: string, foreign_column: string}>} $row */
            $info = TableInfo::fromArray($row);
            $tables[$info->table] = $info;
        }

        return $this->memo = new TableRegistry($tables);
    }

    public function forget(): void
    {
        $this->memo = null;

        $cache = config('filament-dbview.models.cache');

        if (is_array($cache) && isset($cache['key'])) {
            $this->cache()->forget($cache['key']);
        }
    }

    public function build(): TableRegistry
    {
        $exclude = (array) config('filament-dbview.models.exclude', []);
        $tables = [];

        foreach ($this->discoverModelClasses() as $class) {
            if (in_array($class, $exclude, true)) {
                continue;
            }

            $info = $this->describe($class);

            if ($info instanceof TableInfo) {
                // Later models never clobber an already-mapped table.
                $tables[$info->table] ??= $info;
            }
        }

        return new TableRegistry($tables);
    }

    private function describe(string $class): ?TableInfo
    {
        try {
            /** @var Model $model */
            $model = new $class();

            $connection = $model->getConnectionName();
            $table = $model->getTable();

            $columns = Schema::connection($connection)->getColumnListing($table);

            if ($columns === []) {
                return null;
            }

            return new TableInfo(
                model: $class,
                table: $table,
                connection: $connection,
                keyName: $model->getKeyName(),
                columns: $columns,
                foreignKeys: $this->foreignKeysFor($connection, $table),
            );
        } catch (Throwable) {
            // A model whose table/connection cannot be introspected is simply
            // not browsable; never surface schema errors during discovery.
            return null;
        }
    }

    /**
     * @return list<array{column: string, foreign_table: string, foreign_column: string}>
     */
    private function foreignKeysFor(?string $connection, string $table): array
    {
        try {
            $raw = Schema::connection($connection)->getForeignKeys($table);
        } catch (Throwable) {
            return [];
        }

        $keys = [];

        foreach ($raw as $fk) {
            $columns = $fk['columns'] ?? [];
            $foreignColumns = $fk['foreign_columns'] ?? [];
            $foreignTable = $fk['foreign_table'] ?? null;

            if ($columns === [] || $foreignTable === null) {
                continue;
            }

            $keys[] = [
                'column' => (string) $columns[0],
                'foreign_table' => (string) $foreignTable,
                'foreign_column' => (string) ($foreignColumns[0] ?? 'id'),
            ];
        }

        return $keys;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function discoverModelClasses(): array
    {
        $paths = array_filter(
            (array) config('filament-dbview.models.paths', []),
            static fn(string $path): bool => is_dir($path),
        );

        if ($paths === []) {
            return [];
        }

        $classes = [];

        foreach (Finder::create()->files()->in($paths)->name('*.php') as $file) {
            $class = $this->classNameFromFile($file->getRealPath());

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            $classes[$class] = $class;
        }

        return array_values($classes);
    }

    private function classNameFromFile(string $path): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $namespace = '';
        $class = null;
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i);
            }

            if ($token[0] === T_CLASS) {
                // Skip `::class` and anonymous classes.
                $prev = $this->previousMeaningful($tokens, $i);

                if ($prev === T_DOUBLE_COLON || $prev === T_NEW) {
                    continue;
                }

                $class = $this->readClassName($tokens, $i);

                if ($class !== null) {
                    break;
                }
            }
        }

        if ($class === null) {
            return null;
        }

        return $namespace === '' ? $class : $namespace . '\\' . $class;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function readNamespace(array $tokens, int $start): string
    {
        $namespace = '';
        $count = count($tokens);

        for ($i = $start + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === ';' || $token === '{') {
                break;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
            }
        }

        return trim($namespace, '\\');
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function readClassName(array $tokens, int $start): ?string
    {
        $count = count($tokens);

        for ($i = $start + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return null;
        }

        return null;
    }

    /**
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function previousMeaningful(array $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) ? $token[0] : null;
        }

        return null;
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $store = config('filament-dbview.models.cache.store');

        return Cache::store($store);
    }
}
