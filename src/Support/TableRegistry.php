<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Gate;

/**
 * The set of model-backed tables the viewer may touch. This is the hard
 * allowlist: any table not present here is unreachable by design.
 */
final class TableRegistry
{
    /**
     * @param  array<string, TableInfo>  $tables  keyed by table name
     */
    public function __construct(
        private readonly array $tables,
    ) {}

    /**
     * @return array<string, TableInfo>
     */
    public function all(): array
    {
        return $this->tables;
    }

    public function has(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function get(string $table): ?TableInfo
    {
        return $this->tables[$table] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_keys($this->tables);
    }

    /**
     * A new registry containing only the tables the given user may view,
     * according to the configured per-table gate (deny-by-default when the
     * gate denies). No gate configured => all tables visible.
     */
    public function visibleTo(mixed $user): self
    {
        $gate = config('filament-dbview.authorization.table_gate');

        if (! is_string($gate) || $gate === '') {
            return $this;
        }

        $filtered = array_filter(
            $this->tables,
            static fn(TableInfo $info): bool => Gate::forUser($user)->allows($gate, $info->table),
        );

        return new self($filtered);
    }

    /**
     * Options suitable for a Filament select field: table name => label.
     *
     * @return array<string, string>
     */
    public function selectOptions(): array
    {
        $options = [];

        foreach ($this->tables as $name => $info) {
            $options[$name] = "{$info->label()} ({$name})";
        }

        asort($options);

        return $options;
    }
}
