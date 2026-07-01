@php
    /** @var list<string> $columns */
    /** @var array<string, mixed> $row */
    $columns = $columns ?? [];
    $row = $row ?? [];

    $treeFor = static function (mixed $value): ?array {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '' || ! in_array($value[0], ['{', '['], true)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    };

    $rawString = static function (mixed $value): string {
        if ($value === null) {
            return '';
        }

        return is_scalar($value)
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
@endphp

<style>
    [x-cloak] { display: none !important; }
    .fdbv-detail { display: flex; flex-direction: column; gap: .625rem; }
    .fdbv-field { border: 1px solid rgba(0,0,0,.07); border-radius: .5rem; overflow: hidden; }
    .dark .fdbv-field { border-color: rgba(255,255,255,.08); }
    .fdbv-field-name { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .3rem .4rem .3rem .625rem; background: rgb(249 250 251); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; font-weight: 600; letter-spacing: .02em; color: rgb(107 114 128); border-bottom: 1px solid rgba(0,0,0,.06); }
    .dark .fdbv-field-name { background: rgba(255,255,255,.04); color: rgb(156 163 175); border-color: rgba(255,255,255,.08); }
    .fdbv-copy { display: inline-flex; align-items: center; gap: .25rem; padding: .1rem .4rem; border-radius: .3rem; font-size: .65rem; font-weight: 600; color: rgb(107 114 128); background: transparent; border: 1px solid rgba(0,0,0,.1); cursor: pointer; transition: background-color .15s, color .15s; }
    .fdbv-copy:hover { background: rgba(0,0,0,.04); color: rgb(55 65 81); }
    .dark .fdbv-copy { border-color: rgba(255,255,255,.12); }
    .dark .fdbv-copy:hover { background: rgba(255,255,255,.06); color: rgb(229 231 235); }
    .fdbv-field-value { padding: .5rem .625rem; font-size: .8rem; line-height: 1.5; color: rgb(31 41 55); white-space: pre-wrap; word-break: break-word; }
    .dark .fdbv-field-value { color: rgb(229 231 235); }

    /* JSON tree */
    .fdbv-tree { padding: .4rem .5rem; max-height: 26rem; overflow: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .72rem; line-height: 1.55; }
    .fdbv-node-head { display: inline-flex; align-items: baseline; gap: .25rem; cursor: pointer; user-select: none; border-radius: .25rem; }
    .fdbv-node-head:hover { background: rgba(0,0,0,.03); }
    .dark .fdbv-node-head:hover { background: rgba(255,255,255,.05); }
    .fdbv-caret { display: inline-block; font-size: .55rem; color: rgb(156 163 175); transition: transform .12s ease; transform: rotate(0deg); }
    .fdbv-caret.is-open { transform: rotate(90deg); }
    .fdbv-children { padding-left: .85rem; margin-left: .3rem; border-left: 1px solid rgba(0,0,0,.08); }
    .dark .fdbv-children { border-left-color: rgba(255,255,255,.1); }
    .fdbv-leaf { padding: .05rem 0; }
    .fdbv-key { color: rgb(37 99 235); }
    .dark .fdbv-key { color: rgb(96 165 250); }
    .fdbv-punc { color: rgb(156 163 175); }
    .fdbv-meta { color: rgb(156 163 175); }
    .fdbv-str { color: rgb(22 163 74); word-break: break-word; }
    .dark .fdbv-str { color: rgb(74 222 128); }
    .fdbv-num { color: rgb(217 119 6); }
    .dark .fdbv-num { color: rgb(251 191 36); }
    .fdbv-bool { color: rgb(147 51 234); }
    .dark .fdbv-bool { color: rgb(192 132 252); }
    .fdbv-null { color: rgb(156 163 175); font-style: italic; }
</style>

<div class="fdbv-detail">
    @foreach ($columns as $column)
        @php
            $value = $row[$column] ?? null;
            $tree = $treeFor($value);
        @endphp
        <div class="fdbv-field" x-data="{ copied: false }">
            <div class="fdbv-field-name">
                <span>{{ $column }}</span>
                @if ($value !== null)
                    <button
                        type="button"
                        class="fdbv-copy"
                        x-on:click="navigator.clipboard.writeText($refs.raw.textContent).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                    >
                        <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy') }}'"></span>
                    </button>
                @endif
            </div>

            @if ($value === null)
                <div class="fdbv-field-value"><span class="fdbv-null">NULL</span></div>
            @elseif ($tree !== null)
                <div class="fdbv-tree">
                    @include('filament-dbview::components.record-detail-node', ['value' => $tree, 'depth' => 0])
                </div>
            @else
                <div class="fdbv-field-value">{{ is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '') }}</div>
            @endif

            @if ($value !== null)
                <span x-ref="raw" hidden>{{ $rawString($value) }}</span>
            @endif
        </div>
    @endforeach
</div>
