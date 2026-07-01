<?php

declare(strict_types=1);

use SridharSSubramanian\FilamentDbview\Pages\DatabaseBrowser;

/**
 * Regression guard: Filament persists column-visibility state keyed by page
 * class alone. A single-page, many-tables browser must scope that key to the
 * selected table, otherwise the stored visible-column set is shared across
 * every table and collapses to the columns they have in common
 * (id, created_at, updated_at). See DatabaseBrowser::getTableColumnsSessionKey().
 */
it('uses a distinct column-session key per selected table', function (): void {
    $page = new DatabaseBrowser();

    $page->selectedTable = 'posts';
    $postsKey = $page->getTableColumnsSessionKey();
    $postsReorderKey = $page->getHasReorderedTableColumnsSessionKey();

    $page->selectedTable = 'users';
    $usersKey = $page->getTableColumnsSessionKey();

    expect($postsKey)
        ->toStartWith('tables.')
        ->not->toBe($usersKey)
        ->and($postsReorderKey)->toStartWith('tables.')
        ->and($postsReorderKey)->not->toBe($postsKey);
});

it('does not reuse Filament\'s default class-only column-session key', function (): void {
    $page = new DatabaseBrowser();
    $page->selectedTable = 'posts';

    // Filament's default is md5(static::class); ours must differ so switching
    // tables can never inherit another table's stored visibility.
    $default = 'tables.' . md5(DatabaseBrowser::class) . '_columns';

    expect($page->getTableColumnsSessionKey())->not->toBe($default);
});
