<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

use Illuminate\Support\Facades\Gate;

/**
 * The set of model-backed tables discovered for the viewer. The Database
 * Browser is always limited to this set (filtered by table_gate when set).
 * The Query Runner uses it for models scope; connection scope can add other
 * real tables beyond this registry.
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
     * A new registry containing only the tables the given user may view.
     * When table_gate is unset, all tables in this registry stay visible.
     * When table_gate is set, only tables for which the Gate allows the
     * ability (with the table name as argument) remain.
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
