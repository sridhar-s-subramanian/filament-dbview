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
