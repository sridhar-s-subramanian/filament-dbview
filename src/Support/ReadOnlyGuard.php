<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Database\Connection;
use SridharSSubramanian\FilamentDbview\Exceptions\RollbackSignal;
use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;

/**
 * The single gate every raw query passes through. Enforces read-only access in
 * depth (OWASP A03 injection / write-prevention):
 *
 *   1. Lexical allowlist  — single SELECT/WITH statement, no stacked queries,
 *                           no executable comments, no write/DDL/file/DoS tokens.
 *   2. Table scope        — every referenced table must be in the model
 *                           allowlist the current user is permitted to see.
 *   3. Enforced LIMIT     — the query is wrapped and hard-capped.
 *   4. Rolled-back txn     — executed inside a transaction that always rolls
 *                           back, so nothing can persist even if a layer above
 *                           were bypassed.
 *   5. Read-only conn      — optionally routed through a SELECT-only database
 *                           user (configured in ConnectionResolver).
 *
 * Every attempt is audited.
 */
final class ReadOnlyGuard
{
    private readonly Redactor $redactor;

    public function __construct(
        private readonly ModelDiscovery $discovery,
        private readonly ConnectionResolver $connections,
        private readonly QueryAuditor $auditor,
        ?Redactor $redactor = null,
    ) {
        $this->redactor = $redactor ?? new Redactor();
    }

    /**
     * Validate the statement is a safe, single, read-only SELECT. Throws
     * {@see UnsafeQueryException} otherwise. Returns the analysis for reuse.
     */
    public function assertSafe(string $sql): SqlAnalyzer
    {
        $sql = trim($sql);

        if ($sql === '') {
            throw UnsafeQueryException::empty();
        }

        $analysis = SqlAnalyzer::of($sql);

        if ($analysis->hasExecutableComment) {
            throw UnsafeQueryException::executableComment();
        }

        if ($analysis->hasStackedStatement) {
            throw UnsafeQueryException::multipleStatements();
        }

        if (! $analysis->isSelect()) {
            throw UnsafeQueryException::notSelect();
        }

        $forbidden = $analysis->forbiddenTokens();

        if ($forbidden !== []) {
            throw UnsafeQueryException::forbiddenKeyword($forbidden[0]);
        }

        return $analysis;
    }

    /**
     * Every referenced table must be in scope. In the default "models" scope
     * that means present in the model registry the user may view. In the opt-in
     * "connection" scope any real table on the connection is allowed (referenced
     * by its real name; model tables also accept their logical name), except
     * those on the deny list. Deny-unknown either way.
     */
    public function assertInScope(SqlAnalyzer $analysis, mixed $user = null, ?string $connection = null): void
    {
        $registry = $this->discovery->registry()->visibleTo($user);
        $scope = (string) config('filament-dbview.query_runner.scope', 'models');
        $deny = array_map('strtolower', (array) config('filament-dbview.query_runner.deny', []));

        foreach ($analysis->tables as $table) {
            $inModels = $registry->has($table);

            if ($scope === 'connection') {
                $exists = $inModels || $this->connections->hasTable($connection, $table);
                $denied = in_array(strtolower($table), $deny, true)
                    || in_array(strtolower($this->physicalTableName($table, $connection)), $deny, true);

                if (! $exists || $denied) {
                    throw UnsafeQueryException::tableNotAllowed($table);
                }

                continue;
            }

            if (! $inModels) {
                throw UnsafeQueryException::tableNotAllowed($table);
            }
        }
    }

    /**
     * The real (physical) table name for a referenced identifier. Laravel's
     * schema tools operate in logical (unprefixed) space, so every table on a
     * prefixed connection is physically prefixed — model-backed or not. Model
     * tables use their own connection; others use the query's connection.
     */
    private function physicalTableName(string $table, ?string $connection): string
    {
        $info = $this->discovery->registry()->get($table);

        return $this->connections->physicalTableName($info->connection ?? $connection, $table);
    }

