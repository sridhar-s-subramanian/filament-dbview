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

    public function __construct(private readonly ModelDiscovery $discovery) {}

    /**
     * The real (physical) table names present on the given connection. Used by
     * the query runner's "connection" scope. Memoised per request.
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
            $tables = array_map(
                static fn(array $table): string => (string) $table['name'],
                Schema::connection($name)->getTables(),
            );
        } catch (Throwable) {
            $tables = [];
        }

        return $this->tableCache[$name] = $tables;
    }

    public function hasTable(?string $connection, string $table): bool
    {
        return in_array($table, $this->tables($connection), true);
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
