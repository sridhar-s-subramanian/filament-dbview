<x-filament-panels::page>
    @php($options = $this->getTableOptions())

    @if (empty($options))
        <x-filament::section>
            <x-slot name="heading">{{ __('No tables available') }}</x-slot>
            {{ __('No model-backed tables were discovered, or you do not have permission to view any.') }}
        </x-filament::section>
    @else
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <label for="dbview-table" class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('Table') }}
            </label>
            <div class="w-full sm:max-w-xs">
                <x-filament::input.wrapper>
                    <x-filament::input.select id="dbview-table" wire:model.live="selectedTable">
                        @foreach ($options as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        @if ($selectedTable)
            {{ $this->table }}
        @endif
    @endif
</x-filament-panels::page>
