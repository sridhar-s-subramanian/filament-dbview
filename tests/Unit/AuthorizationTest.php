<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use SridharSSubramanian\FilamentDbview\Support\Authorization;

beforeEach(function (): void {
    Auth::login(new GenericUser(['id' => 1, 'name' => 'Tester']));
});

it('allows export by default when features.export is on and export_gate is null', function (): void {
    config()->set('filament-dbview.features.export', true);
    config()->set('filament-dbview.features.query_runner', true);
    config()->set('filament-dbview.authorization.gate', null);
    config()->set('filament-dbview.authorization.query_runner_gate', null);
    config()->set('filament-dbview.authorization.export_gate', null);

    expect(Authorization::canExport())->toBeTrue();
});

it('denies export when features.export is false', function (): void {
    config()->set('filament-dbview.features.export', false);
    config()->set('filament-dbview.authorization.export_gate', null);

    expect(Authorization::canExport())->toBeFalse();
});

it('denies export when export_gate is set and the ability fails', function (): void {
    config()->set('filament-dbview.features.export', true);
    config()->set('filament-dbview.features.query_runner', true);
    config()->set('filament-dbview.authorization.export_gate', 'exportDbview');

    Gate::define('exportDbview', fn (): bool => false);

    expect(Authorization::canExport())->toBeFalse();
});

it('allows export when export_gate is set and the ability passes', function (): void {
    config()->set('filament-dbview.features.export', true);
    config()->set('filament-dbview.features.query_runner', true);
    config()->set('filament-dbview.authorization.export_gate', 'exportDbview');

    Gate::define('exportDbview', fn (): bool => true);

    expect(Authorization::canExport())->toBeTrue();
});

it('denies export when the user cannot run queries', function (): void {
    config()->set('filament-dbview.features.export', true);
    config()->set('filament-dbview.features.query_runner', false);
    config()->set('filament-dbview.authorization.export_gate', null);

    expect(Authorization::canExport())->toBeFalse();
});
