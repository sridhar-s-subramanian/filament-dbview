<x-filament-panels::page>
    <style>
        .fdbv-qr { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
        @media (min-width: 1024px) { .fdbv-qr { grid-template-columns: minmax(0, 1fr) 18rem; } }
        .fdbv-qr-main { min-width: 0; display: flex; flex-direction: column; gap: 1rem; }
        .fdbv-qr-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .625rem; }
        .fdbv-qr-field { display: flex; flex-direction: column; gap: .25rem; }
        .fdbv-qr-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: rgb(107 114 128); }

        .fdbv-qr-editor { position: relative; }
        .fdbv-qr-textarea {
            display: block; width: 100%; min-height: 12rem; resize: vertical;
            padding: .75rem .875rem; border-radius: .625rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; line-height: 1.6; tab-size: 2;
            color: rgb(31 41 55); background: #fff;
            border: 1px solid rgb(209 213 219); box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        .fdbv-qr-textarea::placeholder { color: rgb(156 163 175); }
        .fdbv-qr-textarea:focus { outline: none; border-color: rgb(var(--primary-500)); box-shadow: 0 0 0 2px rgb(var(--primary-500) / .3); }
        .dark .fdbv-qr-textarea { color: rgb(229 231 235); background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .dark .fdbv-qr-textarea::placeholder { color: rgb(107 114 128); }
        .fdbv-qr-kbd { position: absolute; right: .625rem; bottom: .625rem; padding: .1rem .4rem; border-radius: .3rem; font-size: .65rem; font-family: ui-monospace, monospace; color: rgb(107 114 128); background: rgba(0,0,0,.05); pointer-events: none; }
        .dark .fdbv-qr-kbd { color: rgb(156 163 175); background: rgba(255,255,255,.08); }

        .fdbv-qr-hint { font-size: .72rem; color: rgb(107 114 128); }
        .dark .fdbv-qr-hint { color: rgb(156 163 175); }
        .fdbv-qr-error { border: 1px solid rgb(252 165 165); background: rgb(254 242 242); color: rgb(185 28 28); border-radius: .5rem; padding: .625rem .75rem; font-size: .8rem; }
        .dark .fdbv-qr-error { border-color: rgba(248,113,113,.3); background: rgba(248,113,113,.1); color: rgb(248 113 113); }

        .fdbv-qr-card { overflow: hidden; border-radius: .75rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .dark .fdbv-qr-card { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .fdbv-qr-card + .fdbv-qr-card { margin-top: 1.25rem; }
        .fdbv-qr-card-head { display: flex; align-items: center; justify-content: space-between; padding: .55rem .75rem; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: rgb(107 114 128); border-bottom: 1px solid rgba(0,0,0,.06); }
        .dark .fdbv-qr-card-head { color: rgb(156 163 175); border-color: rgba(255,255,255,.08); }
        .fdbv-qr-search { padding: .5rem; border-bottom: 1px solid rgba(0,0,0,.06); }
        .dark .fdbv-qr-search { border-color: rgba(255,255,255,.08); }
        .fdbv-qr-list { display: flex; flex-direction: column; padding: .375rem; gap: 2px; max-height: 40vh; overflow-y: auto; }
        .fdbv-qr-item { display: block; width: 100%; text-align: left; padding: .4rem .55rem; border-radius: .4rem; color: rgb(55 65 81); cursor: pointer; transition: background-color .15s; }
        .fdbv-qr-item:hover { background: rgb(249 250 251); }
        .dark .fdbv-qr-item { color: rgb(209 213 219); }
        .dark .fdbv-qr-item:hover { background: rgba(255,255,255,.05); }
        .fdbv-qr-row { display: flex; align-items: stretch; gap: 2px; }
        .fdbv-qr-row .fdbv-qr-item { flex: 1 1 auto; min-width: 0; }
        .fdbv-qr-struct { display: flex; align-items: center; justify-content: center; flex: 0 0 auto; padding: 0 .4rem; border-radius: .4rem; color: rgb(156 163 175); cursor: pointer; transition: background-color .15s, color .15s; }
        .fdbv-qr-struct:hover { background: rgb(243 244 246); color: rgb(55 65 81); }
        .dark .fdbv-qr-struct:hover { background: rgba(255,255,255,.08); color: rgb(229 231 235); }
        .fdbv-qr-struct-icon { width: .85rem; height: .85rem; }
        .fdbv-qr-browse { display: flex; align-items: center; justify-content: center; flex: 0 0 auto; padding: 0 .4rem; border-radius: .4rem; color: rgb(156 163 175); transition: background-color .15s, color .15s; }
        .fdbv-qr-browse:hover { background: rgb(243 244 246); color: rgb(55 65 81); }
        .dark .fdbv-qr-browse:hover { background: rgba(255,255,255,.08); color: rgb(229 231 235); }
        .fdbv-qr-browse-icon { width: .8rem; height: .8rem; }
        .fdbv-qr-item .n { display: block; font-size: .8rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fdbv-qr-item .q { display: block; font-family: ui-monospace, monospace; font-size: .68rem; color: rgb(156 163 175); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fdbv-qr-tname { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; }
        .fdbv-qr-empty { padding: .75rem; font-size: .78rem; color: rgb(156 163 175); }
    </style>

    <div
        class="fdbv-qr"
        x-data="{
            tq: '',
            insert(t) {
                const ta = this.$refs.editor;
                if (! ta) return;
                const start = ta.selectionStart ?? ta.value.length;
                const end = ta.selectionEnd ?? ta.value.length;
                const before = ta.value.slice(0, start);
                const needsSpace = before.length && ! /\s$/.test(before);
                ta.setRangeText((needsSpace ? ' ' : '') + t, start, end, 'end');
                ta.focus();
                ta.dispatchEvent(new Event('input'));
            },
        }"
    >
        {{-- Editor + results --}}
        <div class="fdbv-qr-main">
            <div class="fdbv-qr-toolbar">
                <div class="fdbv-qr-field" style="flex: 1 1 16rem;">
                    <span class="fdbv-qr-label">{{ __('Connection') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="connection">
                            @foreach ($this->getConnectionOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="fdbv-qr-field" style="width: 8rem;">
                    <span class="fdbv-qr-label">{{ __('Row limit') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" min="1" wire:model="rowLimit" />
                    </x-filament::input.wrapper>
                </div>

                <div class="fdbv-qr-field" style="align-self: flex-end; flex-direction: row; gap: .5rem;">
                    <x-filament::button wire:click="run" wire:loading.attr="disabled" wire:target="run,explain,explainAnalyze" icon="heroicon-m-play">
                        {{ __('Run') }}
                    </x-filament::button>

                    @if (config('filament-dbview.features.explain', true))
                        <x-filament::button wire:click="explain" wire:loading.attr="disabled" wire:target="run,explain,explainAnalyze" color="gray" icon="heroicon-m-list-bullet">
                            {{ __('Explain') }}
                        </x-filament::button>

                        <x-filament::button wire:click="explainAnalyze" wire:loading.attr="disabled" wire:target="run,explain,explainAnalyze" color="gray" icon="heroicon-m-bolt">
                            {{ __('Explain Analyze') }}
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <div
                class="fdbv-qr-editor"
                x-on:keydown.ctrl.enter.prevent="$wire.run()"
                x-on:keydown.meta.enter.prevent="$wire.run()"
            >
                <textarea
                    x-ref="editor"
                    wire:model="sql"
                    spellcheck="false"
                    placeholder="SELECT * FROM users"
                    class="fdbv-qr-textarea"
                ></textarea>
                <span class="fdbv-qr-kbd">⌘/Ctrl + ⏎</span>
            </div>

            <p class="fdbv-qr-hint">
                {{ __('Read-only. A single SELECT (or WITH … SELECT) statement.') }}
            </p>

            <div wire:loading.flex wire:target="run,explain,explainAnalyze" class="fdbv-qr-hint" style="align-items:center; gap:.4rem;">
                {{ __('Running…') }}
            </div>

            @if ($error)
                <div class="fdbv-qr-error">{{ $error }}</div>
            @endif

            @if ($isStructure)
                @include('filament-dbview::components.structure', ['structure' => $structure])
            @elseif ($hasRun && ! $error)
                @if ($isExplain)
                    @include('filament-dbview::components.explain-plan', [
                        'columns' => $resultColumns,
                        'rows' => $resultRows,
                        'truncated' => $resultTruncated,
                        'count' => $resultCount,
                        'duration' => $resultDurationMs,
                    ])
                @else
                    @include('filament-dbview::components.results-grid', [
                        'columns' => $resultColumns,
                        'rows' => $resultRows,
                        'truncated' => $resultTruncated,
                        'count' => $resultCount,
                        'duration' => $resultDurationMs,
                    ])
                @endif
            @endif
        </div>

        {{-- Sidebar --}}
        <aside>
            @php($allTables = $this->getAllTables())
            @php($browsable = array_flip($this->getBrowsableTableNames()))
            @php($canBrowse = \SridharSSubramanian\FilamentDbview\Pages\DatabaseBrowser::canAccess())

            @if (! empty($allTables))
                <div class="fdbv-qr-card">
                    <div class="fdbv-qr-card-head">
                        <span>{{ __('Tables') }} ({{ count($allTables) }})</span>
                    </div>
                    <div class="fdbv-qr-search">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input type="search" placeholder="{{ __('Search tables…') }}" x-model="tq" />
                        </x-filament::input.wrapper>
                    </div>
                    <div class="fdbv-qr-list">
                        @foreach ($allTables as $t)
                            <div
                                class="fdbv-qr-row"
                                x-show="tq === '' || @js(strtolower($t)).includes(tq.toLowerCase())"
                            >
                                @if (config('filament-dbview.features.structure', true))
                                    <button
                                        type="button"
                                        class="fdbv-qr-struct"
                                        wire:click="showStructure('{{ $t }}')"
                                        wire:loading.attr="disabled"
                                        title="{{ __('Show structure') }}"
                                    >
                                        @svg('heroicon-m-table-cells', 'fdbv-qr-struct-icon')
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    class="fdbv-qr-item"
                                    x-on:click="insert(@js($t))"
                                    title="{{ __('Insert into query') }}"
                                >
                                    <span class="fdbv-qr-tname">{{ $t }}</span>
                                </button>

                                @if ($canBrowse && isset($browsable[$t]))
                                    <a
                                        class="fdbv-qr-browse"
                                        href="{{ \SridharSSubramanian\FilamentDbview\Pages\DatabaseBrowser::getUrl() }}?table={{ urlencode($t) }}"
                                        title="{{ __('Browse in Database Browser') }}"
                                    >
                                        @svg('heroicon-m-arrow-top-right-on-square', 'fdbv-qr-browse-icon')
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (config('filament-dbview.features.saved_queries', true))
                <div class="fdbv-qr-card">
                    <div class="fdbv-qr-card-head">{{ __('Saved queries') }}</div>
                    <div class="fdbv-qr-list">
                        @forelse ($this->getSavedQueries() as $item)
                            <button type="button" wire:click="loadSaved({{ $item->id }})" class="fdbv-qr-item" title="{{ $item->sql }}">
                                <span class="n">{{ $item->name }}</span>
                                <span class="q">{{ \Illuminate\Support\Str::limit($item->sql, 42) }}</span>
                            </button>
                        @empty
                            <p class="fdbv-qr-empty">{{ __('None yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if (config('filament-dbview.features.history', true))
                <div class="fdbv-qr-card">
                    <div class="fdbv-qr-card-head">{{ __('Recent queries') }}</div>
                    <div class="fdbv-qr-list">
                        @forelse ($this->getHistory() as $item)
                            <button type="button" wire:click="loadHistory({{ $item->id }})" class="fdbv-qr-item" title="{{ $item->sql }}">
                                <span class="q">{{ \Illuminate\Support\Str::limit($item->sql, 46) }}</span>
                            </button>
                        @empty
                            <p class="fdbv-qr-empty">{{ __('None yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </aside>
    </div>
</x-filament-panels::page>
