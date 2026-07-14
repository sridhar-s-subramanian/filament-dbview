<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Models\DbviewSavedQuery;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

it('does not write history rows when the feature is disabled (default)', function (): void {
    expect(config('filament-dbview.features.history'))->toBeFalse();

    app(ReadOnlyGuard::class)->run('select id from posts');

    expect(DB::table('dbview_query_history')->count())->toBe(0);
});

it('records an allowed query in the audit history when enabled', function (): void {
    config()->set('filament-dbview.features.history', true);

    app(ReadOnlyGuard::class)->run('select id from posts');

    $row = DB::table('dbview_query_history')->where('allowed', true)->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->sql)->toBe('select id from posts')
        ->and((int) $row->row_count)->toBe(3);
});

it('persists and reads back a saved query', function (): void {
    DbviewSavedQuery::query()->create([
        'user_id' => null,
        'name' => 'All posts',
        'connection' => 'testing',
        'sql' => 'select * from posts',
    ]);

    expect(DbviewSavedQuery::query()->where('name', 'All posts')->value('sql'))
        ->toBe('select * from posts');
});
