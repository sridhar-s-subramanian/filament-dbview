@php
    /** Recursive JSON tree node. Shared styles live in record-detail.blade.php. */
    $depth = $depth ?? 0;
    $isContainer = is_array($value);
@endphp

@if ($isContainer)
    @php
        $isList = array_is_list($value);
        $count = count($value);
        $openByDefault = $depth < 1 ? 'true' : 'false';
        $meta = $isList ? '['.$count.']' : '{'.$count.'}';
    @endphp
    <div class="fdbv-node" x-data="{ open: {{ $openByDefault }} }">
        @if ($count === 0)
            <div class="fdbv-leaf">
                @isset($nodeKey)<span class="fdbv-key">{{ $nodeKey }}</span><span class="fdbv-punc">: </span>@endisset
                <span class="fdbv-meta">{{ $meta }}</span>
            </div>
        @else
            <div class="fdbv-node-head" x-on:click="open = ! open">
                <span class="fdbv-caret" :class="{ 'is-open': open }">▶</span>
                @isset($nodeKey)<span class="fdbv-key">{{ $nodeKey }}</span><span class="fdbv-punc">: </span>@endisset
                <span class="fdbv-meta">{{ $meta }}</span>
            </div>
            <div class="fdbv-children" x-show="open" x-cloak>
                @foreach ($value as $k => $v)
                    @include('filament-dbview::components.record-detail-node', [
                        'nodeKey' => $k,
                        'value' => $v,
                        'depth' => $depth + 1,
                    ])
                @endforeach
            </div>
        @endif
    </div>
@else
    @php
        [$text, $cls] = match (true) {
            is_null($value) => ['null', 'fdbv-null'],
            is_bool($value) => [$value ? 'true' : 'false', 'fdbv-bool'],
            is_int($value) || is_float($value) => [(string) $value, 'fdbv-num'],
            default => ['"'.$value.'"', 'fdbv-str'],
        };
    @endphp
    <div class="fdbv-leaf">
        @isset($nodeKey)<span class="fdbv-key">{{ $nodeKey }}</span><span class="fdbv-punc">: </span>@endisset
        <span class="fdbv-val {{ $cls }}">{{ $text }}</span>
    </div>
@endif
