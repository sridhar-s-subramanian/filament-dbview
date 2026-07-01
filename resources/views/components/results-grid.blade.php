@php
    /** @var list<string> $columns */
    /** @var list<array<string, mixed>> $rows */
    $columns = $columns ?? [];
    $rows = $rows ?? [];
    $truncated = $truncated ?? false;
    $count = $count ?? count($rows);
    $duration = $duration ?? null;

    $render = static function (mixed $value): array {
        if ($value === null) {
            return ['NULL', 'fdbv-rg-null'];
        }
        if (is_bool($value)) {
            return [$value ? 'true' : 'false', 'fdbv-rg-bool'];
        }
        if (is_array($value) || is_object($value)) {
            return [(string) json_encode($value, JSON_UNESCAPED_UNICODE), ''];
        }
        return [(string) $value, ''];
    };
@endphp

<style>
    .fdbv-rg { display: flex; flex-direction: column; gap: .625rem; }
    .fdbv-rg-meta { display: flex; align-items: center; gap: .5rem; font-size: .75rem; color: rgb(107 114 128); }
    .dark .fdbv-rg-meta { color: rgb(156 163 175); }
    .fdbv-rg-warn { color: rgb(202 138 4); }
    .dark .fdbv-rg-warn { color: rgb(250 204 21); }
    .fdbv-rg-empty { border: 1px dashed rgba(0,0,0,.15); border-radius: .5rem; padding: 1.5rem; text-align: center; font-size: .8rem; color: rgb(107 114 128); }
    .dark .fdbv-rg-empty { border-color: rgba(255,255,255,.15); color: rgb(156 163 175); }
    .fdbv-rg-scroll { overflow: auto; max-height: 65vh; border: 1px solid rgba(0,0,0,.08); border-radius: .5rem; }
    .dark .fdbv-rg-scroll { border-color: rgba(255,255,255,.1); }
    .fdbv-rg-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .78rem; }
    .fdbv-rg-table th { position: sticky; top: 0; z-index: 1; text-align: left; white-space: nowrap; padding: .5rem .625rem; font-weight: 600; color: rgb(55 65 81); background: rgb(249 250 251); border-bottom: 1px solid rgba(0,0,0,.1); }
    .dark .fdbv-rg-table th { color: rgb(229 231 235); background: rgb(31 41 55); border-color: rgba(255,255,255,.1); }
    .fdbv-rg-table td { padding: .45rem .625rem; border-bottom: 1px solid rgba(0,0,0,.05); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; color: rgb(31 41 55); max-width: 22rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dark .fdbv-rg-table td { color: rgb(209 213 219); border-color: rgba(255,255,255,.06); }
    .fdbv-rg-table tr:hover td { background: rgba(0,0,0,.02); }
    .dark .fdbv-rg-table tr:hover td { background: rgba(255,255,255,.03); }
    .fdbv-rg-null { color: rgb(156 163 175); font-style: italic; }
    .fdbv-rg-bool { color: rgb(147 51 234); }
    .dark .fdbv-rg-bool { color: rgb(192 132 252); }
</style>

<div class="fdbv-rg">
    <div class="fdbv-rg-meta">
        <span>{{ $count }} {{ \Illuminate\Support\Str::plural('row', $count) }}</span>
        @if ($duration !== null)
            <span>·</span>
            <span>{{ round((float) $duration) }} ms</span>
        @endif
        @if ($truncated)
            <span>·</span>
            <span class="fdbv-rg-warn">{{ __('result truncated') }}</span>
        @endif
    </div>

    @if (empty($rows))
        <div class="fdbv-rg-empty">{{ __('No rows returned.') }}</div>
    @else
        <div class="fdbv-rg-scroll">
            <table class="fdbv-rg-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                @php([$text, $cls] = $render($row[$column] ?? null))
                                <td class="{{ $cls }}" title="{{ \Illuminate\Support\Str::limit($text, 500) }}">{{ \Illuminate\Support\Str::limit($text, 160) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
