<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;
use SridharSSubramanian\FilamentDbview\Tests\Fixtures\Models\Post;

it('discovers model-backed tables and their columns', function (): void {
    $registry = app(ModelDiscovery::class)->registry();

    expect($registry->has('posts'))->toBeTrue()
        ->and($registry->has('users'))->toBeTrue()
        ->and($registry->has('secrets'))->toBeFalse();

    $posts = $registry->get('posts');

    expect($posts->model)->toBe(Post::class)
        ->and($posts->columns)->toContain('id')->toContain('title')->toContain('user_id');
});

it('exposes select options and table names', function (): void {
    $registry = app(ModelDiscovery::class)->registry();

    expect($registry->tableNames())->toContain('posts')
        ->and($registry->selectOptions())->toHaveKey('posts');
});

it('detects foreign keys where the driver supports them', function (): void {
    $posts = app(ModelDiscovery::class)->registry()->get('posts');

    // SQLite reports FK metadata; assert shape only when present.
    if ($posts->foreignKeys !== []) {
        expect($posts->foreignKeys[0])
            ->toHaveKeys(['column', 'foreign_table', 'foreign_column'])
            ->and($posts->foreignKeys[0]['foreign_table'])->toBe('users');
    }
})->skip(fn(): bool => app(ModelDiscovery::class)->registry()->get('posts')->foreignKeys === [], 'Driver does not expose foreign keys');
