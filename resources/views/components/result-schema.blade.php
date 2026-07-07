@php
    /** @var list<array{table: string, label: string, indexes: list<array{name: string, columns: list<string>, unique: bool, primary: bool}>, foreignKeys: list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_delete: string|null, on_update: string|null}>}> $schema */
    $schema = $schema ?? [];
@endphp

<style>
    .fdbv-sc { display: flex; flex-direction: column; gap: 1rem; margin-top: .25rem; }
    .fdbv-sc-card { overflow: hidden; border-radius: .625rem; border: 1px solid rgba(0,0,0,.08); background: #fff; }
    .dark .fdbv-sc-card { border-color: rgba(255,255,255,.1); background: rgb(17 24 39); }
    .fdbv-sc-head { display: flex; align-items: baseline; gap: .4rem; padding: .55rem .75rem; border-bottom: 1px solid rgba(0,0,0,.06); font-size: .8rem; font-weight: 600; color: rgb(55 65 81); }
    .dark .fdbv-sc-head { color: rgb(229 231 235); border-color: rgba(255,255,255,.08); }
    .fdbv-sc-tname { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; font-weight: 400; color: rgb(107 114 128); }
    .fdbv-sc-section { padding: .625rem .75rem; }
    .fdbv-sc-section + .fdbv-sc-section { border-top: 1px solid rgba(0,0,0,.05); }
    .dark .fdbv-sc-section + .fdbv-sc-section { border-color: rgba(255,255,255,.06); }
    .fdbv-sc-title { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: rgb(107 114 128); margin-bottom: .4rem; }
    .dark .fdbv-sc-title { color: rgb(156 163 175); }
    .fdbv-sc-empty { font-size: .75rem; color: rgb(156 163 175); }
    .fdbv-sc-scroll { overflow: auto; border: 1px solid rgba(0,0,0,.08); border-radius: .5rem; }
    .dark .fdbv-sc-scroll { border-color: rgba(255,255,255,.1); }
    .fdbv-sc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .75rem; }
    .fdbv-sc-table th { text-align: left; white-space: nowrap; padding: .4rem .6rem; font-weight: 600; color: rgb(55 65 81); background: rgb(249 250 251); border-bottom: 1px solid rgba(0,0,0,.1); }
    .dark .fdbv-sc-table th { color: rgb(229 231 235); background: rgb(31 41 55); border-color: rgba(255,255,255,.1); }
    .fdbv-sc-table td { padding: .38rem .6rem; border-bottom: 1px solid rgba(0,0,0,.05); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; color: rgb(31 41 55); }
    .dark .fdbv-sc-table td { color: rgb(209 213 219); border-color: rgba(255,255,255,.06); }
    .fdbv-sc-table tr:last-child td { border-bottom: 0; }
    .fdbv-sc-badge { display: inline-block; padding: .05rem .35rem; border-radius: .25rem; font-family: ui-sans-serif, system-ui, sans-serif; font-size: .6rem; font-weight: 700; letter-spacing: .03em; }
    .fdbv-sc-badge-pri { color: rgb(146 64 14); background: rgb(254 243 199); }
    .dark .fdbv-sc-badge-pri { color: rgb(253 230 138); background: rgba(146,64,14,.35); }
    .fdbv-sc-badge-uniq { color: rgb(30 64 175); background: rgb(219 234 254); }
    .dark .fdbv-sc-badge-uniq { color: rgb(147 197 253); background: rgba(30,64,175,.35); }
    .fdbv-sc-muted { color: rgb(156 163 175); font-family: ui-sans-serif, system-ui, sans-serif; font-size: .65rem; }
</style>

<div class="fdbv-sc">
    @foreach ($schema as $t)
        <div class="fdbv-sc-card">
            <div class="fdbv-sc-head">
                <span>{{ $t['label'] }}</span>
                @if ($t['label'] !== $t['table'])
                    <span class="fdbv-sc-tname">({{ $t['table'] }})</span>
                @endif
            </div>

            <div class="fdbv-sc-section">
                <div class="fdbv-sc-title">{{ __('Indexes') }}</div>
                @if (empty($t['indexes']))
                    <div class="fdbv-sc-empty">{{ __('No indexes.') }}</div>
                @else
                    <div class="fdbv-sc-scroll">
                        <table class="fdbv-sc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Columns') }}</th>
                                    <th>{{ __('Type') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($t['indexes'] as $ix)
                                    <tr>
                                        <td>{{ $ix['name'] }}</td>
                                        <td>{{ implode(', ', $ix['columns']) }}</td>
                                        <td>
                                            @if ($ix['primary'])
                                                <span class="fdbv-sc-badge fdbv-sc-badge-pri">{{ __('PRIMARY') }}</span>
                                            @elseif ($ix['unique'])
                                                <span class="fdbv-sc-badge fdbv-sc-badge-uniq">{{ __('UNIQUE') }}</span>
                                            @else
                                                <span class="fdbv-sc-muted">{{ __('INDEX') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if (! empty($t['foreignKeys']))
                <div class="fdbv-sc-section">
                    <div class="fdbv-sc-title">{{ __('Foreign keys') }}</div>
                    <div class="fdbv-sc-scroll">
                        <table class="fdbv-sc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Columns') }}</th>
                                    <th>{{ __('References') }}</th>
                                    <th>{{ __('On delete / update') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($t['foreignKeys'] as $fk)
                                    <tr>
                                        <td>{{ implode(', ', $fk['columns']) }}</td>
                                        <td>{{ $fk['foreign_table'] }}({{ implode(', ', $fk['foreign_columns']) }})</td>
                                        <td>
                                            @php($ops = array_filter([
                                                $fk['on_delete'] ? __('delete') . ' ' . $fk['on_delete'] : null,
                                                $fk['on_update'] ? __('update') . ' ' . $fk['on_update'] : null,
                                            ]))
                                            {{ $ops === [] ? '—' : implode(' · ', $ops) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>
