<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview;

use Filament\Contracts\Plugin;
use Filament\Panel;
use SridharSSubramanian\FilamentDbview\Pages\DatabaseBrowser;
use SridharSSubramanian\FilamentDbview\Pages\QueryRunner;

final class DbviewPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-dbview';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function register(Panel $panel): void
    {
        $pages = [DatabaseBrowser::class];

        if (config('filament-dbview.features.query_runner', true)) {
            $pages[] = QueryRunner::class;
        }

        $panel->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
