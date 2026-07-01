<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Commands;

use Illuminate\Console\Command;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;

final class ClearRegistryCommand extends Command
{
    protected $signature = 'filament-dbview:clear';

    protected $description = 'Clear the cached filament-dbview model/table registry.';

    public function handle(ModelDiscovery $discovery): int
    {
        $discovery->forget();

        $this->info('filament-dbview registry cache cleared.');

        return self::SUCCESS;
    }
}
