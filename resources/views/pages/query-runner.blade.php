<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-4">
        {{-- Editor + results --}}
        <div class="lg:col-span-3 space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="connection">
                            @foreach ($this->getConnectionOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <x-filament::input.wrapper prefix="{{ __('Limit') }}">
                        <x-filament::input type="number" min="1" wire:model="rowLimit" />
                    </x-filament::input.wrapper>
                </div>
            </div>

            <div
                x-data
                x-on:keydown.ctrl.enter.prevent="$wire.run()"
                x-on:keydown.meta.enter.prevent="$wire.run()"
            >
                <textarea
                    wire:model="sql"
                    rows="8"
                    spellcheck="false"
                    placeholder="SELECT * FROM users"
                    class="block w-full rounded-lg border-gray-300 bg-white font-mono text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-200"
                ></textarea>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Read-only. A single SELECT (or WITH … SELECT) statement, scoped to your models. Ctrl/⌘+Enter to run.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <x-filament::button wire:click="run" wire:loading.attr="disabled" icon="heroicon-m-play">
                    {{ __('Run') }}
                </x-filament::button>
                <span wire:loading wire:target="run" class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Running…') }}
                </span>
            </div>

            @if ($error)
                <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-400/30 dark:bg-danger-400/10 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif

            @if ($hasRun && ! $error)
                @include('filament-dbview::components.results-grid', [
                    'columns' => $resultColumns,
                    'rows' => $resultRows,
                    'truncated' => $resultTruncated,
                    'count' => $resultCount,
                    'duration' => $resultDurationMs,
                ])
            @endif
        </div>

        {{-- Sidebar: saved queries + history --}}
        <div class="space-y-6">
            @if (config('filament-dbview.features.saved_queries', true))
                <x-filament::section compact>
                    <x-slot name="heading">{{ __('Saved queries') }}</x-slot>
                    @php($saved = $this->getSavedQueries())
                    @forelse ($saved as $item)
                        <button
                            type="button"
                            wire:click="loadSaved({{ $item->id }})"
                            class="block w-full truncate rounded px-2 py-1 text-start text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                            title="{{ $item->sql }}"
                        >
                            {{ $item->name }}
                        </button>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('None yet.') }}</p>
                    @endforelse
                </x-filament::section>
            @endif

            @if (config('filament-dbview.features.history', true))
                <x-filament::section compact>
                    <x-slot name="heading">{{ __('Recent queries') }}</x-slot>
                    @php($history = $this->getHistory())
                    @forelse ($history as $item)
                        <button
                            type="button"
                            wire:click="loadHistory({{ $item->id }})"
                            class="block w-full truncate rounded px-2 py-1 text-start font-mono text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                            title="{{ $item->sql }}"
                        >
                            {{ \Illuminate\Support\Str::limit($item->sql, 40) }}
                        </button>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('None yet.') }}</p>
                    @endforelse
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
