<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SridharSSubramanian\FilamentDbview\Support\Authorization;
use SridharSSubramanian\FilamentDbview\Support\DynamicModel;
use SridharSSubramanian\FilamentDbview\Support\ModelDiscovery;
use SridharSSubramanian\FilamentDbview\Support\Redactor;
use SridharSSubramanian\FilamentDbview\Support\TableInfo;
use SridharSSubramanian\FilamentDbview\Support\TableRegistry;
use UnitEnum;

/**
 * Adminer-style browser for a single model-backed table at a time. Reuses
 * Filament's TableBuilder (search / sort / filter / paginate) by wrapping the
 * raw table in a read-only {@see DynamicModel}. Bulk/edit actions are never
 * exposed — the whole page is read-only.
 */
final class DatabaseBrowser extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament-dbview::pages.database-browser';

    public ?string $selectedTable = null;

    public function mount(): void
    {
        $registry = $this->registry();

        if ($this->selectedTable === null || ! $registry->has($this->selectedTable)) {
            $this->selectedTable = $registry->tableNames()[0] ?? null;
        }
    }

    public static function canAccess(): bool
    {
        return Authorization::canAccess();
    }

    public function updatedSelectedTable(): void
    {
        // Switching tables changes the column set entirely; rebuild the table.
        $this->resetTable();
    }

    /**
     * Options for the table picker in the view.
     *
     * @return array<string, string>
     */
    public function getTableOptions(): array
    {
        return $this->registry()->selectOptions();
    }

    public function table(Table $table): Table
    {
        $info = $this->currentTableInfo();

        if (! $info instanceof TableInfo) {
            // No table selected/visible: an empty, harmless query.
            return $table
                ->query(fn(): Builder => DynamicModel::blank()->newQuery()->whereRaw('1 = 0'))
                ->columns([]);
        }

        return $table
            ->query(fn(): Builder => DynamicModel::for($info)->newQuery())
            ->columns($this->columnsFor($info))
            ->recordActions($this->relationshipActions($info))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * @return list<TextColumn>
     */
    private function columnsFor(TableInfo $info): array
    {
        $redactor = new Redactor();

        return array_map(function (string $column) use ($redactor): TextColumn {
            $col = TextColumn::make($column)
                ->label($column)
                ->sortable()
                ->toggleable()
                ->wrap();

            if ($redactor->redacts($column)) {
                // Never send the real value to the browser for sensitive columns.
                $col->formatStateUsing(fn(): string => $redactor->mask())
                    ->searchable(false);
            } else {
                $col->searchable();
            }

            return $col;
        }, $info->columns);
    }

    /**
     * One modal action per foreign key, showing the related rows for a record
     * via a scoped, read-only query. Disabled when the feature is off.
     *
     * @return list<Action>
     */
    private function relationshipActions(TableInfo $info): array
    {
        if (! config('filament-dbview.features.relationship_preview', true)) {
            return [];
        }

        $registry = $this->registry();
        $actions = [];

        foreach ($info->foreignKeys as $fk) {
            if (! $registry->has($fk['foreign_table'])) {
                continue;
            }

            $foreign = $registry->get($fk['foreign_table']);

            if (! $foreign instanceof TableInfo) {
                continue;
            }

            $actions[] = Action::make('dbview_fk_' . $fk['column'])
                ->label('→ ' . $foreign->label())
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('gray')
                ->modalHeading('Related ' . $foreign->label())
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (mixed $record) use ($fk, $foreign) {
                    $value = $record->getAttribute($fk['column']);
                    $result = $this->relatedRows($foreign, $fk['foreign_column'], $value);

                    return view('filament-dbview::components.results-grid', [
                        'columns' => $result->columns,
                        'rows' => $result->rows,
                        'truncated' => $result->truncated,
                        'count' => $result->rowCount,
                        'duration' => $result->durationMs,
                    ]);
                });
        }

        return $actions;
    }

    private function relatedRows(TableInfo $foreign, string $foreignColumn, mixed $value): \SridharSSubramanian\FilamentDbview\Support\ResultSet
    {
        $redactor = new Redactor();
        $limit = (int) config('filament-dbview.limits.default_rows', 100);

        $start = microtime(true);

        // Parameter-bound Eloquent query on a read-only dynamic model: no SQL
        // string is ever assembled from user input (OWASP A03).
        $rows = $value === null
            ? []
            : DynamicModel::for($foreign)
                ->newQuery()
                ->where($foreignColumn, $value)
                ->limit($limit)
                ->get()
                ->map(static fn($model): array => $model->getAttributes())
                ->all();

        return \SridharSSubramanian\FilamentDbview\Support\ResultSet::fromRows(
            rows: array_values($rows),
            redactor: $redactor,
            connection: $foreign->connection ?? (string) config('database.default'),
            durationMs: (microtime(true) - $start) * 1000,
            maxBytes: (int) config('filament-dbview.limits.max_result_bytes', 5 * 1024 * 1024),
        );
    }

    private function currentTableInfo(): ?TableInfo
    {
        if ($this->selectedTable === null) {
            return null;
        }

        return $this->registry()->get($this->selectedTable);
    }

    private function registry(): TableRegistry
    {
        return app(ModelDiscovery::class)->registry()->visibleTo(filament()->auth()->user());
    }

    public function getTitle(): string
    {
        return __('Database Browser');
    }

    public static function getNavigationLabel(): string
    {
        return __('Database Browser');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return config('filament-dbview.navigation.browser_icon', 'heroicon-o-table-cells');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-dbview.navigation.group', 'Database');
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-dbview.navigation.sort', 90);
    }
}
