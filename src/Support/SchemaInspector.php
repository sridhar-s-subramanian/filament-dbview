<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Introspects the indexes and foreign keys of the tables referenced by a query,
 * for the Adminer-style schema panel shown under the Query Runner results.
 *
 * Introspection is live (not the cached registry) so it covers both model-backed
 * and connection-scope tables uniformly and captures composite keys / constraint
 * names that the discovery-time {@see TableInfo} FK list discards. It only ever
 * describes tables that are in scope for the current user — the same scope rules
 * the {@see ReadOnlyGuard} enforces — so it can never reveal a denied table.
 *
 * @phpstan-type IndexInfo array{name: string, columns: list<string>, unique: bool, primary: bool}
 * @phpstan-type ForeignKeyInfo array{columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null, on_update: string|null}
 * @phpstan-type TableSchema array{table: string, label: string, indexes: list<IndexInfo>, foreignKeys: list<ForeignKeyInfo>}
 */
final class SchemaInspector
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ConnectionResolver $connections,
    ) {}

    /**
     * Describe every in-scope table in $tableNames (indexes + foreign keys).
     *
     * @param  list<string>  $tableNames  logical table names (as parsed from SQL)
     * @return list<TableSchema>
     */
    public function for(array $tableNames, ?string $connection = null, mixed $user = null): array
    {
        $registry = $this->discovery->registry()->visibleTo($user);
        $scope = (string) config('filament-dbview.query_runner.scope', 'models');
        $deny = array_map('strtolower', (array) config('filament-dbview.query_runner.deny', []));

        $schemas = [];
        $seen = [];

        foreach ($tableNames as $table) {
            $key = strtolower($table);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $info = $registry->get($table);
            $inModels = $info instanceof TableInfo;

            // The table's owning connection (model tables carry their own).
            $tableConnection = $info->connection ?? $connection;

            // Scope check, matching ReadOnlyGuard::assertInScope so the panel can
            // never describe a table the runner would refuse.
            if ($scope === 'connection') {
                $exists = $inModels || $this->connections->hasTable($connection, $table);
                $denied = in_array($key, $deny, true)
                    || in_array(strtolower($this->connections->physicalTableName($tableConnection, $table)), $deny, true);

                if (! $exists || $denied) {
                    continue;
                }
            } elseif (! $inModels) {
                continue;
            }

            // Schema builder methods (getIndexes/getForeignKeys) prepend the
            // connection's table prefix themselves, so they take the LOGICAL
            // (unprefixed) table name on its own connection — exactly as
            // ModelDiscovery does. Passing an already-prefixed name would
            // double-prefix and silently match nothing.
            $logicalTable = $inModels ? $info->table : $table;

            $schemas[] = [
                'table' => $table,
                'label' => $inModels ? $info->label() : $table,
                'indexes' => $this->indexesFor($tableConnection, $logicalTable),
                'foreignKeys' => $this->foreignKeysFor($tableConnection, $logicalTable),
            ];
        }

        return $schemas;
    }

    /**
     * @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}>
     */
    private function indexesFor(?string $connection, string $table): array
    {
        try {
            $raw = Schema::connection($connection)->getIndexes($table);
        } catch (Throwable) {
            return [];
        }

        $indexes = [];

        foreach ($raw as $index) {
            $indexes[] = [
                'name' => (string) $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'],
            ];
        }

        return $indexes;
    }

    /**
     * @return list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null, on_update: string|null}>
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
            $columns = (array) ($fk['columns'] ?? []);
            $foreignTable = $fk['foreign_table'] ?? null;

            if ($columns === [] || $foreignTable === null) {
                continue;
            }

            $keys[] = [
                'columns' => array_values(array_map('strval', $columns)),
                'foreign_table' => (string) $foreignTable,
                'foreign_columns' => array_values(array_map('strval', (array) ($fk['foreign_columns'] ?? []))),
                'on_delete' => isset($fk['on_delete']) ? (string) $fk['on_delete'] : null,
                'on_update' => isset($fk['on_update']) ? (string) $fk['on_update'] : null,
            ];
        }

        return $keys;
    }
}
