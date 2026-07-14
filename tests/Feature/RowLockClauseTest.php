<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

function lockGuard(): ReadOnlyGuard
{
    return app(ReadOnlyGuard::class);
}

it('rejects SELECT … FOR SHARE', function (): void {
    expect(fn() => lockGuard()->run('SELECT * FROM posts FOR SHARE'))
        ->toThrow(UnsafeQueryException::class, 'FOR SHARE');
});

it('rejects SELECT … FOR UPDATE', function (): void {
    expect(fn() => lockGuard()->run('SELECT id FROM posts FOR UPDATE'))
        ->toThrow(UnsafeQueryException::class);
});

it('rejects SELECT … FOR UPDATE SKIP LOCKED', function (): void {
    expect(fn() => lockGuard()->run('SELECT id FROM posts FOR UPDATE SKIP LOCKED'))
        ->toThrow(UnsafeQueryException::class);
});

it('rejects SELECT … FOR KEY SHARE', function (): void {
    expect(fn() => lockGuard()->run('SELECT id FROM posts FOR KEY SHARE'))
        ->toThrow(UnsafeQueryException::class);
});

it('rejects SELECT … LOCK IN SHARE MODE', function (): void {
    expect(fn() => lockGuard()->run('SELECT id FROM posts LOCK IN SHARE MODE'))
        ->toThrow(UnsafeQueryException::class);
});

it('still allows a plain SELECT without lock clauses', function (): void {
    $result = lockGuard()->run('SELECT id FROM posts ORDER BY id');

    expect($result->rowCount)->toBe(3);
});
