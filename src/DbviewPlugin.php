<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview;

use Filament\Contracts\Plugin;
use Filament\Panel;
use SridharSSubramanian\FilamentDbview\Pages\DatabaseBrowser;
use SridharSSubramanian\FilamentDbview\Pages\QueryRunner;

final class DbviewPlugin implements Plugin
{
    private ?string $queryRunnerScope = null;

    /** @var list<string>|null */
    private ?array $queryRunnerDeny = null;

    private ?bool $history = null;

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

    /**
     * Scope the Query Runner: 'models' (only model-backed tables) or
     * 'connection' (any real table on an allowed connection). The Database
     * Browser is always limited to models regardless of this setting.
     */
    public function queryRunnerScope(string $scope): static
    {
        $this->queryRunnerScope = $scope;

        return $this;
    }

    /**
     * Convenience: allow the Query Runner to query every table on the
     * connection (equivalent to queryRunnerScope('connection')).
     */
    public function allTables(bool $condition = true): static
    {
        return $this->queryRunnerScope($condition ? 'connection' : 'models');
    }

    /**
     * Tables that stay blocked even in 'connection' scope.
     *
     * @param  list<string>  $tables
     */
    public function denyTables(array $tables): static
    {
        $this->queryRunnerDeny = $tables;

        return $this;
    }

    /**
     * Enable the query-history feature: persist every allowed/denied query to
     * dbview_query_history and show the per-user history panel in the Query
     * Runner. Off by default so the table stays empty on busy panels (the
     * migration still ships). PSR-3 audit logging is unaffected and always runs.
     */
    public function history(bool $condition = true): static
    {
        $this->history = $condition;

        return $this;
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
        $this->applyConfiguration();
    }

    /**
     * Push any fluent plugin settings onto the runtime config so the rest of
     * the package (which reads config()) honours them. Config-file values are
     * used as defaults when a setter was not called.
     */
    public function applyConfiguration(): void
    {
        if ($this->queryRunnerScope !== null) {
            config()->set('filament-dbview.query_runner.scope', $this->queryRunnerScope);
        }

        if ($this->queryRunnerDeny !== null) {
            config()->set('filament-dbview.query_runner.deny', $this->queryRunnerDeny);
        }

        if ($this->history !== null) {
            config()->set('filament-dbview.features.history', $this->history);
        }
    }
}
