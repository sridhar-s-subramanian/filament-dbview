<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;
use SridharSSubramanian\FilamentDbview\Support\Redactor;
use SridharSSubramanian\FilamentDbview\Support\SqlAnalyzer;

/**
 * Regression tests for the three critical scope / redaction bypasses:
 *   1. Comma-join table list (`FROM allowed, secret`)
 *   2. Quoted identifiers (`FROM \`secret\``, `FROM "secret"`)
 *   3. Redaction via alias / expression (`password AS pwd`, `hex(password)`)
 */
function criticalGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

beforeEach(function (): void {
    if (! Schema::hasTable('secrets')) {
        Schema::create('secrets', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('token');
        });

        DB::table('secrets')->insert(['id' => 1, 'token' => 'SUPER_SECRET']);
    }
});

// ---------------------------------------------------------------------------
// 1 + 2: table scope
// ---------------------------------------------------------------------------

it('blocks comma-join access to out-of-scope tables', function (): void {
    expect(fn () => criticalGuard()->run('SELECT secrets.token FROM posts, secrets'))
        ->toThrow(UnsafeQueryException::class);
});

it('extracts every table from a comma-separated FROM list', function (): void {
    $tables = SqlAnalyzer::of('SELECT * FROM posts AS p, users AS u')->tables;

    expect($tables)->toContain('posts')->toContain('users');
});

it('blocks backtick-quoted out-of-scope tables', function (): void {
    expect(fn () => criticalGuard()->run('SELECT * FROM `secrets`'))
        ->toThrow(UnsafeQueryException::class);
});

it('extracts backtick-quoted table names for scope checks', function (): void {
    $analysis = SqlAnalyzer::of('SELECT * FROM `posts`');

    expect($analysis->tables)->toContain('posts')
        ->and($analysis->hasUnresolvableTableRef)->toBeFalse();
});

it('blocks double-quoted out-of-scope tables', function (): void {
    expect(fn () => criticalGuard()->run('SELECT * FROM "secrets"'))
        ->toThrow(UnsafeQueryException::class);
});

it('extracts double-quoted table names for scope checks', function (): void {
    $analysis = SqlAnalyzer::of('SELECT * FROM "posts"');

    expect($analysis->tables)->toContain('posts')
        ->and($analysis->hasUnresolvableTableRef)->toBeFalse();
});

it('blocks bracket-quoted out-of-scope tables', function (): void {
    expect(fn () => criticalGuard()->run('SELECT * FROM [secrets]'))
        ->toThrow(UnsafeQueryException::class);
});

it('still allows a benign scoped SELECT after the fixes', function (): void {
    $result = criticalGuard()->run('SELECT id, title FROM posts ORDER BY id');

    expect($result->rowCount)->toBe(3)
        ->and($result->rows[0]['title'])->toBe('Hello');
});

it('still allows comma joins when every table is in scope', function (): void {
    $result = criticalGuard()->run(
        'SELECT posts.id, users.email FROM posts, users WHERE posts.user_id = users.id ORDER BY posts.id',
    );

    expect($result->rowCount)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// 3: redaction
// ---------------------------------------------------------------------------

it('redacts sensitive values selected under an alias', function (): void {
    $mask = (string) config('filament-dbview.redaction_mask');
    $result = criticalGuard()->run('SELECT password AS pwd FROM users ORDER BY id');

    expect($result->rows[0]['pwd'])->toBe($mask);
});

it('redacts sensitive values selected through an expression', function (): void {
    $mask = (string) config('filament-dbview.redaction_mask');
    $result = criticalGuard()->run("SELECT password || '' AS x FROM users ORDER BY id");

    expect($result->rows[0]['x'])->toBe($mask);
});

it('redacts sensitive values selected through a nested alias', function (): void {
    $mask = (string) config('filament-dbview.redaction_mask');
    $result = criticalGuard()->run(
        'SELECT pwd FROM (SELECT password AS pwd FROM users) AS t ORDER BY 1',
    );

    expect($result->rows[0]['pwd'])->toBe($mask);
});

it('still redacts plain sensitive column names', function (): void {
    $mask = (string) config('filament-dbview.redaction_mask');
    $result = criticalGuard()->run('SELECT password, email FROM users ORDER BY id');

    expect($result->rows[0]['password'])->toBe($mask)
        ->and($result->rows[0]['email'])->toBe('ada@example.com');
});

it('marks alias outputs as sensitive in the analyzer', function (): void {
    $redactor = new Redactor(['password'], 'XXX');
    $names = SqlAnalyzer::of('SELECT password AS pwd, email FROM users')
        ->sensitiveOutputNames($redactor);

    expect($names)->toContain('pwd')
        ->and($names)->not->toContain('email');
});
