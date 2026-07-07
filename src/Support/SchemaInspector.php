<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Database\Connection;
use Throwable;

/**
 * Introspects the columns, indexes and foreign keys of a table for the
 * Adminer-style "Show structure" view in the Query Runner.
 *
 * Introspection is live (not the cached registry) so it covers both model-backed
 * and connection-scope tables uniformly and captures composite keys / constraint
 * names that the discovery-time {@see TableInfo} FK list discards. It only ever
 * describes tables that are in scope for the current user — the same scope rules
 * the {@see ReadOnlyGuard} enforces — so it can never reveal a denied table.
 *
 * @phpstan-type ColumnInfo array{name: string, type: string, nullable: bool, default: string|null, auto_increment: bool, primary: bool}
 * @phpstan-type IndexInfo array{name: string, columns: list<string>, unique: bool, primary: bool}
 * @phpstan-type ForeignKeyInfo array{columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null, on_update: string|null}
 * @phpstan-type TableSchema array{table: string, label: string, columns: list<ColumnInfo>, indexes: list<IndexInfo>, foreignKeys: list<ForeignKeyInfo>}
 */
final class SchemaInspector
{
    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ConnectionResolver $connections,
    ) {}

    /**
     * Describe every in-scope table in $tableNames (columns, indexes, FKs).
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

            $logicalTable = $inModels ? $info->table : $table;
            $dbConnection = $this->connections->connection($tableConnection);
            $physicalTable = $this->connections->physicalTableName($tableConnection, $logicalTable);

            $schemas[] = [
                'table' => $table,
                'label' => $inModels ? $info->label() : $table,
                ...$this->describe($dbConnection, $physicalTable),
            ];
        }

        return $schemas;
    }

    /**
     * Introspect a table by its PHYSICAL name with the connection prefix
     * temporarily cleared. Laravel's schema builder always prepends the
     * connection prefix, which is wrong for databases that mix prefixed and
     * non-prefixed tables (the physical name already encodes the prefix, if
     * any). The prefix is always restored.
     *
     * @return array{columns: list<ColumnInfo>, indexes: list<IndexInfo>, foreignKeys: list<ForeignKeyInfo>}
     */
    private function describe(Connection $connection, string $table): array
    {
        $prefix = $connection->getTablePrefix();
        $connection->setTablePrefix('');

        try {
            $indexes = $this->indexesFor($connection, $table);

            return [
                'columns' => $this->columnsFor($connection, $table, $this->primaryColumns($indexes)),
                'indexes' => $indexes,
                'foreignKeys' => $this->foreignKeysFor($connection, $table),
            ];
        } finally {
            $connection->setTablePrefix($prefix);
        }
    }

    /**
     * The set (lower-cased) of columns that make up the table's primary key,
     * derived from the introspected indexes.
     *
     * @param  list<array{name: string, columns: list<string>, unique: bool, primary: bool}>  $indexes
     * @return array<string, true>
     */
    private function primaryColumns(array $indexes): array
    {
        $primary = [];

        foreach ($indexes as $index) {
            if (! $index['primary']) {
                continue;
            }

            foreach ($index['columns'] as $column) {
                $primary[strtolower($column)] = true;
            }
        }

        return $primary;
    }

    /**
     * @param  array<string, true>  $primaryColumns
     * @return list<array{name: string, type: string, nullable: bool, default: string|null, auto_increment: bool, primary: bool}>
     */
    private function columnsFor(Connection $connection, string $table, array $primaryColumns): array
    {
        try {
            $raw = $connection->getSchemaBuilder()->getColumns($table);
        } catch (Throwable) {
            return [];
        }

        $columns = [];

        foreach ($raw as $column) {
            $name = (string) $column['name'];

            $columns[] = [
                'name' => $name,
                'type' => (string) $column['type'],
                'nullable' => (bool) $column['nullable'],
                'default' => $column['default'] !== null ? (string) $column['default'] : null,
                'auto_increment' => $column['auto_increment'],
                'primary' => isset($primaryColumns[strtolower($name)]),
            ];
        }

        return $columns;
    }

    /**
     * @return list<array{name: string, columns: list<string>, unique: bool, primary: bool}>
     */
    private function indexesFor(Connection $connection, string $table): array
    {
        try {
            $raw = $connection->getSchemaBuilder()->getIndexes($table);
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
    private function foreignKeysFor(Connection $connection, string $table): array
    {
        try {
            $raw = $connection->getSchemaBuilder()->getForeignKeys($table);
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
