<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\DbviewPlugin;

it('defaults to the models scope', function (): void {
    expect(config('filament-dbview.query_runner.scope'))->toBe('models');
});

it('applies the query runner scope fluently', function (): void {
    DbviewPlugin::make()->queryRunnerScope('connection')->applyConfiguration();

    expect(config('filament-dbview.query_runner.scope'))->toBe('connection');
});

it('allTables() switches the scope to connection', function (): void {
    DbviewPlugin::make()->allTables()->applyConfiguration();

    expect(config('filament-dbview.query_runner.scope'))->toBe('connection');

    DbviewPlugin::make()->allTables(false)->applyConfiguration();

    expect(config('filament-dbview.query_runner.scope'))->toBe('models');
});

it('applies the deny list fluently', function (): void {
    DbviewPlugin::make()->denyTables(['sessions', 'migrations'])->applyConfiguration();

    expect(config('filament-dbview.query_runner.deny'))->toBe(['sessions', 'migrations']);
});

it('leaves config untouched when no setter is called', function (): void {
    config()->set('filament-dbview.query_runner.scope', 'connection');

    DbviewPlugin::make()->applyConfiguration();

    expect(config('filament-dbview.query_runner.scope'))->toBe('connection');
});

it('defaults query history to off', function (): void {
    expect(config('filament-dbview.features.history'))->toBeFalse();
});

it('enables history fluently via the plugin', function (): void {
    DbviewPlugin::make()->history()->applyConfiguration();

    expect(config('filament-dbview.features.history'))->toBeTrue();

    DbviewPlugin::make()->history(false)->applyConfiguration();

    expect(config('filament-dbview.features.history'))->toBeFalse();
});

it('does not override history when history() was not called', function (): void {
    config()->set('filament-dbview.features.history', true);

    DbviewPlugin::make()->applyConfiguration();

    expect(config('filament-dbview.features.history'))->toBeTrue();
});
