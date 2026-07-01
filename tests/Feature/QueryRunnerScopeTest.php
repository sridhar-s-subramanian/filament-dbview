<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

function scopeGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

it('blocks non-model tables in the default models scope', function (): void {
    // dbview_query_history is a real table with no Eloquent model.
    scopeGuard()->run('select * from dbview_query_history');
})->throws(UnsafeQueryException::class);

it('allows any real table in connection scope', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');

    DB::table('dbview_saved_queries')->insert([
        'name' => 'x', 'connection' => 'testing', 'sql' => 'select 1',
    ]);

    $result = scopeGuard()->run('select * from dbview_saved_queries');

    expect($result->rowCount)->toBe(1)
        ->and($result->columns)->toContain('sql');
});

it('still allows model tables by their logical name in connection scope', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');

    expect(scopeGuard()->run('select * from posts')->rowCount)->toBe(3);
});

it('still rejects tables that do not exist at all in connection scope', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');

    scopeGuard()->run('select * from nope_not_real');
})->throws(UnsafeQueryException::class);

it('honours the deny list in connection scope', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');
    config()->set('filament-dbview.query_runner.deny', ['dbview_query_history']);

    scopeGuard()->run('select * from dbview_query_history');
})->throws(UnsafeQueryException::class);
