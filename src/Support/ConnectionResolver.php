<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Resolves which physical database connection the viewer should use, honouring
 * the optional dedicated read-only connection override, and applies a
 * per-statement timeout where the driver supports it.
 */
final class ConnectionResolver
{
    /** @var array<string, list<string>> */
    private array $tableCache = [];

    /** @var array<string, array<string, string>> */
    private array $logicalToPhysicalCache = [];

    public function __construct(private readonly ModelDiscovery $discovery) {}

    /**
     * The logical (unprefixed) table names on the given connection, used for the
     * query runner's "connection" scope table list. getTables() is prefix-
     * inconsistent across drivers (MySQL strips the prefix, SQLite does not), so
     * we normalise by stripping the connection prefix ourselves. Memoised.
     *
     * @return list<string>
     */
    public function tables(?string $connection): array
    {
        $name = $this->physicalName($connection);

        if (isset($this->tableCache[$name])) {
            return $this->tableCache[$name];
        }

        try {
            $prefix = DB::connection($name)->getTablePrefix();
            $rawTables = Schema::connection($name)->getTables();

            $tables = [];
            $mapping = [];

            foreach ($rawTables as $table) {
                $raw = (string) $table['name'];
                $logical = ($prefix !== '' && str_starts_with($raw, $prefix))
                    ? substr($raw, strlen($prefix))
                    : $raw;

                $tables[] = $logical;
                $mapping[strtolower($logical)] = $raw;
            }

            $this->logicalToPhysicalCache[$name] = $mapping;
        } catch (Throwable) {
            $tables = [];
            $this->logicalToPhysicalCache[$name] = [];
        }

        return $this->tableCache[$name] = $tables;
    }

    /**
     * Map a logical table name to its physical table name on the connection.
     */
    public function physicalTableName(?string $connection, string $table): string
    {
        $name = $this->physicalName($connection);

        // Ensure tables() has been called to populate cache.
        $this->tables($connection);

        $mapping = $this->logicalToPhysicalCache[$name] ?? [];
        $lowerTable = strtolower($table);

        if (isset($mapping[$lowerTable])) {
            return $mapping[$lowerTable];
        }

        // Fallback: if not found in physical tables list, default to prepending connection prefix.
        try {
            $prefix = DB::connection($name)->getTablePrefix();
        } catch (Throwable) {
            $prefix = '';
        }

        return $prefix . $table;
    }

    /**
     * Whether a logical table name exists on the connection. Checks the memoised
     * list of logical tables resolved from the physical schema, ensuring prefix-resilience.
     */
    public function hasTable(?string $connection, string $table): bool
    {
        $name = $this->physicalName($connection);

        // Ensure tables() has been called to populate cache.
        $this->tables($connection);

        return isset($this->logicalToPhysicalCache[$name][strtolower($table)]);
    }

    /**
     * The app connection names the viewer is permitted to query. When
     * `connections.allowed` is null, this is derived from the registry so the
     * viewer only ever sees connections that actually back a model.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $configured = config('filament-dbview.connections.allowed');

        if (is_array($configured)) {
            return array_values(array_map('strval', $configured));
        }

        $names = [];

        foreach ($this->discovery->registry()->all() as $info) {
            $name = $info->connection ?? (string) config('database.default');
            $names[$name] = $name;
        }

        return array_values($names);
    }

    public function isAllowed(?string $connection): bool
    {
        $name = $connection ?? (string) config('database.default');

        return in_array($name, $this->allowed(), true);
    }

    /**
     * Map a requested (app) connection to the physical connection to run on,
     * substituting the configured read-only connection when one exists.
     */
    public function physicalName(?string $requested): string
    {
        $name = $requested ?? (string) config('database.default');

        /** @var array<string, string> $map */
        $map = (array) config('filament-dbview.connections.read_only', []);

        return $map[$name] ?? $name;
    }

    public function connection(?string $requested): Connection
    {
        return DB::connection($this->physicalName($requested));
    }

    /**
     * Apply a best-effort statement timeout to the connection for the current
     * query. Silently ignored on drivers that do not support it.
     */
    public function applyTimeout(Connection $connection): void
    {
        $timeout = (int) config('filament-dbview.limits.timeout', 0);

        if ($timeout <= 0) {
            return;
        }

        try {
            match ($connection->getDriverName()) {
                'mysql', 'mariadb' => $connection->statement(
                    'SET SESSION MAX_EXECUTION_TIME=' . ($timeout * 1000),
                ),
                'pgsql' => $connection->statement(
                    "SET LOCAL statement_timeout='" . ($timeout * 1000) . "'",
                ),
                default => null,
            };
        } catch (Throwable) {
            // Timeout is a hardening nicety; never fail the request over it.
        }
    }
}
