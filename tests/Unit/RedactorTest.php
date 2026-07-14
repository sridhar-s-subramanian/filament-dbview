<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Support\Redactor;

it('redacts sensitive column names via patterns', function (string $column): void {
    expect((new Redactor())->redacts($column))->toBeTrue();
})->with([
    'password',
    'PASSWORD',
    'api_token',
    'remember_token',
    'stripe_secret',
    'two_factor_recovery_codes',
]);

it('does not redact ordinary columns', function (string $column): void {
    expect((new Redactor())->redacts($column))->toBeFalse();
})->with([
    'id',
    'email',
    'title',
    'created_at',
]);

it('masks sensitive values but preserves nulls', function (): void {
    $redactor = new Redactor(['password'], 'XXX');

    expect($redactor->apply(['id' => 1, 'password' => 'hunter2']))
        ->toBe(['id' => 1, 'password' => 'XXX'])
        ->and($redactor->apply(['password' => null]))
        ->toBe(['password' => null]);
});

it('force-masks aliased and positional outputs', function (): void {
    $redactor = new Redactor(['password'], 'XXX');

    expect($redactor->apply(
        ['pwd' => 'hunter2', 'email' => 'a@b.c'],
        forceColumns: ['pwd'],
    ))->toBe(['pwd' => 'XXX', 'email' => 'a@b.c'])
        ->and($redactor->apply(
            ['hex(password)' => 'abc', 'email' => 'a@b.c'],
            forceColumns: [],
            forcePositions: [0],
        ))->toBe(['hex(password)' => 'XXX', 'email' => 'a@b.c']);
});
