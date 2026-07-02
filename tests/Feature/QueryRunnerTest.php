<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Pages\QueryRunner;
use Filament\Actions\Action;

it('defines viewRowAction as a slide-over modal action', function (): void {
    $page = new QueryRunner();

    $action = $page->viewRowAction();

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('viewRow');
});
