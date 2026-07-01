<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

function guard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

it('runs a valid SELECT and returns rows', function (): void {
    $result = guard()->run('select id, title from posts order by id');

    expect($result->rowCount)->toBe(3)
        ->and($result->columns)->toBe(['id', 'title'])
        ->and($result->rows[0]['title'])->toBe('Hello');
});

it('runs WITH … SELECT (CTE)', function (): void {
    $result = guard()->run('with recent as (select * from posts) select id from recent');

    expect($result->rowCount)->toBe(3);
});

it('enforces the configured row limit', function (): void {
    config()->set('filament-dbview.limits.max_rows', 2);

    $result = guard()->run('select * from posts', limit: 999);

    expect($result->rowCount)->toBe(2);
});

it('redacts sensitive columns in the result', function (): void {
    $result = guard()->run('select * from users order by id');

    expect($result->rows[0]['password'])->toBe(config('filament-dbview.redaction_mask'))
        ->and($result->rows[0]['api_token'])->toBe(config('filament-dbview.redaction_mask'))
        ->and($result->rows[0]['email'])->toBe('ada@example.com');
});

it('rejects write and DDL statements before execution', function (string $sql): void {
    guard()->run($sql);
})->with([
    'update posts set title = "x"',
    'delete from posts',
    'drop table posts',
    'insert into posts (id) values (99)',
    'truncate table posts',
    'alter table posts add column x int',
])->throws(UnsafeQueryException::class);

it('rejects stacked statements', function (): void {
    guard()->run('select * from posts; drop table posts');
})->throws(UnsafeQueryException::class);

it('rejects queries against tables outside the model allowlist', function (): void {
    guard()->run('select * from sqlite_master');
})->throws(UnsafeQueryException::class);

it('leaves the database unmodified even for a valid read', function (): void {
    guard()->run('select * from posts');

    expect(DB::table('posts')->count())->toBe(3);
});
