<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Support\SchemaInspector;

function inspector(): SchemaInspector
{
    return app(SchemaInspector::class);
}

it('returns foreign keys for a referenced model table', function (): void {
    $schema = inspector()->for(['posts']);

    expect($schema)->toHaveCount(1);

    $posts = $schema[0];

    expect($posts['table'])->toBe('posts')
        ->and($posts['label'])->toBe('Post')
        ->and($posts['foreignKeys'])->toHaveCount(1)
        ->and($posts['foreignKeys'][0]['columns'])->toBe(['user_id'])
        ->and($posts['foreignKeys'][0]['foreign_table'])->toBe('users')
        ->and($posts['foreignKeys'][0]['foreign_columns'])->toBe(['id']);
});

it('returns indexes including the primary key', function (): void {
    $schema = inspector()->for(['posts']);

    $primaries = array_values(array_filter(
        $schema[0]['indexes'],
        static fn(array $index): bool => $index['primary'],
    ));

    expect($primaries)->not->toBeEmpty()
        ->and($primaries[0]['columns'])->toBe(['id']);
});

it('returns no foreign keys for a table without any', function (): void {
    $schema = inspector()->for(['users']);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['foreignKeys'])->toBe([]);
});

it('describes every referenced table for a join, de-duplicated', function (): void {
    $schema = inspector()->for(['posts', 'users', 'posts']);

    $tables = array_map(static fn(array $t): string => $t['table'], $schema);

    expect($tables)->toBe(['posts', 'users']);
});

it('omits tables outside the model allowlist', function (): void {
    expect(inspector()->for(['dbview_query_history']))->toBe([])
        ->and(inspector()->for(['sqlite_master']))->toBe([]);
});

it('omits unknown tables without throwing', function (): void {
    expect(inspector()->for(['no_such_table']))->toBe([]);
});
