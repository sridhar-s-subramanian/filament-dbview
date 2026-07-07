<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

beforeEach(function (): void {
    config()->set('database.connections.prefixed', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'pfx_',
    ]);

    // Real (prefixed) table + rows: physically pfx_widgets. Model-backed.
    Schema::connection('prefixed')->create('widgets', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name');
    });

    DB::connection('prefixed')->table('widgets')->insert([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);

    // A prefixed table with NO Eloquent model: physically pfx_gadgets.
    Schema::connection('prefixed')->create('gadgets', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('label');
    });

    DB::connection('prefixed')->table('gadgets')->insert([
        ['id' => 1, 'label' => 'Gizmo'],
    ]);

    // A non-prefixed table: physically "legacy_data" (no pfx_ prefix).
    DB::connection('prefixed')->statement('CREATE TABLE legacy_data (id INTEGER PRIMARY KEY, info TEXT)');
    DB::connection('prefixed')->statement('INSERT INTO legacy_data (id, info) VALUES (1, "Legacy")');

    app(ModelDiscovery::class)->forget();
});

it('rewrites a logical table name to its prefixed physical name before running', function (): void {
    // The user types the logical name (as shown in the browser); the guard must
    // translate `widgets` -> `pfx_widgets` for the raw query to succeed.
    $result = app(ReadOnlyGuard::class)->run('select * from widgets order by id', connection: 'prefixed');

    expect($result->rowCount)->toBe(2)
        ->and($result->rows[0]['name'])->toBe('Alpha');
});

it('rewrites qualified references too', function (): void {
    $result = app(ReadOnlyGuard::class)->run(
        'select widgets.name from widgets where widgets.id = 2',
        connection: 'prefixed',
    );

    expect($result->rowCount)->toBe(1)
        ->and($result->rows[0]['name'])->toBe('Beta');
});

it('prefixes non-model tables too in connection scope', function (): void {
    // Laravel's schema is prefix-transparent, so a model-less table on a
    // prefixed connection must still be rewritten (gadgets -> pfx_gadgets).
    config()->set('filament-dbview.query_runner.scope', 'connection');

    $result = app(ReadOnlyGuard::class)->run('select * from gadgets', connection: 'prefixed');

    expect($result->rowCount)->toBe(1)
        ->and($result->rows[0]['label'])->toBe('Gizmo');
});

it('allows querying non-prefixed tables on a prefixed connection in connection scope', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');

    $result = app(ReadOnlyGuard::class)->run('select * from legacy_data', connection: 'prefixed');

    expect($result->rowCount)->toBe(1)
        ->and($result->rows[0]['info'])->toBe('Legacy');
});

it('introspects schema on a prefixed table', function (): void {
    // The inspector resolves `widgets` to its physical name pfx_widgets and
    // introspects it with the connection prefix cleared, so the physical name is
    // used verbatim (no double-prefixing).
    $schema = app(\SridharSSubramanian\FilamentDbview\Support\SchemaInspector::class)
        ->for(['widgets'], 'prefixed', null);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['table'])->toBe('widgets');

    $primaries = array_values(array_filter(
        $schema[0]['indexes'],
        static fn(array $index): bool => $index['primary'],
    ));

    expect($primaries)->not->toBeEmpty()
        ->and($primaries[0]['columns'])->toBe(['id']);
});

it('introspects a non-prefixed table on a prefixed connection', function (): void {
    // Mixed-prefix database: `legacy_data` is a real table WITHOUT the cms_/pfx_
    // prefix on a prefixed connection. Schema builders always prepend the prefix,
    // so the inspector must introspect the physical name with the prefix cleared.
    config()->set('filament-dbview.query_runner.scope', 'connection');

    $schema = app(\SridharSSubramanian\FilamentDbview\Support\SchemaInspector::class)
        ->for(['legacy_data'], 'prefixed', null);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['table'])->toBe('legacy_data');

    $names = array_map(static fn(array $c): string => $c['name'], $schema[0]['columns']);

    expect($names)->toContain('id', 'info');
});
