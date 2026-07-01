<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Models\DbviewSavedQuery;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;

it('records an allowed query in the audit history', function (): void {
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
