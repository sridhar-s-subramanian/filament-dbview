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

    // Real (prefixed) table + rows: physically pfx_widgets.
    Schema::connection('prefixed')->create('widgets', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name');
    });

    DB::connection('prefixed')->table('widgets')->insert([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);

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
