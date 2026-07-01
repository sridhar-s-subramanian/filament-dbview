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
     * Every table the query references (best-effort) must be present in the
     * registry the given user may view. Deny-unknown.
     */
    public function assertInScope(SqlAnalyzer $analysis, mixed $user = null): void
    {
        $registry = $this->discovery->registry()->visibleTo($user);

        foreach ($analysis->tables as $table) {
            if (! $registry->has($table)) {
                throw UnsafeQueryException::tableNotAllowed($table);
            }
        }
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
            $this->assertInScope($analysis, $user);
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
     * Only identifiers appearing after FROM/JOIN or as a `table.` qualifier are
     * rewritten, so string literals and column names are left untouched.
     */
    private function applyTablePrefixes(string $sql, SqlAnalyzer $analysis, ?string $connection): string
    {
        $registry = $this->discovery->registry();

        foreach ($analysis->tables as $logical) {
            $info = $registry->get($logical);

            if ($info === null) {
                continue;
            }

            $prefix = $this->connections->connection($info->connection ?? $connection)->getTablePrefix();

            if ($prefix === '') {
                continue;
            }

            $physical = $prefix . $logical;
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
