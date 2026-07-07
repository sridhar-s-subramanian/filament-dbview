<?php

declare(strict_types=1);

namespace SridharSSubramanian\FilamentDbview\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use SridharSSubramanian\FilamentDbview\Exceptions\UnsafeQueryException;
use SridharSSubramanian\FilamentDbview\Exports\ResultExporter;
use SridharSSubramanian\FilamentDbview\Models\DbviewQueryHistory;
use SridharSSubramanian\FilamentDbview\Models\DbviewSavedQuery;
use SridharSSubramanian\FilamentDbview\Support\Authorization;
use SridharSSubramanian\FilamentDbview\Support\ConnectionResolver;
use SridharSSubramanian\FilamentDbview\Support\ReadOnlyGuard;
use SridharSSubramanian\FilamentDbview\Support\ResultSet;
use SridharSSubramanian\FilamentDbview\Support\SchemaInspector;
use SridharSSubramanian\FilamentDbview\Support\SqlAnalyzer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Adminer-style raw SELECT runner. Every submission passes through
 * {@see ReadOnlyGuard} (single read-only statement, table scope, forced LIMIT,
 * rolled-back transaction, optional read-only connection) before execution.
 * Ad-hoc column shapes are rendered by a custom grid rather than the
 * Eloquent-bound TableBuilder.
 */
final class QueryRunner extends Page
{
    protected string $view = 'filament-dbview::pages.query-runner';

    public ?string $sql = null;

    public ?string $connection = null;

    public ?int $rowLimit = null;

    public bool $hasRun = false;

    public bool $isExplain = false;

    public ?string $error = null;

    /** @var list<string> */
    public array $resultColumns = [];

    /** @var list<array<string, mixed>> */
    public array $resultRows = [];

    public int $resultCount = 0;

    public bool $resultTruncated = false;

    public float $resultDurationMs = 0.0;

    public function mount(): void
    {
        $this->connection ??= app(ConnectionResolver::class)->allowed()[0] ?? (string) config('database.default');
        $this->rowLimit ??= (int) config('filament-dbview.limits.default_rows', 100);
    }

    public static function canAccess(): bool
    {
        return Authorization::canRunQueries();
    }

    /**
     * @return array<string, string>
     */
    public function getConnectionOptions(): array
    {
        $names = app(ConnectionResolver::class)->allowed();

        return array_combine($names, $names);
    }

    /**
     * Real table names on the current connection, shown as a reference list when
     * the runner is in "connection" scope. Empty in the default "models" scope.
     *
     * @return list<string>
     */
    public function getAllTables(): array
    {
        if (config('filament-dbview.query_runner.scope', 'models') !== 'connection') {
            return [];
        }

        $deny = array_map('strtolower', (array) config('filament-dbview.query_runner.deny', []));

        $tables = array_values(array_filter(
            app(ConnectionResolver::class)->tables($this->connection),
            static fn(string $table): bool => ! in_array(strtolower($table), $deny, true),
        ));

        sort($tables);

        return $tables;
    }

    /**
     * Indexes + foreign keys of the tables referenced by the last successful
     * (non-EXPLAIN) query, for the Adminer-style schema panel under the results.
     *
     * @return list<array{table: string, label: string, indexes: list<array{name: string, columns: list<string>, unique: bool, primary: bool}>, foreignKeys: list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null, on_update: string|null}>}>
     */
    public function getResultSchema(): array
    {
        if (! config('filament-dbview.features.result_schema', true)) {
            return [];
        }

        if (! $this->hasRun || $this->error !== null || $this->isExplain || $this->sql === null) {
            return [];
        }

        $tables = SqlAnalyzer::of((string) $this->sql)->tables;

        if ($tables === []) {
            return [];
        }

        return app(SchemaInspector::class)->for($tables, $this->connection, Auth::user());
    }

    public function run(): void
    {
        $this->dispatchQuery(
            fn(ReadOnlyGuard $guard): ResultSet => $guard->run(
                sql: (string) $this->sql,
                connection: $this->connection,
                limit: $this->rowLimit,
                user: Auth::user(),
            ),
            isExplain: false,
        );
    }

    public function explain(): void
    {
        if (! config('filament-dbview.features.explain', true)) {
            return;
        }

        $this->dispatchQuery(
            fn(ReadOnlyGuard $guard): ResultSet => $guard->explain(
                sql: (string) $this->sql,
                connection: $this->connection,
                limit: $this->rowLimit,
                user: Auth::user(),
                analyze: false,
            ),
            isExplain: true,
        );
    }

    public function explainAnalyze(): void
    {
        if (! config('filament-dbview.features.explain', true)) {
            return;
        }

        $this->dispatchQuery(
            fn(ReadOnlyGuard $guard): ResultSet => $guard->explain(
                sql: (string) $this->sql,
                connection: $this->connection,
                limit: $this->rowLimit,
                user: Auth::user(),
                analyze: true,
            ),
            isExplain: true,
        );
    }

