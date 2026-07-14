<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Support\SqlAnalyzer;

it('recognises SELECT and WITH as read-only statements', function (string $sql): void {
    expect(SqlAnalyzer::of($sql)->isSelect())->toBeTrue();
})->with([
    'select * from posts',
    '  SELECT id FROM posts',
    "WITH t AS (SELECT 1) SELECT * FROM t",
    '(SELECT 1)',
]);

it('rejects non-select statements', function (string $sql): void {
    expect(SqlAnalyzer::of($sql)->isSelect())->toBeFalse();
})->with([
    'update posts set title = 1',
    'delete from posts',
    'drop table posts',
    'insert into posts values (1)',
]);

it('detects stacked statements ignoring a single trailing semicolon', function (): void {
    expect(SqlAnalyzer::of('select 1; drop table posts')->hasStackedStatement)->toBeTrue()
        ->and(SqlAnalyzer::of('select 1;')->hasStackedStatement)->toBeFalse()
        ->and(SqlAnalyzer::of('select 1')->hasStackedStatement)->toBeFalse();
});

it('is not fooled by keywords or semicolons inside string literals', function (): void {
    $analysis = SqlAnalyzer::of("select 'a; drop table x' as note, 'INSERT' as k");

    expect($analysis->isSelect())->toBeTrue()
        ->and($analysis->hasStackedStatement)->toBeFalse()
        ->and($analysis->forbiddenTokens())->toBe([]);
});

it('is not fooled by keywords hidden in comments', function (): void {
    $analysis = SqlAnalyzer::of("select 1 -- ; delete from posts\n");

    expect($analysis->hasStackedStatement)->toBeFalse()
        ->and($analysis->forbiddenTokens())->toBe([]);
});

it('flags MySQL executable comments', function (): void {
    expect(SqlAnalyzer::of('select /*!40001 sleep(5) */ 1')->hasExecutableComment)->toBeTrue();
});

it('flags forbidden write/DDL keywords as whole words', function (string $sql, string $token): void {
    expect(SqlAnalyzer::of($sql)->forbiddenTokens())->toContain($token);
})->with([
    ['select * from posts into outfile "/tmp/x"', 'INTO'],
    ['select load_file("/etc/passwd")', 'LOAD_FILE'],
    ['select sleep(10)', 'SLEEP'],
    ['select benchmark(1000000, md5(1))', 'BENCHMARK'],
]);

it('does not flag columns that merely contain a keyword substring', function (): void {
    // "created_at" contains "create"; "updated_by" contains "update".
    expect(SqlAnalyzer::of('select created_at, updated_by from posts')->forbiddenTokens())->toBe([]);
});

it('extracts referenced tables after FROM and JOIN', function (): void {
    $tables = SqlAnalyzer::of('select * from posts p join users u on u.id = p.user_id')->tables;

    expect($tables)->toContain('posts')->toContain('users');
});

it('extracts every table from comma-separated FROM lists', function (): void {
    $tables = SqlAnalyzer::of('select * from posts, users, secrets')->tables;

    expect($tables)->toContain('posts')->toContain('users')->toContain('secrets');
});

it('extracts quoted table identifiers', function (string $sql, string $table): void {
    $analysis = SqlAnalyzer::of($sql);

    expect($analysis->tables)->toContain($table)
        ->and($analysis->hasUnresolvableTableRef)->toBeFalse();
})->with([
    ['select * from `posts`', 'posts'],
    ['select * from "posts"', 'posts'],
    ['select * from [posts]', 'posts'],
]);

it('is not fooled by keywords inside single-quoted strings after identifier changes', function (): void {
    $analysis = SqlAnalyzer::of("select 'DROP TABLE x' as note from posts");

    expect($analysis->isSelect())->toBeTrue()
        ->and($analysis->forbiddenTokens())->toBe([])
        ->and($analysis->tables)->toContain('posts');
});
