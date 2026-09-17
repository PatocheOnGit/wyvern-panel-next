@php
    $value = $getValue();
    $ratio = $getRatio();
@endphp

<div class="fi-small-stat-block">
    @if ($isCopyable($value))
        <button type="button" class="fi-small-stat-block-body" x-on:click="
            navigator.clipboard.writeText(@js($value));
            $tooltip(@js($getCopyMessage($value)), {
                theme: $store.theme,
                timeout: 2000,
            })">
    @else
        <span class="fi-small-stat-block-body">
    @endif
            <span class="fi-small-stat-block-label">{{ $getLabel() }}</span>
            <span class="fi-small-stat-block-value">{{ $value }}</span>

            {{-- Drawn only where there is a ceiling to measure against. An unmetered
                 track says "not measured"; a bar at zero would say "idle". --}}
            @if ($ratio !== null)
                <span class="fi-small-stat-block-meter">
                    <i style="width: {{ $ratio * 100 }}%; background: {{ $getRatioColor() }};"></i>
                </span>
            @endif
    @if ($isCopyable($value))
        </button>
    @else
        </span>
    @endif
</div>
