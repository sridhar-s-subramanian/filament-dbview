<?php

declare(strict_types=1);

use Filament\Actions\Action;
use SridharSSubramanian\FilamentDbview\Pages\QueryRunner;

it('defines viewRowAction as a slide-over modal action', function (): void {
    $page = new QueryRunner();

    $action = $page->viewRowAction();

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('viewRow');
});

it('shows the structure of a table', function (): void {
    $page = new QueryRunner();

    $page->showStructure('posts');

    expect($page->isStructure)->toBeTrue()
        ->and($page->error)->toBeNull()
        ->and($page->structure['table'])->toBe('posts')
        ->and($page->structure['columns'])->not->toBe([])
        ->and($page->structure['foreignKeys'][0]['foreign_table'])->toBe('users');

    $columns = array_map(static fn(array $c): string => $c['name'], $page->structure['columns']);
    expect($columns)->toContain('user_id');
});

it('errors and shows no structure for an out-of-scope table', function (): void {
    $page = new QueryRunner();

    $page->showStructure('dbview_query_history');

    expect($page->isStructure)->toBeFalse()
        ->and($page->structure)->toBe([])
        ->and($page->error)->not->toBeNull();
});

it('does nothing when the table name is empty', function (): void {
    $page = new QueryRunner();

    $page->showStructure('');

    expect($page->isStructure)->toBeFalse()
        ->and($page->structure)->toBe([]);
});

it('does not show structure when the feature is disabled', function (): void {
    config()->set('filament-dbview.features.structure', false);

    $page = new QueryRunner();

    $page->showStructure('posts');

    expect($page->isStructure)->toBeFalse();
});

it('registers a Structure header action when the feature is enabled', function (): void {
    $page = new QueryRunner();
    $page->mount();

    $method = new ReflectionMethod(QueryRunner::class, 'getHeaderActions');
    $actions = $method->invoke($page);
    $names = array_map(static fn(Action $action): string => $action->getName(), $actions);

    expect($names)->toContain('structure');

    $structure = collect($actions)->first(
        static fn(Action $action): bool => $action->getName() === 'structure',
    );

    expect($structure)->toBeInstanceOf(Action::class)
        ->and($structure->getLabel())->toBe(__('Structure'));
});

it('omits the Structure header action when the feature is disabled', function (): void {
    config()->set('filament-dbview.features.structure', false);

    $page = new QueryRunner();
    $page->mount();

    $method = new ReflectionMethod(QueryRunner::class, 'getHeaderActions');
    $actions = $method->invoke($page);
    $names = array_map(static fn(Action $action): string => $action->getName(), $actions);

    expect($names)->not->toContain('structure');
});

it('shows structure for the table referenced in the editor SQL', function (): void {
    $page = new QueryRunner();
    $page->mount();
    $page->sql = 'SELECT id, title FROM posts ORDER BY id';

    $page->showStructureFromSql();

    expect($page->isStructure)->toBeTrue()
        ->and($page->structure['table'])->toBe('posts')
        ->and($page->error)->toBeNull();
});

it('uses the first real table when the query references several', function (): void {
    $page = new QueryRunner();
    $page->mount();
    $page->sql = 'SELECT * FROM posts JOIN users ON users.id = posts.user_id';

    $page->showStructureFromSql();

    expect($page->isStructure)->toBeTrue()
        ->and($page->structure['table'])->toBe('posts');
});

it('does not open structure when the SQL has no table reference', function (): void {
    $page = new QueryRunner();
    $page->mount();
    $page->sql = 'SELECT 1';

    $page->showStructureFromSql();

    expect($page->isStructure)->toBeFalse()
        ->and($page->structure)->toBe([]);
});

it('ignores CTE names when resolving structure from SQL', function (): void {
    $page = new QueryRunner();
    $page->mount();
    $page->sql = 'WITH recent AS (SELECT id FROM posts) SELECT id FROM recent';

    $page->showStructureFromSql();

    expect($page->isStructure)->toBeTrue()
        ->and($page->structure['table'])->toBe('posts');
});

it('clears prior query results when showing structure', function (): void {
    $page = new QueryRunner();
    $page->hasRun = true;
    $page->isExplain = true;
    $page->resultRows = [['id' => 1]];
    $page->resultColumns = ['id'];

    $page->showStructure('posts');

    expect($page->isStructure)->toBeTrue()
        ->and($page->hasRun)->toBeFalse()
        ->and($page->isExplain)->toBeFalse()
        ->and($page->resultRows)->toBe([]);
});

it('lists model-backed tables in the sidebar', function (): void {
    $tables = (new QueryRunner())->getAllTables();

    expect($tables)->toContain('posts', 'users');
});

it('lists browsable (model-backed) tables', function (): void {
    $tables = (new QueryRunner())->getBrowsableTableNames();

    expect($tables)->toContain('posts', 'users')
        ->and($tables)->not->toContain('dbview_query_history');
});

it('prefills a SELECT from the ?table cross-link', function (): void {
    $page = new QueryRunner();
    $page->prefillTable = 'posts';

    $page->mount();

    expect($page->sql)->toBe('SELECT * FROM posts')
        ->and($page->isStructure)->toBeFalse();
});

it('does not overwrite existing SQL when prefilling a table', function (): void {
    $page = new QueryRunner();
    $page->prefillTable = 'posts';
    $page->sql = 'select 1';

    $page->mount();

    expect($page->sql)->toBe('select 1');
});

it('opens the structure view from the ?structure cross-link', function (): void {
    $page = new QueryRunner();
    $page->prefillStructure = 'posts';

    $page->mount();

    expect($page->isStructure)->toBeTrue()
        ->and($page->structure['table'])->toBe('posts')
        ->and($page->sql)->toBeNull();
});

it('ignores an out-of-scope prefill table', function (): void {
    $page = new QueryRunner();
    $page->prefillTable = 'dbview_query_history';

    $page->mount();

    expect($page->sql)->toBeNull()
        ->and($page->isStructure)->toBeFalse();
});
