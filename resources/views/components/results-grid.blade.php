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
            return ['NULL', 'text-gray-400 dark:text-gray-500 italic'];
        }
        if (is_bool($value)) {
            return [$value ? 'true' : 'false', 'text-primary-600 dark:text-primary-400'];
        }
        if (is_array($value) || is_object($value)) {
            return [json_encode($value, JSON_UNESCAPED_UNICODE) ?: '', ''];
        }
        return [(string) $value, ''];
    };
@endphp

<div class="space-y-3">
    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
        <span>{{ $count }} {{ \Illuminate\Support\Str::plural('row', $count) }}</span>
        @if ($duration !== null)
            <span>·</span>
            <span>{{ round((float) $duration) }} ms</span>
        @endif
        @if ($truncated)
            <span>·</span>
            <span class="text-warning-600 dark:text-warning-400">{{ __('result truncated') }}</span>
        @endif
    </div>

    @if (empty($rows))
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-white/10 p-6 text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('No rows returned.') }}
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10">
            <table class="w-full text-start text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        @foreach ($columns as $column)
                            <th class="whitespace-nowrap px-3 py-2 text-start font-semibold text-gray-700 dark:text-gray-200">
                                {{ $column }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            @foreach ($columns as $column)
                                @php([$text, $classes] = $render($row[$column] ?? null))
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300 {{ $classes }}">
                                    {{ \Illuminate\Support\Str::limit($text, 200) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
