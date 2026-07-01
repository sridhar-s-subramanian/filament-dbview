<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use SridharSSubramanian\FilamentDbview\Commands\ClearRegistryCommand;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;

final class FilamentDbviewServiceProvider extends PackageServiceProvider
{
    public const NAME = 'filament-dbview';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::NAME)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                'create_dbview_query_history_table',
                'create_dbview_saved_queries_table',
            ])
            ->hasCommand(ClearRegistryCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ModelDiscovery::class);
    }
}
