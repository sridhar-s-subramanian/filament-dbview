<x-filament-panels::page>
    {{--
        Styles are shipped inline as plain CSS (scoped to .fdbv-*) so the plugin
        does not depend on the host application's Tailwind build scanning these
        Blade files — a class only referenced here would otherwise never be
        generated. Colours reuse Filament's own --primary-* theme variables.
    --}}
    <style>
        .fdbv { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; }
        @media (min-width: 1024px) {
            .fdbv[data-open="true"] { grid-template-columns: 16rem minmax(0, 1fr); }
        }
        .fdbv-aside { min-width: 0; }
        @media (min-width: 1024px) {
            .fdbv-aside { position: sticky; top: 5rem; align-self: start; }
            .fdbv[data-open="false"] .fdbv-aside { display: none; }
        }
        .fdbv-card { overflow: hidden; border-radius: 0.75rem; background: #fff; border: 1px solid rgba(0,0,0,.05); box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .dark .fdbv-card { background: rgb(17 24 39); border-color: rgba(255,255,255,.1); }
        .fdbv-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .625rem .75rem; border-bottom: 1px solid rgba(0,0,0,.06); }
        .dark .fdbv-head { border-color: rgba(255,255,255,.1); }
        .fdbv-head-title { font-size: .7rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: rgb(107 114 128); }
        .fdbv-search { padding: .5rem; }
        .fdbv-list { display: flex; flex-direction: column; gap: 2px; max-height: 62vh; overflow-y: auto; padding: 0 .5rem .5rem; }
        .fdbv-item { display: flex; flex-direction: column; width: 100%; text-align: left; padding: .4rem .6rem; border-radius: .5rem; color: rgb(55 65 81); transition: background-color .15s ease; cursor: pointer; }
        .fdbv-item:hover { background: rgb(249 250 251); }
        .dark .fdbv-item { color: rgb(209 213 219); }
        .dark .fdbv-item:hover { background: rgba(255,255,255,.05); }
        .fdbv-item[data-active="true"] { background: rgb(var(--primary-500) / .12); color: rgb(var(--primary-600)); }
        .dark .fdbv-item[data-active="true"] { color: rgb(var(--primary-400)); }
        .fdbv-item .t { font-size: .8rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fdbv-item .s { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; color: rgb(156 163 175); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fdbv-item[data-active="true"] .s { color: rgb(var(--primary-500) / .8); }
        .fdbv-reopen { margin-bottom: 1rem; }
        @media (min-width: 1024px) { .fdbv[data-open="true"] .fdbv-reopen { display: none; } }
        @media (max-width: 1023px) { .fdbv-reopen, .fdbv-collapse { display: none; } }
        .fdbv-main { min-width: 0; transition: opacity .15s ease; }
        .fdbv-main.fdbv-dim { opacity: .55; }
    </style>

    @php($tables = $this->getBrowsableTables())

    @if (empty($tables))
        <x-filament::section>
            <x-slot name="heading">{{ __('No tables available') }}</x-slot>
            {{ __('No model-backed tables were discovered, or you do not have permission to view any.') }}
        </x-filament::section>
    @else
        <div
            x-data="{ open: (localStorage.getItem('fdbv-sidebar-open') ?? 'true') !== 'false', q: '' }"
            x-init="$watch('open', value => localStorage.setItem('fdbv-sidebar-open', value))"
            :data-open="open ? 'true' : 'false'"
            data-open="true"
            class="fdbv"
        >
            {{-- Sidebar: searchable, collapsible table navigator --}}
            <aside class="fdbv-aside">
                <div class="fdbv-card">
                    <div class="fdbv-head">
                        <span class="fdbv-head-title">{{ __('Tables') }} ({{ count($tables) }})</span>
                        <span class="fdbv-collapse">
                            <x-filament::icon-button
                                icon="heroicon-m-chevron-double-left"
                                color="gray"
                                size="sm"
                                :label="__('Collapse')"
                                x-on:click="open = false"
                            />
                        </span>
                    </div>

                    <div class="fdbv-search">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input
                                type="search"
                                placeholder="{{ __('Search tables…') }}"
                                x-model="q"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <nav class="fdbv-list">
                        @foreach ($tables as $t)
                            <button
                                type="button"
                                wire:click="selectTable('{{ $t['table'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="selectTable"
                                class="fdbv-item"
                                data-active="{{ $selectedTable === $t['table'] ? 'true' : 'false' }}"
                                x-show="q === '' || @js(strtolower($t['label'].' '.$t['table'])).includes(q.toLowerCase())"
                            >
                                <span class="t">{{ $t['label'] }}</span>
                                <span class="s">{{ $t['table'] }}</span>
                            </button>
                        @endforeach
                    </nav>
                </div>
            </aside>

            {{-- Main: the selected table --}}
            <div
                class="fdbv-main"
                wire:target="selectTable"
                wire:loading.class="fdbv-dim"
            >
                <div class="fdbv-reopen">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-bars-3"
                        x-on:click="open = true"
                    >
                        {{ __('Tables') }}
                    </x-filament::button>
                </div>

                @if ($selectedTable)
                    {{ $this->table }}
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
