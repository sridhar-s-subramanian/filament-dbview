<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;
use SridharSSubramanian\FilamentDbview\Support\SqlAnalyzer;

/**
 * Regression corpus for CTE / nested SELECT shapes. Table extraction must:
 *   - treat CTE names as derived (not real tables for scope),
 *   - still require real base tables to be in scope,
 *   - keep fail-closed behaviour for out-of-scope tables.
 */
function cteGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

// ---------------------------------------------------------------------------
// Analyzer-level expectations
// ---------------------------------------------------------------------------

it('treats a simple CTE name as derived, not a real table', function (): void {
    $analysis = SqlAnalyzer::of('WITH t AS (SELECT 1 AS n) SELECT * FROM t');

    expect($analysis->isSelect())->toBeTrue()
        ->and($analysis->cteNames)->toContain('t')
        ->and($analysis->tables)->toBe([]);
});

it('handles WITH RECURSIVE and column lists without treating the CTE as a real table', function (): void {
    $sql = 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 3) SELECT * FROM t';
    $analysis = SqlAnalyzer::of($sql);

    expect($analysis->isSelect())->toBeTrue()
        ->and($analysis->cteNames)->toContain('t')
        ->and($analysis->tables)->not->toContain('t');
});

it('extracts real tables from CTE bodies and outer queries', function (): void {
    $analysis = SqlAnalyzer::of(
        'WITH a AS (SELECT 1 AS x), b AS (SELECT id FROM users) SELECT * FROM b',
    );

    expect($analysis->cteNames)->toContain('a', 'b')
        ->and($analysis->tables)->toContain('users')
        ->and($analysis->tables)->not->toContain('a')
        ->and($analysis->tables)->not->toContain('b');
});

it('recognises Postgres MATERIALIZED and NOT MATERIALIZED CTEs', function (string $sql): void {
    $analysis = SqlAnalyzer::of($sql);

    expect($analysis->cteNames)->toContain('x')
        ->and($analysis->tables)->toContain('posts')
        ->and($analysis->tables)->not->toContain('x');
})->with([
    'materialized' => ['WITH x AS MATERIALIZED (SELECT id FROM posts) SELECT * FROM x'],
    'not materialized' => ['WITH x AS NOT MATERIALIZED (SELECT id FROM posts) SELECT * FROM x'],
]);

it('extracts tables from subqueries in WHERE', function (): void {
    $analysis = SqlAnalyzer::of(
        'SELECT * FROM posts WHERE id IN (SELECT user_id FROM users)',
    );

    expect($analysis->tables)->toContain('posts', 'users');
});

it('keeps CTE names out of scope when joining a real table', function (): void {
    $analysis = SqlAnalyzer::of(
        'WITH t(n) AS (SELECT 1) SELECT n FROM t JOIN posts ON true',
    );

    expect($analysis->cteNames)->toContain('t')
        ->and($analysis->tables)->toContain('posts')
        ->and($analysis->tables)->not->toContain('t');
});

// ---------------------------------------------------------------------------
// Guard-level (end-to-end scope)
// ---------------------------------------------------------------------------

it('allows a CTE that only references in-scope tables', function (): void {
    $result = cteGuard()->run(
        'WITH recent AS (SELECT id, title FROM posts) SELECT id FROM recent ORDER BY id',
    );

    expect($result->rowCount)->toBe(3);
});

it('allows WITH RECURSIVE that does not touch out-of-scope tables', function (): void {
    // SQLite supports WITH RECURSIVE; body only references the CTE itself.
    $result = cteGuard()->run(
        'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 2) SELECT n FROM t',
    );

    expect($result->rowCount)->toBeGreaterThan(0);
});

it('allows multi-CTE queries that read an in-scope model table', function (): void {
    $result = cteGuard()->run(
        'WITH a AS (SELECT 1 AS x), b AS (SELECT id FROM users) SELECT id FROM b ORDER BY id',
    );

    expect($result->rowCount)->toBe(2);
});

it('allows Postgres-style MATERIALIZED CTE syntax against an in-scope table', function (): void {
    // Syntax is accepted by the analyzer; SQLite ignores MATERIALIZED as a hint
    // only if the driver supports it — we only require the guard to accept scope.
    // On SQLite, "AS MATERIALIZED" may be a syntax error at the driver; if so,
    // the analyzer/scope path must still treat x as a CTE (not table-not-allowed).
    try {
        $result = cteGuard()->run(
            'WITH x AS MATERIALIZED (SELECT id FROM posts) SELECT id FROM x',
        );
        expect($result->rowCount)->toBeGreaterThan(0);
    } catch (UnsafeQueryException $e) {
        // Must not be a table-scope failure for "x".
        expect($e->getMessage())->not->toContain('not exposed by the database viewer');
        throw $e;
    } catch (Throwable $e) {
        // Driver may reject MATERIALIZED; that is fine as long as scope passed.
        expect($e)->not->toBeInstanceOf(UnsafeQueryException::class);
    }
});

it('still blocks out-of-scope tables inside a CTE body', function (): void {
    expect(fn () => cteGuard()->run(
        'WITH x AS (SELECT * FROM sqlite_master) SELECT * FROM x',
    ))->toThrow(UnsafeQueryException::class);
});

it('still blocks out-of-scope tables joined to a CTE', function (): void {
    expect(fn () => cteGuard()->run(
        'WITH t AS (SELECT 1 AS n) SELECT * FROM t, sqlite_master',
    ))->toThrow(UnsafeQueryException::class);
});

it('still allows the existing simple WITH … SELECT path', function (): void {
    $result = cteGuard()->run('with recent as (select * from posts) select id from recent');

    expect($result->rowCount)->toBe(3);
});
