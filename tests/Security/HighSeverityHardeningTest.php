<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ConnectionResolver;
use SridharSSubramanian\FilamentDbview\Support\DynamicModel;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;
use SridharSSubramanian\FilamentDbview\Support\SqlAnalyzer;
use SridharSSubramanian\FilamentDbview\Support\TableInfo;

function highGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

// ---------------------------------------------------------------------------
// #5 Connection allowlist
// ---------------------------------------------------------------------------

it('rejects a connection that is not on the allowlist', function (): void {
    config()->set('filament-dbview.connections.allowed', ['testing']);

    expect(fn () => highGuard()->run('SELECT id FROM posts', connection: 'not_allowed'))
        ->toThrow(UnsafeQueryException::class, 'not available to the database viewer');
});

it('accepts the default testing connection from the registry allowlist', function (): void {
    $result = highGuard()->run('SELECT id FROM posts');

    expect($result->rowCount)->toBe(3);
});

it('rejects a connection when the explicit allowlist is empty', function (): void {
    config()->set('filament-dbview.connections.allowed', []);

    expect(fn () => highGuard()->run('SELECT 1'))
        ->toThrow(UnsafeQueryException::class);
});

// ---------------------------------------------------------------------------
// #4 Schema / database qualification
// ---------------------------------------------------------------------------

it('flags multi-part table references in the analyzer', function (): void {
    $analysis = SqlAnalyzer::of('SELECT * FROM other_db.users');

    expect($analysis->hasQualifiedTableRef)->toBeTrue()
        ->and($analysis->qualifiedTableRef)->toBe('other_db.users')
        ->and($analysis->tables)->toContain('users');
});

it('blocks schema- or database-qualified table names even when bare name is allowed', function (): void {
    expect(fn () => highGuard()->run('SELECT * FROM other_db.users'))
        ->toThrow(UnsafeQueryException::class, 'Schema- or database-qualified');
});

it('blocks public.posts style qualification', function (): void {
    expect(fn () => highGuard()->run('SELECT * FROM public.posts'))
        ->toThrow(UnsafeQueryException::class);
});

it('still allows bare table names after the qualification check', function (): void {
    $result = highGuard()->run('SELECT id FROM users ORDER BY id');

    expect($result->rowCount)->toBe(2);
});

// ---------------------------------------------------------------------------
// #6 Browser path uses ConnectionResolver (read_only remap)
// ---------------------------------------------------------------------------

it('binds DynamicModel to the remapped read-only connection', function (): void {
    config()->set('database.connections.readonly_clone', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    // Mirror the users table onto the clone so a query would work if bound there.
    Schema::connection('readonly_clone')->create('users', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name');
        $table->string('email');
        $table->string('password')->nullable();
        $table->string('api_token')->nullable();
    });

    config()->set('filament-dbview.connections.read_only', [
        'testing' => 'readonly_clone',
    ]);

    $info = app(ModelDiscovery::class)->registry()->get('users');
    expect($info)->toBeInstanceOf(TableInfo::class);

    $model = DynamicModel::for($info);

    expect($model->getConnectionName())->toBe('readonly_clone');

    // physicalName still maps the logical app connection correctly.
    expect(app(ConnectionResolver::class)->physicalName('testing'))->toBe('readonly_clone');
});

// ---------------------------------------------------------------------------
// #7 Side-effect / lock denylist
// ---------------------------------------------------------------------------

it('blocks session and advisory lock functions', function (string $sql): void {
    expect(fn () => highGuard()->run($sql))
        ->toThrow(UnsafeQueryException::class);
})->with([
    'get_lock' => ['SELECT GET_LOCK("x", 10)'],
    'release_lock' => ['SELECT RELEASE_LOCK("x")'],
    'pg_advisory_lock' => ['SELECT pg_advisory_lock(1)'],
    'pg_terminate_backend' => ['SELECT pg_terminate_backend(1)'],
    'openrowset' => ["SELECT * FROM OPENROWSET('a', 'b')"],
]);

it('still blocks classic sleep/dos tokens', function (): void {
    expect(fn () => highGuard()->run('SELECT SLEEP(1)'))
        ->toThrow(UnsafeQueryException::class);
});
