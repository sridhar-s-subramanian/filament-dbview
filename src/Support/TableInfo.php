<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Support;

/**
 * Immutable description of a single model-backed table in the registry.
 *
 * @phpstan-type ForeignKey array{column: string, foreign_table: string, foreign_column: string}
 */
final class TableInfo
{
    /**
     * @param  class-string  $model
     * @param  list<string>  $columns
     * @param  array<string, string>  $columnTypes  column name => category (text|numeric|date|boolean)
     * @param  list<ForeignKey>  $foreignKeys
     */
    public function __construct(
        public readonly string $model,
        public readonly string $table,
        public readonly ?string $connection,
        public readonly ?string $keyName,
        public readonly array $columns,
        public readonly array $columnTypes = [],
        public readonly array $foreignKeys = [],
    ) {}

    public function label(): string
    {
        return class_basename($this->model);
    }

    /**
     * The filter category for a column (text|numeric|date|boolean); defaults to
     * text when the type could not be introspected.
     */
    public function categoryFor(string $column): string
    {
        return $this->columnTypes[$column] ?? 'text';
    }

    /**
     * @return array{model: class-string, table: string, connection: string|null, keyName: string|null, columns: list<string>, columnTypes: array<string, string>, foreignKeys: list<ForeignKey>}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'table' => $this->table,
            'connection' => $this->connection,
            'keyName' => $this->keyName,
            'columns' => $this->columns,
            'columnTypes' => $this->columnTypes,
            'foreignKeys' => $this->foreignKeys,
        ];
    }

    /**
     * @param  array{model: class-string, table: string, connection: string|null, keyName?: string|null, columns: list<string>, columnTypes?: array<string, string>, foreignKeys?: list<ForeignKey>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            model: $data['model'],
            table: $data['table'],
            connection: $data['connection'],
            keyName: $data['keyName'] ?? null,
            columns: $data['columns'],
            columnTypes: $data['columnTypes'] ?? [],
            foreignKeys: $data['foreignKeys'] ?? [],
        );
    }
}
