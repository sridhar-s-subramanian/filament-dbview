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

/**
 * Build a QueryRunner in a "just ran this SQL" state for exercising the
 * result-schema accessor gating.
 */
function ranRunner(string $sql): QueryRunner
{
    $page = new QueryRunner();
    $page->sql = $sql;
    $page->hasRun = true;

    return $page;
}

it('exposes the referenced tables schema after a successful run', function (): void {
    $schema = ranRunner('select * from posts')->getResultSchema();

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['table'])->toBe('posts')
        ->and($schema[0]['foreignKeys'][0]['foreign_table'])->toBe('users');
});

it('does not expose schema for explain results', function (): void {
    $page = ranRunner('select * from posts');
    $page->isExplain = true;

    expect($page->getResultSchema())->toBe([]);
});

it('does not expose schema when the run errored', function (): void {
    $page = ranRunner('select * from posts');
    $page->error = 'boom';

    expect($page->getResultSchema())->toBe([]);
});

it('does not expose schema before any run', function (): void {
    $page = new QueryRunner();
    $page->sql = 'select * from posts';

    expect($page->getResultSchema())->toBe([]);
});

it('does not expose schema when the feature is disabled', function (): void {
    config()->set('filament-dbview.features.result_schema', false);

    expect(ranRunner('select * from posts')->getResultSchema())->toBe([]);
});