    /**
     * Shared execution wrapper for run/explain: reset state, invoke the guard,
     * store the result, and surface errors safely (never leaking driver
     * internals — OWASP A09).
     *
     * @param  \Closure(ReadOnlyGuard): ResultSet  $runner
     */
    private function dispatchQuery(\Closure $runner, bool $isExplain): void
    {
        $this->reset('error', 'hasRun', 'isExplain', 'resultColumns', 'resultRows', 'resultCount', 'resultTruncated', 'resultDurationMs');

        try {
            $result = $runner(app(ReadOnlyGuard::class));

            $this->applyResult($result);
            $this->isExplain = $isExplain;
            $this->hasRun = true;

            Notification::make()
                ->title($isExplain
                    ? __('Plan generated in :ms ms', ['ms' => round($result->durationMs)])
                    : $result->rowCount . ' row(s) returned in ' . round($result->durationMs) . ' ms')
                ->success()
                ->send();
        } catch (UnsafeQueryException $e) {
            // Safe, user-facing validation message shown inline (no toast).
            $this->error = $e->getMessage();
            $this->hasRun = true;
        } catch (Throwable $e) {
            // Never leak driver internals / stack traces to the UI (OWASP A09).
            Log::error('filament-dbview query execution failed', ['exception' => $e]);
            $this->error = 'The query could not be executed. Please check the syntax and try again.';
            $this->hasRun = true;
        }
    }

    private function applyResult(ResultSet $result): void
    {
        $this->resultColumns = $result->columns;
        $this->resultRows = $result->rows;
        $this->resultCount = $result->rowCount;
        $this->resultTruncated = $result->truncated;
        $this->resultDurationMs = round($result->durationMs, 2);
    }

    private function currentResultSet(): ResultSet
    {
        return new ResultSet(
            columns: $this->resultColumns,
            rows: $this->resultRows,
            rowCount: $this->resultCount,
            truncated: $this->resultTruncated,
            durationMs: $this->resultDurationMs,
            connection: (string) $this->connection,
        );
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        // Run lives in the editor toolbar (with ⌘/Ctrl+Enter); header keeps the
        // result-oriented actions only.
        $actions = [];

        if (config('filament-dbview.features.export', true)) {
            $actions[] = Action::make('exportCsv')
                ->label('CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn(): bool => $this->hasRun && $this->resultRows !== [])
                ->action(fn(): StreamedResponse => ResultExporter::csv($this->currentResultSet()));

            $actions[] = Action::make('exportJson')
                ->label('JSON')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn(): bool => $this->hasRun && $this->resultRows !== [])
                ->action(fn(): StreamedResponse => ResultExporter::json($this->currentResultSet()));
        }

        if (config('filament-dbview.features.saved_queries', true)) {
            $actions[] = Action::make('save')
                ->label('Save')
                ->icon('heroicon-m-bookmark')
                ->color('gray')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->saveQuery((string) $data['name']);
                });
        }

        return $actions;
    }

    private function saveQuery(string $name): void
    {
        if (in_array(trim((string) $this->sql), ['', '0'], true)) {
            return;
        }

        DbviewSavedQuery::query()->create([
            'user_id' => Auth::id(),
            'name' => $name,
            'connection' => (string) $this->connection,
            'sql' => (string) $this->sql,
        ]);

        Notification::make()->title('Query saved')->success()->send();
    }

    public function loadSaved(int $id): void
    {
        $saved = DbviewSavedQuery::query()
            ->where('user_id', Auth::id())
            ->find($id);

        if ($saved instanceof DbviewSavedQuery) {
            $this->sql = $saved->sql;
            $this->connection = $saved->connection;
        }
    }

    public function loadHistory(int $id): void
    {
        $entry = DbviewQueryHistory::query()
            ->where('user_id', Auth::id())
            ->find($id);

        if ($entry instanceof DbviewQueryHistory) {
            $this->sql = $entry->sql;
            $this->connection = $entry->connection;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, DbviewSavedQuery>
     */
    public function getSavedQueries(): \Illuminate\Support\Collection
    {
        if (! config('filament-dbview.features.saved_queries', true)) {
            return collect();
        }

        return DbviewSavedQuery::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, DbviewQueryHistory>
     */
    public function getHistory(): \Illuminate\Support\Collection
    {
        if (! config('filament-dbview.features.history', true)) {
            return collect();
        }

        return DbviewQueryHistory::query()
            ->where('user_id', Auth::id())
            ->where('allowed', true)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function viewRowAction(): Action
    {
        return Action::make('viewRow')
            ->slideOver()
            ->modalHeading(fn(array $arguments): string => __('Row #') . ((int) ($arguments['index'] ?? 0) + 1))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalContent(function (array $arguments) {
                $index = (int) ($arguments['index'] ?? 0);
                $row = $this->resultRows[$index] ?? null;

                if ($row === null) {
                    return null;
                }

                return view('filament-dbview::components.record-detail', [
                    'columns' => array_keys($row),
                    'row' => $row,
                ]);
            });
    }

    public function getTitle(): string
    {
        return __('Query Runner');
    }

    public static function getNavigationLabel(): string
    {
        return __('Query Runner');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return config('filament-dbview.navigation.runner_icon', 'heroicon-o-command-line');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-dbview.navigation.group', 'Database');
    }

    public static function getNavigationSort(): int
    {
        return (int) config('filament-dbview.navigation.sort', 90) + 1;
    }
}
