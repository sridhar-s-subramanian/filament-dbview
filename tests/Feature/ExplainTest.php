<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

function explainGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

it('returns an execution plan for a valid SELECT', function (): void {
    $result = explainGuard()->explain('select id, title from posts order by id');

    // SQLite → EXPLAIN QUERY PLAN, which yields at least one plan row.
    expect($result->rowCount)->toBeGreaterThan(0)
        ->and($result->columns)->not->toBe([]);
});

it('returns a plan in analyze mode', function (): void {
    $result = explainGuard()->explain('select id, title from posts', analyze: true);

    expect($result->rowCount)->toBeGreaterThan(0)
        ->and($result->columns)->not->toBe([]);
});

it('rejects write and DDL statements in explain mode', function (string $sql): void {
    explainGuard()->explain($sql);
})->with([
    'update posts set title = "x"',
    'delete from posts',
    'drop table posts',
    'insert into posts (id) values (99)',
    'truncate table posts',
])->throws(UnsafeQueryException::class);

it('rejects write and DDL statements in analyze mode', function (string $sql): void {
    explainGuard()->explain($sql, analyze: true);
})->with([
    'delete from posts',
    'drop table posts',
])->throws(UnsafeQueryException::class);

it('rejects stacked statements in explain mode', function (): void {
    explainGuard()->explain('select * from posts; drop table posts');
})->throws(UnsafeQueryException::class);

it('rejects out-of-allowlist tables in explain mode', function (): void {
    explainGuard()->explain('select * from sqlite_master');
})->throws(UnsafeQueryException::class);

it('leaves the database unmodified after an analyze', function (): void {
    explainGuard()->explain('select * from posts', analyze: true);

    expect(DB::table('posts')->count())->toBe(3);
});