    /**
     * Full pipeline: validate, scope-check, cap, and execute read-only.
     */
    public function run(string $sql, ?string $connection = null, ?int $limit = null, mixed $user = null): ResultSet
    {
        $sql = trim($sql);
        $physical = $this->connections->physicalName($connection);

        try {
            $analysis = $this->assertSafe($sql);
            $this->assertInScope($analysis, $user, $connection);
        } catch (UnsafeQueryException $e) {
            $this->auditor->record($sql, $physical, null, 0.0, false, $e->getMessage());

            throw $e;
        }

        $limit = $this->resolveLimit($limit);

        // Raw SQL bypasses Eloquent's automatic table-prefixing, so translate
        // the logical model table names the user typed (as shown in the browser)
        // into their real prefixed names before execution.
        $executable = $this->applyTablePrefixes($sql, $analysis, $connection);
        $wrapped = $this->wrapWithLimit($executable, $limit);

        $start = microtime(true);
        $rows = $this->executeReadOnly($this->connections->connection($connection), $wrapped);
        $durationMs = (microtime(true) - $start) * 1000;

        $result = ResultSet::fromRows(
            rows: $rows,
            redactor: $this->redactor,
            connection: $physical,
            durationMs: $durationMs,
            maxBytes: (int) config('filament-dbview.limits.max_result_bytes', 5 * 1024 * 1024),
        );

        $this->auditor->record($sql, $physical, $result->rowCount, $durationMs, true);

        return $result;
    }

    /**
     * Replace referenced logical table names with their real (prefixed) names.
     * Laravel's schema layer is prefix-transparent, so raw execution must prefix
     * every referenced table — model-backed or not. Only identifiers after
     * FROM/JOIN or as a `table.` qualifier are rewritten, so string literals and
     * column names are left untouched.
     */
    private function applyTablePrefixes(string $sql, SqlAnalyzer $analysis, ?string $connection): string
    {
        foreach ($analysis->tables as $logical) {
            $physical = $this->physicalTableName($logical, $connection);

            if ($physical === $logical) {
                continue;
            }

            $quoted = preg_quote($logical, '/');

            // `FROM logical` / `JOIN logical` (optionally back-ticked).
            $sql = (string) preg_replace(
                '/(\b(?:FROM|JOIN)\s+`?)' . $quoted . '\b/i',
                '${1}' . $physical,
                $sql,
            );

            // Qualified references: `logical.column`.
            $sql = (string) preg_replace(
                '/(?<![\w.])' . $quoted . '(\s*\.)/i',
                $physical . '${1}',
                $sql,
            );
        }

        return $sql;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function executeReadOnly(Connection $connection, string $wrapped): array
    {
        $rows = [];

        try {
            $connection->transaction(function () use ($connection, $wrapped, &$rows): void {
                $this->connections->applyTimeout($connection);

                $rows = array_map(
                    static fn(object $row): array => (array) $row,
                    $connection->select($wrapped),
                );

                // Fetch complete — force a rollback so nothing can ever persist.
                throw new RollbackSignal();
            });
        } catch (RollbackSignal) {
            // Expected: the transaction rolled back cleanly.
        }

        return array_values($rows);
    }

    private function resolveLimit(?int $limit): int
    {
        $default = (int) config('filament-dbview.limits.default_rows', 100);
        $max = (int) config('filament-dbview.limits.max_rows', 1000);

        $limit ??= $default;

        return max(1, min($limit, $max));
    }

    /**
     * Wrap the (already validated) SELECT as a subquery with a hard row cap.
     * $limit is a clamped internal integer, never user text, so inlining it is
     * injection-safe and avoids driver quirks with bound LIMIT parameters.
     */
    private function wrapWithLimit(string $sql, int $limit): string
    {
        $sql = rtrim(trim($sql), ';');

        return "SELECT * FROM ({$sql}) AS dbview_sub LIMIT {$limit}";
    }
}
