@php
    /** @var list<string> $columns */
    /** @var list<array<string, mixed>> $rows */
    $columns = $columns ?? [];
    $rows = $rows ?? [];
    $truncated = $truncated ?? false;
    $count = $count ?? count($rows);
    $duration = $duration ?? null;

    // Single-column plans (Postgres "QUERY PLAN" over many rows, MySQL EXPLAIN
    // ANALYZE tree in one cell) read best as preformatted text; multi-column
    // plans (e.g. SQLite EXPLAIN QUERY PLAN) fall back to the tabular grid.
    $singleColumn = count($columns) === 1;

    $planText = '';

    if ($singleColumn && $rows !== []) {
        $column = $columns[0];

        $planText = implode("\n", array_map(
            static function (array $row) use ($column): string {
                $value = $row[$column] ?? '';

                if (is_array($value) || is_object($value)) {
                    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }

                return (string) $value;
            },
            $rows,
        ));
    }
@endphp

@if (! $singleColumn)
    @include('filament-dbview::components.results-grid', [
        'columns' => $columns,
        'rows' => $rows,
        'truncated' => $truncated,
        'count' => $count,
        'duration' => $duration,
    ])
@else
    <style>
        .fdbv-ep { display: flex; flex-direction: column; gap: .625rem; }
        .fdbv-ep-meta { display: flex; align-items: center; gap: .5rem; font-size: .75rem; color: rgb(107 114 128); }
        .dark .fdbv-ep-meta { color: rgb(156 163 175); }
        .fdbv-ep-warn { color: rgb(202 138 4); }
        .dark .fdbv-ep-warn { color: rgb(250 204 21); }
        .fdbv-ep-empty { border: 1px dashed rgba(0,0,0,.15); border-radius: .5rem; padding: 1.5rem; text-align: center; font-size: .8rem; color: rgb(107 114 128); }
        .dark .fdbv-ep-empty { border-color: rgba(255,255,255,.15); color: rgb(156 163 175); }
        .fdbv-ep-pre {
            margin: 0; overflow: auto; max-height: 65vh;
            padding: .75rem .875rem; border-radius: .5rem;
            border: 1px solid rgba(0,0,0,.08); background: rgb(249 250 251);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; line-height: 1.55;
            color: rgb(31 41 55); white-space: pre; tab-size: 2;
        }
        .dark .fdbv-ep-pre { border-color: rgba(255,255,255,.1); background: rgb(17 24 39); color: rgb(209 213 219); }
    </style>

    <div class="fdbv-ep">
        <div class="fdbv-ep-meta">
            <span>{{ $columns[0] }}</span>
            @if ($duration !== null)
                <span>·</span>
                <span>{{ round((float) $duration) }} ms</span>
            @endif
            @if ($truncated)
                <span>·</span>
                <span class="fdbv-ep-warn">{{ __('result truncated') }}</span>
            @endif
        </div>

        @if ($planText === '')
            <div class="fdbv-ep-empty">{{ __('No plan returned.') }}</div>
        @else
            <pre class="fdbv-ep-pre">{{ $planText }}</pre>
        @endif
    </div>
@endif
